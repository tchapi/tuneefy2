<?php

namespace App\Controller;

use App\Entity\ApiClient;
use App\Services\StatsService;
use Doctrine\ORM\EntityManagerInterface;
use League\Bundle\OAuth2ServerBundle\Manager\ClientManagerInterface;
use League\Bundle\OAuth2ServerBundle\Model\Client;
use League\Bundle\OAuth2ServerBundle\ValueObject\Grant;
use League\Bundle\OAuth2ServerBundle\ValueObject\Scope;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin', name: 'admin_')]
class BackendController extends AbstractController
{
    #[Route('/dashboard', name: 'dashboard')]
    public function dashboard(StatsService $statsService, EntityManagerInterface $entityManager): Response
    {
        $statsRaw = $statsService->getApiStats();
        $clients = $entityManager->getRepository(ApiClient::class)->findAllWithOAuth2Client();
        $activeClients = array_filter($clients, function ($e) {return $e['active']; });
        $stats = [];

        foreach ($statsRaw as $stat) {
            if (isset($stats[StatsService::METHOD_NAMES[$stat['method']]])) {
                $stats[StatsService::METHOD_NAMES[$stat['method']]] += $stat['count'];
            } else {
                $stats[StatsService::METHOD_NAMES[$stat['method']]] = $stat['count'];
            }
        }

        // Format
        foreach ($stats as $key => $stat) {
            if ($stat > 1000000) {
                $stats[$key] = number_format($stat / 1000000, 2, ',', ' ').' M';
            } elseif ($stat > 1000) {
                $stats[$key] = number_format($stat / 1000, 0, ',', ' ').' k';
            } else {
                $stats[$key] = number_format($stat, 0, ',', ' ');
            }
        }

        return $this->render('admin/dashboard.html.twig', [
            'itemsStats' => $statsService->getItemsStats(),
            'apiStats' => $stats,
            'clients' => $clients,
            'activeClients' => $activeClients,
        ]);
    }

    #[Route('/api/clients', name: 'clients')]
    public function clients(StatsService $statsService, EntityManagerInterface $entityManager): Response
    {
        $clients = $entityManager->getRepository(ApiClient::class)->findAllWithOAuth2Client();
        $statsRaw = $statsService->getApiStats();

        $stats = [];
        foreach ($statsRaw as $stat) {
            $stats[$stat['client_id']][] = [
                'method' => StatsService::METHOD_NAMES[$stat['method']],
                'count' => $stat['count'],
            ];
        }

        return $this->render('admin/clients.html.twig', [
            'clients' => $clients,
            'stats' => $stats,
        ]);
    }

    #[Route('/api/clients/new', name: 'new_client')]
    public function createClient(Request $request, EntityManagerInterface $entityManager, ClientManagerInterface $clientManager): Response
    {
        $apiClient = new ApiClient();

        $form = $this->createFormBuilder($apiClient)
         ->add('name', TextType::class)
         ->add('email', TextType::class)
         ->add('url', TextType::class)
         ->add('description', TextType::class)
         // Used to create the Oauth2 client
         ->add('active', CheckboxType::class, ['mapped' => false])
         ->add('identifier', TextType::class, ['mapped' => false])
         ->add('secret', TextType::class, ['mapped' => false])
         ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $apiClient = $form->getData();
            $apiClient->setCreatedAt(new \DateTime());

            // OAuth2 client fields
            $active = $form->get('active')->getData();
            $identifier = $form->get('identifier')->getData();
            $secret = $form->get('secret')->getData();

            $client = new Client($apiClient->getName(), $identifier, $secret);
            $client->setActive($active);

            $client
                ->setGrants(new Grant('client_credentials'), new Grant('refresh_token'))
                ->setScopes(new Scope('api'))
            ;

            $clientManager->save($client);

            $apiClient->setOauth2ClientIdentifier($identifier);

            $entityManager->persist($apiClient);
            $entityManager->flush();

            return $this->redirectToRoute('admin_clients');
        }

        return $this->render('admin/clients.new.html.twig', [
            'form' => $form,
        ]);
    }
}
