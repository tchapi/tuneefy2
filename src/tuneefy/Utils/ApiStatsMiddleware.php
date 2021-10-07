<?php

namespace tuneefy\Utils;

use Slim\Http\Request;
use Slim\Http\Response;
use tuneefy\DB\DatabaseHandler;

class ApiStatsMiddleware
{
    public function __construct($container)
    {
        $this->container = $container;
    }

    public function __invoke(Request $request, Response $response, $next)
    {
        $response = $next($request, $response);

        $method = $this->container->has('api_method') ? $this->container->get('api_method') : null;
        $clientId = $this->container->has('token') ? $this->container->get('token')['client_id'] : null;

        if (null !== $clientId) { // If we're using oauth, else it's unecessary
            $db = DatabaseHandler::getInstance(null);
            $db->addApiCallingStat($clientId, $method ?? DatabaseHandler::METHOD_OTHER);
        }

        return $response;
    }
}
