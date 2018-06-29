<?php

namespace tuneefy\Controller;

use Interop\Container\ContainerInterface;
use tuneefy\DB\DatabaseHandler;

class BackendController
{
    protected $container;

    // constructor receives container instance
    public function __construct(ContainerInterface $container)
    {
        // Slim container
        $this->container = $container;
    }

    public function dashboard($request, $response)
    {
        $db = DatabaseHandler::getInstance(null);

        $statsRaw = $db->getApiStats();
        $clients = $db->getApiClients();
        $activeClients = array_filter($clients, function ($e) {return $e['active']; });
        $stats = [];

        foreach ($statsRaw as $stat) {
            if (isset($stats[$stat['method']])) {
                $stats[DatabaseHandler::METHOD_NAMES[$stat['method']]] += $stat['count'];
            } else {
                $stats[DatabaseHandler::METHOD_NAMES[$stat['method']]] = $stat['count'];
            }
        }

        return $this->container->get('view')->render($response, 'admin/dashboard.html.twig', [
            'params' => $this->container->get('params'),
            'itemsStats' => $db->getItemsStats(),
            'apiStats' => $stats,
            'clients' => $clients,
            'activeClients' => $activeClients,
        ]);
    }

    public function clients($request, $response)
    {
        $db = DatabaseHandler::getInstance(null);
        $clients = $db->getApiClients();
        $statsRaw = $db->getApiStats();

        $stats = [];
        foreach ($statsRaw as $stat) {
            $stats[$stat['client_id']][] = [
                'method' => DatabaseHandler::METHOD_NAMES[$stat['method']],
                'count' => $stat['count'],
            ];
        }

        return $this->container->get('view')->render($response, 'admin/clients.html.twig', [
            'params' => $this->container->get('params'),
            'clients' => $clients,
            'stats' => $stats,
        ]);
    }

    public function createClient($request, $response)
    {
        if ($request->isPost()) {
            $db = DatabaseHandler::getInstance(null);
            $db->addApiClient(
                $request->getParsedBodyParam('name'),
                $request->getParsedBodyParam('client_id'),
                $request->getParsedBodyParam('client_secret'),
                $request->getParsedBodyParam('description'),
                $request->getParsedBodyParam('email'),
                $request->getParsedBodyParam('url'),
                $request->getParsedBodyParam('active')
            );

            $uri = $request->getUri()->withPath($this->container->get('router')->pathFor('admin_clients'));

            return $response->withRedirect($uri);
        }

        return $this->container->get('view')->render($response, 'admin/clients.new.html.twig', [
            'params' => $this->container->get('params'),
        ]);
    }
}
