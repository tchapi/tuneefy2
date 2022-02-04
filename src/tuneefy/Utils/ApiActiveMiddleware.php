<?php

namespace tuneefy\Utils;

use Chadicus\Slim\OAuth2\Middleware\Authorization;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use RKA\ContentTypeRenderer\Renderer;
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

    public function __invoke(Request $request, RequestHandler $handler): ResponseInterface
    {
        $token = $request->getAttribute(Authorization::TOKEN_ATTRIBUTE_KEY);
        $clientId = $token ? $token['client_id'] : null;

        if (null !== $clientId) { // If we're using oauth, else it's unecessary
            $db = DatabaseHandler::getInstance(null);
            $active = $db->isClientActive($clientId);
            if (false === $active || (is_array($active) && '0' == $active['active'])) {
                $response = new Response();
                $response->withStatus(401);

                return $this->renderer->render($request, $response, [
                    'errors' => [ApiController::ERRORS['NOT_ACTIVE']],
                ]);
            }
        }

        $response = $handler->handle($request);

        return $response;
    }
}
