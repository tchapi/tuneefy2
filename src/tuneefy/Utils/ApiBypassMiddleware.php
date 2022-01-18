<?php

namespace tuneefy\Utils;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

class ApiBypassMiddleware
{
    public function __construct($ApiParams)
    {
        $this->ApiParams = $ApiParams;
    }

    public function __invoke(Request $request, RequestHandler $handler): ResponseInterface
    {
        $session = new \SlimSession\Helper();

        $bypass = ('xmlhttprequest' === strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') &&
                    $session->get('bypassSecret', null) === $this->ApiParams['bypassSecret']);

        if ($bypass) {
            // Add the infinite lifetime access token for the next middleware
            $request = $request->withHeader('Authorization', 'bearer '.$this->ApiParams['bypassToken']);
        }

        $response = $handler->handle($request);

        return $response;
    }
}
