<?php

namespace tuneefy\Utils;

use RKA\ContentTypeRenderer\Renderer;
use Slim\Http\Request;
use Slim\Http\Response;
use tuneefy\Controller\ApiController;
use tuneefy\DB\DatabaseHandler;

class ApiActiveMiddleware
{
    public function __construct($container)
    {
        $this->container = $container;
        $this->renderer = new Renderer();
    }

    public function __invoke(Request $request, Response $response, $next)
    {
        $method = isset($this->container['api_method']) ? $this->container['api_method'] : null;
        $clientId = isset($this->container['token']) ? $this->container['token']['client_id'] : null;

        if (null !== $clientId) { // If we're using oauth, else it's unecessary
            $db = DatabaseHandler::getInstance(null);
            $active = $db->isClientActive($clientId);
            if (false === $active || (is_array($active) && '0' == $active['active'])) {
                $response->withStatus(401);

                return $this->renderer->render($request, $response, [
                    'errors' => [ApiController::ERRORS['NOT_ACTIVE']],
                ]);
            }
        }

        $response = $next($request, $response);

        return $response;
    }
}
