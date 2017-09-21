<?php

namespace tuneefy\Utils;

use Slim\Http\Request;
use Slim\Http\Response;

class ApiBypassMiddleware
{
    public function __construct($ApiParams)
    {
        $this->ApiParams = $ApiParams;
    }

    public function __invoke(Request $request, Response $response, $next)
    {
        $session = new \SlimSession\Helper();

        $bypass = (strtolower(@$_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest' &&
                    $session->get('bypassSecret', null) === $this->ApiParams['bypassSecret']);

        if ($bypass) {
            // Add the infinite lifetime access token for the next middleware
            $request = $request->withHeader('Authorization', 'bearer '.$this->ApiParams['bypassToken']);
        }

        $response = $next($request, $response);

        return $response;
    }
}
