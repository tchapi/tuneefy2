<?php

namespace tuneefy\Utils;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use RKA\ContentTypeRenderer\Renderer;
use Slim\Interfaces\ErrorHandlerInterface;
use Slim\Psr7\Response;
use Slim\Views\Twig;
use tuneefy\Controller\ApiController;

class CustomErrorHandler implements ErrorHandlerInterface
{
    private bool $isApiRoute;
    private Twig $twig;
    private $logger;

    public function __construct(bool $isApiRoute, Twig $twig, LoggerInterface $logger)
    {
        $this->isApiRoute = $isApiRoute;
        $this->twig = $twig;
        $this->logger = $logger;
    }

    private function makePrettyException(\Throwable $e)
    {
        $trace = $e->getTrace();
        $result = $e->getMessage();
        $result .= '" @ ';
        if (array_key_exists('class', $trace[0]) && '' != $trace[0]['class']) {
            $result .= $trace[0]['class'];
            $result .= '->';
        }
        if (array_key_exists('function', $trace[0])) {
          $result .= $trace[0]['function'];
          $result .= '()';
        }

        return $result;
    }

    public function __invoke(ServerRequestInterface $request, \Throwable $exception, bool $displayErrorDetails, bool $logErrors, bool $logErrorDetails): ResponseInterface
    {
        $this->logger->error($this->makePrettyException($exception));
        $response = (new Response())->withStatus(500);

        // Depending on the group we should render an error page or a structured response
        if (!$this->isApiRoute) {
            $response = $response->withHeader('Content-Type', 'text/html; charset=UTF-8');

            return $this->twig->render($response, '500.html.twig');
        } else {
            $renderer = new Renderer();

            return $renderer->render($request, $response, [
                'errors' => [ApiController::ERRORS['GENERAL_ERROR']],
            ]);
        }
    }
}
