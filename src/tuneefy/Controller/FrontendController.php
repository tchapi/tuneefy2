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
        // Let's bypass OAuth by setting a session secret
        $session = new \SlimSession\Helper();
        $session->bypassSecret = $this->container->get('params')['api']['bypassSecret'];

        $default_platforms = implode(',', array_reduce($this->engine->getAllPlatforms(), function ($carry, $e) {
            if ($e->isDefault()) {
                $carry[] = $e->getTag();
            }

            return $carry;
        }, []));

        // Get the most viewed and last shared
        $db = DatabaseHandler::getInstance(null);
        $mostViewed = $db->getMostViewedItemThisWeek();
        if ($mostViewed['id']) {
            $mostViewed['uid'] = Utils::toUId($mostViewed['id']);
        }

        $lastShared = $db->getLastSharedItems();
        if (isset($lastShared['track']) && $lastShared['track']['id']) {
            $lastShared['track']['uid'] = Utils::toUId($lastShared['track']['id']);
        }
        if (isset($lastShared['album']) && $lastShared['album']['id']) {
            $lastShared['album']['uid'] = Utils::toUId($lastShared['album']['id']);
        }

        if ('42' == $request->getQueryParam('widget')) {
            return $this->container->get('view')->render($response, '_widget.html.twig', [
                'query' => $request->getQueryParam('q'),
                'params' => $this->container->get('params'),
                'default_platforms' => $default_platforms,
            ]);
        } else {
            return $this->container->get('view')->render($response, 'home.html.twig', [
                'query' => $request->getQueryParam('q'),
                'params' => $this->container->get('params'),
                'platforms' => $this->engine->getAllPlatforms(),
                'default_platforms' => $default_platforms,
                'last_shared' => $lastShared,
                'most_viewed' => $mostViewed,
            ]);
        }
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
        $allPostVars = $request->getParsedBody();

        $sanitized_email = filter_var($allPostVars['mail'], FILTER_SANITIZE_EMAIL);

        $params = $this->container->get('params');

        // Create the Transport
        $transport = (new \Swift_SmtpTransport($params['mail']['smtp_server'], 25))
          ->setUsername($params['mail']['smtp_user'])
          ->setPassword($params['mail']['smtp_password']);
        $mailer = new \Swift_Mailer($transport);

        $message = (new \Swift_Message('[CONTACT] '.$sanitized_email.' (via tuneefy.com)"'))
          ->setFrom([$params['mail']['contact_email']])
          ->setTo([$params['mail']['team_email']])
          ->setBody($sanitized_email." sent a message from the site : \n\n".nl2br($allPostVars['message']));

        // Send the message
        $result = $mailer->send($message);

        // Return a response
        $body = $response->getBody();
        $body->write(0 + ($result > 0));

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

        $stats['artists'] = $db->getMostViewedArtists();

        return $this->container->get('view')->render($response, 'trends.html.twig', [
            'params' => $this->container->get('params'),
            'platforms' => $this->engine->getAllPlatforms(),
            'stats' => $stats,
            'total' => $total,
        ]);
    }

    // Handles legacy routes as well with a 301
    public function show($request, $response, $args)
    {
        if (null === $args['uid'] || '' === $args['uid']) {
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
        if (!isset($args['type']) || $item->getType() !== $args['type']) {
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
            // Override, just to get the page in JSON
            if ('json' === $request->getQueryParam('format')) {
                $response = $this->renderer->render($request->withHeader('Accept', 'application/json'), $response, $item->toArray());

                return $response->withStatus(200);
            } else {
                return $this->container->get('view')->render($response, 'item.'.$args['type'].'.html.twig', [
                    'params' => $this->container->get('params'),
                    'uid' => $args['uid'],
                    'item' => $item,
                    'embed' => (null !== $request->getQueryParam('embed')),
                ]);
            }
        } else {
            return $response->withStatus(404);
        }
    }

    public function listen($request, $response, $args)
    {
        $platform = strtolower($args['platform']);

        if (null === $args['uid'] || '' === $args['uid']) {
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

    public function listenDirect($request, $response, $args)
    {
        $platform = strtolower($args['platform']);

        $link = $request->getQueryParam('l');

        if (null === $link || '' === $link) {
            return $response->withStatus(404);
        }

        $db = DatabaseHandler::getInstance(null);

        // Increment stats
        try {
            $db->addListeningStatDirect($platform);
        } catch (\Exception $e) {
            // Let's redirect anyway, we should log an error somehow TODO FIX ME
        }

        // Eventually, redirect to platform
        return $response->withStatus(303)->withHeader('Location', $link);
    }

    public function api($request, $response, $args)
    {
        return $this->container->get('view')->render($response, 'api.html');
    }

    public function apiRateLimiting($request, $response, $args)
    {
        return $this->container->get('view')->render($response, '503.html.twig');
    }
}
