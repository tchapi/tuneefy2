<?php

namespace tuneefy\Utils;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Handlers\NotFound;
use Slim\Views\Twig;

class CustomNotFoundHandler extends NotFound
{
    private $status;
    private $message;

    private $view;

    public function __construct(Twig $view, int $status, string $message)
    {
        $this->view = $view;
        $this->status = $status;
        $this->message = $message;
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response)
    {
        parent::__invoke($request, $response);

        // FIX ME this should only be for website 40X
        // All api errors should be differentiated (404, 405, etc ..) and use the middleware to give good response format (json, xml, etc)
        // See https://github.com/slimphp/Slim/issues/2220
        //$this->view->render($response, '404.html.twig');

        return $response
            ->withStatus($this->status)
            ->withHeader('Content-Type', 'text/html')
            ->write($this->message);
    }
}
