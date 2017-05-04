<?php

namespace tuneefy\Controller;

use Interop\Container\ContainerInterface;
use RKA\ContentTypeRenderer\Renderer;
use tuneefy\DB\DatabaseHandler;
use tuneefy\PlatformEngine;
use tuneefy\Utils\Utils;

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

    public function show($request, $response, $args)
    {
        if ($args['uid'] === null || $args['uid'] === '') {
            return $response->withStatus(404);
        }

        $db = DatabaseHandler::getInstance(null);

        // Translate into good id
        $id = Utils::fromUId($args['uid']);
        $item = $db->getItemById($id);

        // Check the type and redirect if necessary
        if ($item->getType() !== $args['type']) {
            $route = $this->container->get('router')->pathFor('show', [
                'type' => $item->getType(),
                'uid' => $args['uid'],
            ]);

            return $response->withStatus(301)->withHeader('Location', $route);
        }

        if (!is_null($item)) {
            return $this->container->get('view')->render($response, 'item.'.$args['type'].'.html.twig', [
                'uid' => $args['uid'],
                'item' => $item,
            ]);
        } else {
            return $response->withStatus(404);
        }
    }

    public function listen($request, $response, $args)
    {
        if ($args['uid'] === null || $args['uid'] === '') {
            return $response->withStatus(404);
        }

        $db = DatabaseHandler::getInstance(null);

        // Translate into good id
        $id = Utils::fromUId($args['uid']);
        $item = $db->getItemById($id);

        // Check we have a 'platform' link
        $links = $item->getLinksForPlatform($args['platform']);

        $index = intval($args['i'] ?? 0);

        if (count($links) <= $index) {
            return $response->withStatus(404);
        }

        // Eventually, redirect to platform
        return $response->withStatus(303)->withHeader('Location', $links[$index]);
    }

    public function api($request, $response, $args)
    {
        return $this->container->get('view')->render($response, 'api.html');
    }
}
