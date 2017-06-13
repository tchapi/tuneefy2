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

    public function __construct(Twig $view, int $status, string $message)
    {
        $this->view = $view;
        $this->status = $status;
        $this->message = $message;

        $this->renderer = new Renderer();
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response)
    {
        $isApiRoute = (substr($request->getUri()->getPath(), 0, 4) === 'api/');

        // Depending on the group we should render an error page or a structured response
        if (!$isApiRoute) {
            $response = new Response($this->status);

            return $this->view->render($response, '404.html.twig');
        } else {
            $response->withStatus($this->status);

            return $this->renderer->render($request, $response, [
                'errors' => [ApiController::ERRORS[$this->message]],
            ]);
        }
    }
}
