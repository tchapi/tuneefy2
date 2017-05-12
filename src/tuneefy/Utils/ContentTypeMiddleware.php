<?php

namespace tuneefy\Utils;

use RKA\ContentTypeRenderer\Renderer;
use Slim\Http\Request;
use Slim\Http\Response;
use tuneefy\Controller\ApiController;

class ContentTypeMiddleware
{
    private $defaultContentType = 'application/json';
    private $allowedContentTypes = [
        'html' => 'text/html',
        'xml' => 'application/xml',
        'json' => 'application/json',
    ];

    public function __construct($container)
    {
        $this->container = $container;
    }

    public function __invoke(Request $request, Response $response, $next)
    {
        $request = $this->resolveContentType($request);

        /**
         * Process All Middlewares.
         */
        $response = $next($request, $response);

        $isApiRoute = (preg_match("/^\/?api\//", $request->getUri()->getPath()));

        if ($isApiRoute) {
            $renderer = new Renderer();
            if (401 === $response->getStatusCode()) {
                $response = $renderer->render($request, $response, [
                    'errors' => [ApiController::ERRORS['NOT_AUTHORIZED']],
                ]);
            } elseif (4 === intval($response->getStatusCode() / 100)) {
                $response = $renderer->render($request, $response, [
                    'errors' => [ApiController::ERRORS[$response->getBody()->__toString()]],
                ]);
            } elseif (5 === intval($response->getStatusCode() / 100)) {
                $response = $renderer->render($request, $response, [
                    'errors' => [ApiController::ERRORS['GENERAL_ERROR']],
                ]);
            }
        } else {
            if (4 === intval($response->getStatusCode() / 100)) {
                $handler = $this->container['notFoundHandler'];
                return $handler($request, $response);
            }
        }

        return $response;
    }

    private function resolveContentType(Request $request)
    {
        // Accept the 'format' modifier
        $contentType = $this->defaultContentType;
        $format = $request->getParam('format');

        if ($format && isset($this->allowedContentTypes[$format])) {
            return $request->withHeader('Accept', $this->allowedContentTypes[$format]);
        }

        return $request;
    }
}
