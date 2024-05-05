<?php

namespace App\Command;

use App\Entity\ApiClient;
use Doctrine\ORM\EntityManagerInterface;
use League\Bundle\OAuth2ServerBundle\Manager\ClientManagerInterface;
use League\Bundle\OAuth2ServerBundle\Model\Client;
use League\Bundle\OAuth2ServerBundle\ValueObject\Grant;
use League\Bundle\OAuth2ServerBundle\ValueObject\Scope;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'tuneefy:migrate')]
class MigrationCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ClientManagerInterface $clientManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
        ->addOption(
            'execute',
            null,
            InputOption::VALUE_NONE,
            'Do not do a dry-run, execute for real'
        )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $sql = 'select * from oauth_clients;';
        $conn = $this->entityManager->getConnection();

        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery();

        $clients = $result->fetchAllAssociative();

        $output->writeln('Found '.count($clients));

        foreach ($clients as $client) {
            $active = $client['active'];
            $id = $client['client_id'];
            $secret = $client['client_secret'];

            $oauth2_client = new Client($client['name'], $id, $secret);
            $oauth2_client->setActive($active);

            $oauth2_client
                ->setGrants(new Grant('client_credentials'), new Grant('refresh_token'))
                ->setScopes(new Scope('api'))
            ;

            $output->writeln('OAUTH2 Client: '.$oauth2_client->getIdentifier());

            if ($input->getOption('execute')) {
                $this->clientManager->save($oauth2_client);
            }

            $api_client = (new ApiClient())
              ->setName($client['name'])
              ->setCreatedAt(new \DateTime($client['created_at']))
              ->setDescription($client['description'])
              ->setEmail($client['email'])
              ->setOauth2ClientIdentifier($id)
              ->setUrl($client['url']);

            $output->writeln('API Client   : '.$api_client->getName().' - '.$api_client->getCreatedAt()->format('Y-m-d H:i:s').' / '.$client['created_at']);
            if ($input->getOption('execute')) {
                $this->entityManager->persist($api_client);
            }
        }

        if ($input->getOption('execute')) {
            $this->entityManager->flush();
        }

        return Command::SUCCESS;
    }
}
