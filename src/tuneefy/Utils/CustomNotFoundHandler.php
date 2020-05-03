<?php

namespace tuneefy\Utils;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RKA\ContentTypeRenderer\Renderer;
use Slim\Http\Response;
use Slim\Views\Twig;
use tuneefy\Controller\ApiController;

class CustomNotFoundHandler
{
    private $status;
    private $message;

    private $view;

    private $renderer;

    public function __construct(Twig $view, bool $isApiRoute, int $status, string $message)
    {
        $this->isApiRoute = $isApiRoute;
        $this->view = $view;
        $this->status = $status;
        $this->message = $message;

        $this->renderer = new Renderer();
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response)
    {
        // Depending on the group we should render an error page or a structured response
        if (!$this->isApiRoute) {
            $response = $response->withStatus($this->status)
                                 ->withHeader('Content-Type', 'text/html; charset=UTF-8');

            return $this->view->render($response, '404.html.twig');
        } else {
            $response->withStatus($this->status);

            return $this->renderer->render($request, $response, [
                'errors' => [ApiController::ERRORS[$this->message]],
            ]);
        }
    }
}
