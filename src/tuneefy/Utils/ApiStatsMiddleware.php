<?php

namespace tuneefy\Utils;

use Chadicus\Slim\OAuth2\Middleware\Authorization;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use tuneefy\DB\DatabaseHandler;

class ApiStatsMiddleware
{
    public function __construct($container)
    {
        $this->container = $container;
    }

    public function __invoke(Request $request, RequestHandler $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        $method = $this->container->has('api_method') ? $this->container->get('api_method') : null;

        $token = $request->getAttribute(Authorization::TOKEN_ATTRIBUTE_KEY);
        $clientId = $token ? $token['client_id'] : null;

        if (null !== $clientId) { // If we're using oauth, else it's unecessary
            $db = DatabaseHandler::getInstance(null);
            $db->addApiCallingStat($method ?? DatabaseHandler::METHOD_OTHER, $clientId);
        }

        return $response;
    }
}
