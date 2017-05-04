<?php

namespace tuneefy\Controller;

use Interop\Container\ContainerInterface;
use RKA\ContentTypeRenderer\Renderer;
use tuneefy\DB\DatabaseHandler;
use tuneefy\PlatformEngine;

class ApiController
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

    public function getAllPlatforms($request, $response, $args)
    {
        $type = strtolower($request->getQueryParam('type'));

        $platforms = $this->engine->getAllPlatforms();

        if ($type != "") {
            $platforms = array_filter($platforms, function($e) use ($type) {
                return $e->getType() === $type;
            });

            if (count($platforms) === 0) {
                $response->write('This type of platform does not exist');
                return $response->withStatus(400);
            }
        }

        $data = ['platforms' => array_map(function ($e) { return $e->toArray(); }, $platforms)];
        $response = $this->renderer->render($request, $response, $data);

        return $response->withStatus(200);
    }


    public function getPlatform($request, $response, $args)
    {
        $platform = $this->engine->getPlatformByTag($args['tag']);

        if (!$platform) {
            // TODO translation
            $response->write('This platform does not exist');

            return $response->withStatus(404);
        }

        $response = $this->renderer->render($request, $response, $platform->toArray());

        return $response->withStatus(200);
    }

    public function lookup($request, $response, $args)
    {
        $permalink = $request->getQueryParam('q');
        $real_mode = $this->engine->translateFlag('mode', $request->getQueryParam('mode'));

        // Permalink could be null, but we don't accept that
        if ($permalink === null || $permalink === '') {
            // TODO translation
            $response->write('Missing or empty parameter : q (permalink)');

            return $response->withStatus(400);
        }

        if ($real_mode === null) {
            $real_mode = Platform::MODE_LAZY;
        }

        try {
            $result = $this->engine->lookup($permalink, $real_mode);
        } catch (PlatformException $e) {
            $result = false;
        }

        // From the try/catch up there
        if ($result === false) {
            $data = ['errors' => ['There was a problem while fetching data from the platform']];
        // If we have a result
        } elseif (isset($result['result'])) {
            if ($result['result']->getMusicalEntity()) {
                $data = [
                    'result' => $result['result']->toArray(),
                ];
            } else {
                $data = [
                    'errors' => ['No match was found for this permalink'],
                    'result' => $result['result']->toArray(),
                ];
            }
        // Result is only an error message
        } else {
            $data = $result;
        }

        $response = $this->renderer->render($request, $response, $data);

        return $response->withStatus(200);
    }

    public function search($request, $response, $args)
    {
        $query = $request->getQueryParam('q');
        $limit = $request->getQueryParam('limit');
        $real_type = $this->engine->translateFlag('type', $args['type']);
        $real_mode = $this->engine->translateFlag('mode', $request->getQueryParam('mode'));

        if ($query === null || $query === '') {
            // TODO translation
            $response->write('Missing or empty parameter : q (query)');

            return $response->withStatus(400);
        }

        $platform = $this->engine->getPlatformByTag($args['platform_str']);
        if ($platform === null) {
            // TODO translation
            $response->write('Invalid parameter : platform '.$args['platform_str'].' does not exist');

            return $response->withStatus(400);
        }

        if ($real_type === null) {
            // TODO translation
            $response->write('Invalid parameter : type '.$args['type'].' does not exist');

            return $response->withStatus(400);
        }

        if ($real_mode === null) {
            $real_mode = Platform::MODE_LAZY;
        }

        try {
            $result = $this->engine->search($platform, $real_type, $query, intval($limit), $real_mode);
        } catch (PlatformException $e) {
            $result = false;
        }

        // From the try/catch up there
        if ($result === false) {
            $data = ['errors' => ['There was a problem while fetching data from the platform']];
        // If we have a result
        } elseif (isset($result['results'])) {
            if (count($result['results']) > 0) {
                $data = [
                    'results' => array_map(function ($e) { return $e->toArray(); }, $result['results']),
                ];
            } else {
                $data = ['errors' => ['No match was found for this search on this platform']];
            }
        // Result is only an error message
        } else {
            $data = $result;
        }

        $response = $this->renderer->render($request, $response, $data);

        return $response->withStatus(200);
    }

    public function aggregate($request, $response, $args)
    {
        $query = $request->getQueryParam('q');
        $limit = $request->getQueryParam('limit');
        $include = $request->getQueryParam('include'); // Included platforms?
        $aggressive = true && ($request->getQueryParam('aggressive') && $request->getQueryParam('aggressive') == 'true'); // If aggressive, merge more (actual behaviour depends on the type)
        $real_type = $this->engine->translateFlag('type', $args['type']);
        $real_mode = $this->engine->translateFlag('mode', $request->getQueryParam('mode'));

        if ($query === null || $query === '') {
            // TODO translation
            $response->write('Missing or empty parameter : q (query)');

            return $response->withStatus(400);
        }

        // TODO FIXME: it's a bit cumbersome, should refactor
        if ($include === null || $include === '') { // If empty, include all.
            $platforms = $this->engine->getAllPlatforms();
        } else {
            $platforms = $this->engine->getPlatformsByTags(explode(',', strtolower($include)));
            if ($platforms === null) { // Silently fails if a name is invalid, that's ok
                $platforms = $this->engine->getAllPlatforms();
            }
        }

        if ($real_type === null) {
            // TODO translation
            $response->write('Invalid parameter : type '.$args['type'].' does not exist');

            return $response->withStatus(400);
        }

        if ($real_mode === null) {
            $real_mode = Platform::MODE_LAZY;
        }

        try {
            $result = $this->engine->aggregate($platforms, $real_type, $query, intval($limit), $real_mode, $aggressive);
        } catch (PlatformException $e) {
            $result = false;
        }

        // From the try/catch up there
        if ($result === false) {
            $data = ['errors' => ['There was a problem while fetching data from the platforms']];
        // If we have a result
        } elseif (isset($result['results'])) {
            if (count($result['results']) > 0) {
                $data = [
                    'errors' => $result['errors'],
                    'results' => array_map(function ($e) { return $e->toArray(); }, $result['results']),
                ];
            } else {
                $data = ['errors' => ['No match was found for this search']];
            }
        // Result is only an error message
        } else {
            $data = $result;
        }

        $response = $this->renderer->render($request, $response, $data);

        return $response->withStatus(200);
    }

    public function share($request, $response, $args)
    {
        $intent = $args['intent'];

        if ($intent === null || $intent === '') {
            // TODO translation
            $response->write('Missing or empty parameter : intent');

            return $response->withStatus(400);
        }

        $db = DatabaseHandler::getInstance(null);
        // Retrieve the intent
        try {
            $uid = $db->fixItemWithIntent($intent);
        } catch (\Exception $e) {
            $response->write($e->getMessage());

            return $response->withStatus(400);
        }

        $data = [
            'uid' => $uid,
            'link' => $this->container->get('params')['urls']['front'].
                      str_replace('{uid}', $uid, $this->container->get('params')['urls']['format']),
        ];

        $response = $this->renderer->render($request, $response, $data);

        return $response->withStatus(200);
    }
}
