<?php

namespace tuneefy\Utils;

use RKA\ContentTypeRenderer\Renderer;
use Slim\Views\Twig;
use tuneefy\Controller\ApiController;

class CustomErrorHandler
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

    public function __invoke($request, $response, $exceptionOrError)
    {
        $isApiRoute = (substr($request->getUri()->getPath(), 0, 4) === 'api/');

        // Depending on the group we should render an error page or a structured response
        if (!$isApiRoute) {
            $this->view->render($response, '500.html.twig');

            return $response->withStatus($this->status);
        } else {
            $response->withStatus($this->status);

            return $this->renderer->render($request, $response, [
                'errors' => [ApiController::ERRORS[$this->message]],
            ]);
        }
    }
}
