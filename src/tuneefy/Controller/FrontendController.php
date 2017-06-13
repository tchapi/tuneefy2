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

    public function home($request, $response)
    {
        return $this->container->get('view')->render($response, 'home.html.twig', [
            'params' => $this->container->get('params'),
            'platforms' => $this->engine->getAllPlatforms(),
        ]);
    }

    public function about($request, $response)
    {
        return $this->container->get('view')->render($response, 'about.html.twig', [
            'params' => $this->container->get('params'),
            'platforms' => $this->engine->getAllPlatforms(),
        ]);
    }

    public function mail($request, $response)
    {
        // TODO FIX ME SEND MAIL FOR REAL
        $body = $response->getBody();
        $body->write(1);

        return $response;
    }

    public function trends($request, $response)
    {
        $db = DatabaseHandler::getInstance(null);
        $platforms = $this->engine->getAllPlatforms();
        $stats = [];

        // Get platform shares
        $shares = $db->getPlatformShares();
        $total = 0;

        $stats['hits'] = [];
        foreach ($shares as $key => $value) {
            $stats['hits'][$key] = [
                'platform' => $this->engine->getPlatformByTag($key),
                'count' => $value['count'],
            ];
            $total = $total + intval($value['count']);
        }

        $stats['tracks'] = $db->getMostViewedTracks();
        foreach ($stats['tracks'] as $key => $item) {
            $stats['tracks'][$key]['uid'] = Utils::toUId($item['id']);
        }
        $stats['albums'] = $db->getMostViewedAlbums();
        foreach ($stats['albums'] as $key => $item) {
            $stats['albums'][$key]['uid'] = Utils::toUId($item['id']);
        }

        $stats['artists'] = [];

        return $this->container->get('view')->render($response, 'trends.html.twig', [
            'params' => $this->container->get('params'),
            'platforms' => $this->engine->getAllPlatforms(),
            'stats' => $stats,
            'total' => $total,
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
        try {
            $item = $db->getItemById($id);
        } catch (\Exception $e) {
            return $response->withStatus(404);
        }

        // Check the type and redirect if necessary
        if ($item->getType() !== $args['type']) {
            $route = $this->container->get('router')->pathFor('show', [
                'params' => $this->container->get('params'),
                'type' => $item->getType(),
                'uid' => $args['uid'],
            ]);

            return $response->withStatus(301)->withHeader('Location', $route);
        }

        // Increment stats
        try {
            $db->addViewingStat($id);
        } catch (\Exception $e) {
            // Let's redirect anyway, we should log an error somehow TODO FIX ME
        }

        if (!is_null($item)) {
            return $this->container->get('view')->render($response, 'item.'.$args['type'].'.html.twig', [
                'params' => $this->container->get('params'),
                'uid' => $args['uid'],
                'item' => $item,
            ]);
        } else {
            return $response->withStatus(404);
        }
    }

    public function listen($request, $response, $args)
    {
        $platform = strtolower($args['platform']);

        if ($args['uid'] === null || $args['uid'] === '') {
            return $response->withStatus(404);
        }

        $db = DatabaseHandler::getInstance(null);

        // Translate into good id
        $id = Utils::fromUId($args['uid']);
        try {
            $item = $db->getItemById($id);
        } catch (\Exception $e) {
            return $response->withStatus(404);
        }

        // Check we have a 'platform' link
        $links = $item->getLinksForPlatform($platform);

        $index = intval($args['i'] ?? 0);

        if ($links === [] || count($links) <= $index) {
            return $response->withStatus(404);
        }

        // Increment stats
        try {
            $db->addListeningStat($id, $platform, $index);
        } catch (\Exception $e) {
            // Let's redirect anyway, we should log an error somehow TODO FIX ME
        }

        // Eventually, redirect to platform
        return $response->withStatus(303)->withHeader('Location', $links[$index]);
    }

    public function api($request, $response, $args)
    {
        return $this->container->get('view')->render($response, 'api.html');
    }
}
