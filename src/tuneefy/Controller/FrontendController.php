<?php

namespace tuneefy\Controller;

use Interop\Container\ContainerInterface;
use RKA\ContentTypeRenderer\Renderer;
use tuneefy\PlatformEngine;

class FrontendController
{
    protected $container;
    private $renderer;
    private $engine;

    // constructor receives container instance
    public function __construct(ContainerInterface $container)
    {
        // JSON / XML renderer
        $this->renderer = new Renderer();

        // Application engine
        $this->engine = new PlatformEngine();

        // Slim container
        $this->container = $container;
    }

    public function home($request, $response, $id)
    {
        return $this->container->get('view')->render($response, 'home.html.twig', [
            'platforms' => $this->engine->getAllPlatforms(),
        ]);
    }

    public function about($request, $response, $id)
    {
        return $this->container->get('view')->render($response, 'about.html.twig', [
            'platforms' => $this->engine->getAllPlatforms(),
        ]);
    }

    public function trends($request, $response, $id)
    {
        return $this->container->get('view')->render($response, 'trends.html.twig', [
            'platforms' => $this->engine->getAllPlatforms(),
        ]);
    }

    public function share($request, $response, $args)
    {
        // Translate into good id
        $id = Utils::fromUId($args['uid']);
        $item = $this->db->getItem($id);

        if (!is_null($item)) {
            return $this->container->get('view')->render($response, 'item.html.twig', ['item' => $item->toArray()]);
        } else {
            return $response->withStatus(404);
        }
    }

    public function listen($request, $response, $args)
    {
        // TODO

        // Eventually, redirect to platform
        return $response->withStatus(303)->withHeader('Location', 'http://the/link/on/the/platform'); // "See Other"
    }

    public function api($request, $response, $args)
    {
        return $this->container->get('view')->render($response, 'api.html');
    }
}
