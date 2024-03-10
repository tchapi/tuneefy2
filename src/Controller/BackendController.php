<?php

namespace App\Controller;

use App\Services\StatsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin', name: 'admin_')]
class BackendController extends AbstractController
{
    #[Route('/dashboard', name: 'dashboard')]
    public function dashboard(StatsService $statsService): Response
    {
        // $db = DatabaseHandler::getInstance(null);

        $statsRaw = $statsService->getApiStats();
        $clients = []; // $db->getApiClients();
        $activeClients = array_filter($clients, function ($e) {return $e['active']; });
        $stats = [];

        foreach ($statsRaw as $stat) {
            if (isset($stats[DatabaseHandler::METHOD_NAMES[$stat['method']]])) {
                $stats[DatabaseHandler::METHOD_NAMES[$stat['method']]] += $stat['count'];
            } else {
                $stats[DatabaseHandler::METHOD_NAMES[$stat['method']]] = $stat['count'];
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
    public function clients(StatsService $statsService): Response
    {
        // $db = DatabaseHandler::getInstance(null);
        $clients = $db->getApiClients();
        $statsRaw = $statsService->getApiStats();

        $stats = [];
        foreach ($statsRaw as $stat) {
            $stats[$stat['client_id']][] = [
                'method' => DatabaseHandler::METHOD_NAMES[$stat['method']],
                'count' => $stat['count'],
            ];
        }

        return $this->render('admin/clients.html.twig', [
            'clients' => $clients,
            'stats' => $stats,
        ]);
    }

    #[Route('/api/clients/new', name: 'new_client')]
    public function createClient(Request $request): Response
    {
        if ('POST' === $request->getMethod()) {
            // $db = DatabaseHandler::getInstance(null);
            $params = $request->getParsedBody();

            $db->addApiClient(
                $params['name'],
                $params['client_id'],
                $params['client_secret'],
                $params['description'],
                $params['email'],
                $params['url'],
                'on' == $params['active']
            );

            return $this->redirectToRoute('admin_clients');
        }

        return $this->render('admin/clients.new.html.twig', [
        ]);
    }
}
