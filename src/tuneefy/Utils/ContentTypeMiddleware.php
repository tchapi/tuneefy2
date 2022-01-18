<?php

namespace tuneefy\Utils;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use RKA\ContentTypeRenderer\Renderer;
use tuneefy\Controller\ApiController;

class ContentTypeMiddleware
{
    private $defaultContentType = 'application/json';
    private $allowedContentTypes = [
        'html' => 'text/html',
        'xml' => 'application/xml',
        'json' => 'application/json',
    ];

    public function __invoke(Request $request, RequestHandler $handler): ResponseInterface
    {
        $request = $this->resolveContentType($request);

        /**
         * Process All Middlewares.
         */
        $response = $handler->handle($request);

        $renderer = new Renderer();
        if (401 === $response->getStatusCode()) {
            $response = $renderer->render($request, $response, [
                'errors' => [ApiController::ERRORS['NOT_AUTHORIZED']],
            ]);
        } elseif (4 === intval($response->getStatusCode() / 100) &&
                isset(ApiController::ERRORS[$response->getBody()->__toString()])) {
            $response = $renderer->render($request, $response, [
                'errors' => [ApiController::ERRORS[$response->getBody()->__toString()]],
            ]);
        } elseif (4 === intval($response->getStatusCode() / 100)) {
            $response = $renderer->render($request, $response, [
                'errors' => [ApiController::ERRORS[404]],
            ]);
        } elseif (5 === intval($response->getStatusCode() / 100)) {
            $response = $renderer->render($request, $response, [
                'errors' => [ApiController::ERRORS['GENERAL_ERROR']],
            ]);
        }

        return $response;
    }

    private function resolveContentType(Request $request)
    {
        // Accept the 'format' modifier
        $contentType = $this->defaultContentType;
        $params = $request->getQueryParams();
        $format = $params['format'] ?? null;

        if ($format && isset($this->allowedContentTypes[$format])) {
            return $request->withHeader('Accept', $this->allowedContentTypes[$format]);
        }

        return $request;
    }
}
