<?php

namespace tuneefy\Controller;

use Interop\Container\ContainerInterface;
use RKA\ContentTypeRenderer\Renderer;
use tuneefy\DB\DatabaseHandler;
use tuneefy\Platform\Platform;
use tuneefy\Platform\PlatformException;
use tuneefy\PlatformEngine;

class ApiController
{
    protected $container;
    private $renderer;
    private $engine;

    const ERRORS = [
        'GENERAL_ERROR' => ['GENERAL_ERROR' => 'An error was encountered'],
        'BAD_PLATFORM_TYPE' => ['BAD_PLATFORM_TYPE' => 'This type of platform does not exist'],
        'BAD_PLATFORM' => ['BAD_PLATFORM' => 'This platform does not exist'],
        'BAD_MODE' => ['BAD_MODE' => 'This mode is not valid'],
        'MISSING_PERMALINK' => ['MISSING_PERMALINK' => 'Missing or empty parameter : q (permalink)'],
        'PERMALINK_UNKNOWN' => ['PERMALINK_UNKNOWN' => 'This permalink does not belong to any known platform'],
        'FETCH_PROBLEM' => ['FETCH_PROBLEM' => 'There was a problem while fetching data from the platform'],
        'FETCH_PROBLEMS' => ['FETCH_PROBLEMS' => 'There was a problem while fetching data from the platforms'],
        'NO_MATCH' => ['NO_MATCH' => 'No match was found for this search'],
        'NO_MATCH_PERMALINK' => ['NO_MATCH_PERMALINK' => 'No match was found for this permalink'],
        'MISSING_QUERY' => ['MISSING_QUERY' => 'Missing or empty parameter : q (query)'],
        'BAD_MUSICAL_TYPE' => ['BAD_MUSICAL_TYPE' => 'This musical type does not exist'],
        'NO_INTENT' => ['NO_INTENT' => 'Missing or empty parameter : intent'],
        'NO_OR_EXPIRED_INTENT' => ['NO_OR_EXPIRED_INTENT' => 'No intent with the requested uid'],
        'INVALID_INTENT_SIGNATURE' => ['INVALID_INTENT_SIGNATURE' => 'Data for this intent has been tampered with, the signature is not valid.'],
        'SERIALIZATION_ERROR' => ['SERIALIZATION_ERROR' => 'Stored object is not unserializable'],
        'NOT_CAPABLE_TRACKS' => ['NOT_CAPABLE_TRACKS' => 'This platform is not capable of searching tracks'],
        'NOT_CAPABLE_ALBUMS' => ['NOT_CAPABLE_ALBUMS' => 'This platform is not capable of searching albums'],

        'NOT_AUTHORIZED' => ['NOT_AUTHORIZED' => 'Not authorized, check the token'],
        'NOT_FOUND' => ['NOT_FOUND' => 'Not found'],
        'NOT_ALLOWED' => ['NOT_ALLOWED' => 'Method not allowed'],
    ];

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

    public function redirect($request, $response, $args)
    {
        $route = $this->container->get('router')->pathFor('api');

        return $response->withStatus(301)->withHeader('Location', $route);
    }

    public function getAllPlatforms($request, $response, $args)
    {
        $type = strtolower($request->getQueryParam('type'));

        $platforms = $this->engine->getAllPlatforms();

        if ('' != $type) {
            $platforms = array_values(array_filter($platforms, function ($e) use ($type) {
                return $e->getType() === $type;
            }));

            if (0 === count($platforms)) {
                $response->write('BAD_PLATFORM_TYPE');

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
            $response->write('BAD_PLATFORM');

            return $response->withStatus(404);
        }

        $response = $this->renderer->render($request, $response, $platform->toArray());

        return $response->withStatus(200);
    }

    public function lookup($request, $response, $args)
    {
        $this->engine->setCurrentToken($this->container['token']);

        $permalink = $request->getQueryParam('q');

        try {
            $real_mode = $this->engine->translateFlag('mode', $request->getQueryParam('mode'));
        } catch (\Exception $e) {
            $response->write($e->getMessage());

            return $response->withStatus(400);
        }

        // Permalink could be null, but we don't accept that
        if (null === $permalink || '' === $permalink) {
            $response->write('MISSING_PERMALINK');

            return $response->withStatus(400);
        }

        try {
            $result = $this->engine->lookup($permalink, $real_mode);
        } catch (PlatformException $e) {
            $result = false;
        }

        // From the try/catch up there
        if (false === $result) {
            $data = ['errors' => [self::ERRORS['FETCH_PROBLEM']]];
            // If we have a result
        } elseif (isset($result['result'])) {
            if ($result['result']->getMusicalEntity()) {
                $data = [
                    'result' => $result['result']->toArray(),
                ];
            } else {
                $data = [
                    'errors' => [self::ERRORS['NO_MATCH_PERMALINK']],
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
        $this->engine->setCurrentToken($this->container['token']);

        $query = $request->getQueryParam('q');
        $limit = $request->getQueryParam('limit') ?? Platform::LIMIT;

        try {
            $real_type = $this->engine->translateFlag('type', $args['type']);
            $real_mode = $this->engine->translateFlag('mode', $request->getQueryParam('mode'));
        } catch (\Exception $e) {
            $response->write($e->getMessage());

            return $response->withStatus(400);
        }

        if (null === $query || '' === $query) {
            $response->write('MISSING_QUERY');

            return $response->withStatus(400);
        }

        $platform = $this->engine->getPlatformByTag($args['platform_str']);
        if (null === $platform) {
            $response->write('BAD_PLATFORM');

            return $response->withStatus(400);
        }

        try {
            $result = $this->engine->search($platform, $real_type, $query, intval($limit), $real_mode);
        } catch (PlatformException $e) {
            $result = false;
        }

        // From the try/catch up there
        if (false === $result) {
            $data = ['errors' => [self::ERRORS['FETCH_PROBLEM']]];
            // If we have a result
        } elseif (isset($result['results'])) {
            if (count($result['results']) > 0) {
                $data = [
                    'results' => array_map(function ($e) { return $e->toArray(); }, $result['results']),
                ];
            } else {
                $data = ['errors' => [self::ERRORS['NO_MATCH']]];
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
        $this->engine->setCurrentToken($this->container['token']);

        $query = $request->getQueryParam('q');
        $limit = $request->getQueryParam('limit') ?? Platform::LIMIT;
        $include = strtolower($request->getQueryParam('include'));
        $aggressive = true && ($request->getQueryParam('aggressive') && 'true' == $request->getQueryParam('aggressive'));

        try {
            $real_type = $this->engine->translateFlag('type', strtolower($args['type']));
            $real_mode = $this->engine->translateFlag('mode', strtolower($request->getQueryParam('mode')));
        } catch (\Exception $e) {
            $response->write($e->getMessage());

            return $response->withStatus(400);
        }

        if (null === $query || '' === $query) {
            $response->write('MISSING_QUERY');

            return $response->withStatus(400);
        }

        $platforms = $this->engine->getPlatformsByTags(explode(',', $include));
        if (null === $include || '' === $include || null === $platforms) { // Silently fails if a name is invalid, that's ok
            $platforms = $this->engine->getAllPlatforms();
        }

        try {
            $result = $this->engine->aggregate($platforms, $real_type, $query, intval($limit), $real_mode, $aggressive);
        } catch (PlatformException $e) {
            $result = false;
        }

        // From the try/catch up there
        if (false === $result) {
            $data = ['errors' => [self::ERRORS['FETCH_PROBLEMS']]];
            // If we have a result
        } elseif (isset($result['results'])) {
            if (count($result['results']) > 0) {
                $data = [
                    'errors' => $result['errors'],
                    'results' => array_map(function ($e) { return $e->toArray(); }, $result['results']),
                ];
            } else {
                $data = ['errors' => [self::ERRORS['NO_MATCH']]];
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

        if (null === $intent || '' === $intent) {
            $response->write('NO_INTENT');

            return $response->withStatus(400);
        }

        $db = DatabaseHandler::getInstance(null);
        // Retrieve the intent
        try {
            list($type, $uid) = $db->fixItemWithIntent($intent);
        } catch (\Exception $e) {
            $response->write($e->getMessage());

            return $response->withStatus(400);
        }

        $data = [
            'uid' => $uid,
            'link' => $this->container->get('params')['urls']['front'].
                      str_replace(['{type}', '{uid}'], [$type, $uid], $this->container->get('params')['urls']['format']),
        ];

        $response = $this->renderer->render($request, $response, $data);

        return $response->withStatus(200);
    }
}
