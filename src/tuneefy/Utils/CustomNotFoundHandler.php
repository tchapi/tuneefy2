<?php

namespace tuneefy\Utils;

class CustomNotFoundHandler {
   public function __invoke($request, $response) {
        return $response
            ->withStatus(404)
            ->withHeader('Content-Type', 'text/html')
            ->write('Custom : page not found');
            //$this->slim_app->render('404.html.twig');
   }
}