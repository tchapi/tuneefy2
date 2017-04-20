<?php

namespace tuneefy\Utils;

class CustomErrorHandler
{
    public function __invoke($request, $response, $args)
    {
        return $response
            ->withStatus(500)
            ->withHeader('Content-Type', 'text/html')
            ->write('Custom : something went wrong!');
            //$this->slim_app->render('error.html.twig', ['message' => $e->getMessage()]);
    }
}
