<?php

namespace tuneefy\Utils;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RKA\ContentTypeRenderer\Renderer;
use Slim\Interfaces\ErrorHandlerInterface;
use Slim\Psr7\Response;
use Slim\Views\Twig;
use tuneefy\Controller\ApiController;

class CustomNotFoundHandler implements ErrorHandlerInterface
{
    private bool $isApiRoute;

    public function __construct(bool $isApiRoute)
    {
        $this->isApiRoute = $isApiRoute;
    }

    public function __invoke(ServerRequestInterface $request, \Throwable $exception, bool $displayErrorDetails, bool $logErrors, bool $logErrorDetails): ResponseInterface
    {
        $status = $exception->getCode();
        $response = new Response();

        // Depending on the group we should render an error page or a structured response
        if (!$this->isApiRoute) {
            $response = $response->withStatus($status)
                                 ->withHeader('Content-Type', 'text/html; charset=UTF-8');

            $twig = Twig::fromRequest($request);
            return $twig->render($response, '404.html.twig');
        } else {
            $response->withStatus($status);

            $renderer = new Renderer();

            return $renderer->render($request, $response, [
                'errors' => [ApiController::ERRORS[$status]],
            ]);
        }
    }
}
