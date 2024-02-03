<?php

namespace tuneefy\Controller;

use Psr\Container\ContainerInterface;
use Slim\Routing\RouteContext;
use Slim\Views\Twig;
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
        $view = Twig::fromRequest($request);
        $db = DatabaseHandler::getInstance(null);

        $statsRaw = $db->getApiStats();
        $clients = $db->getApiClients();
        $activeClients = array_filter($clients, function ($e) {return $e['active']; });
        $stats = [];

        foreach ($statsRaw as $stat) {
            if (isset($stats[DatabaseHandler::METHOD_NAMES[$stat['method']]])) {
                $stats[DatabaseHandler::METHOD_NAMES[$stat['method']]] += $stat['count'];
            } else {
                $stats[DatabaseHandler::METHOD_NAMES[$stat['method']]] = $stat['count'];
            }
        }

        return $view->render($response, 'admin/dashboard.html.twig', [
            'params' => $this->container->get('params'),
            'itemsStats' => $db->getItemsStats(),
            'apiStats' => $stats,
            'clients' => $clients,
            'activeClients' => $activeClients,
        ]);
    }

    public function clients($request, $response)
    {
        $view = Twig::fromRequest($request);
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

        return $view->render($response, 'admin/clients.html.twig', [
            'params' => $this->container->get('params'),
            'clients' => $clients,
            'stats' => $stats,
        ]);
    }

    public function createClient($request, $response)
    {
        if ('POST' === $request->getMethod()) {
            $db = DatabaseHandler::getInstance(null);
            $params = $request->getParsedBody();

            $db->addApiClient(
                $params['name'],
                $params['client_id'],
                $params['client_secret'],
                $params['description'],
                $params['email'],
                $params['url'],
                ($params['active'] == "on")
            );

            $routeContext = RouteContext::fromRequest($request);
            $routeParser = $routeContext->getRouteParser();

            $route = $routeParser->urlFor('admin_clients');
            return $response->withStatus(301)->withHeader('Location', $route);
        }

        $view = Twig::fromRequest($request);

        return $view->render($response, 'admin/clients.new.html.twig', [
            'params' => $this->container->get('params'),
        ]);
    }
}
