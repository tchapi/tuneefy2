<?php

namespace tuneefy\Controller;

use Interop\Container\ContainerInterface;

class BackendController
{
    protected $container;

    // constructor receives container instance
    public function __construct(ContainerInterface $container)
    {
        // Slim container
        $this->container = $container;
    }

    public function dashboard($request, $response)
    {
        return $this->container->get('view')->render($response, 'admin/dashboard.html.twig');
    }

    public function clients($request, $response)
    {
        return $this->container->get('view')->render($response, 'admin/clients.html.twig');
    }
}
