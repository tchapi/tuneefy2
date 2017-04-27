<?php

namespace tuneefy;

use RKA\ContentTypeRenderer\Renderer;
use Slim\App;
use Slim\Views\Twig;
use Symfony\Component\Yaml\Yaml;
use tuneefy\DB\DatabaseHandler;
use tuneefy\Platform\Platform;
use tuneefy\Platform\PlatformException;
use tuneefy\Utils\CustomErrorHandler;
use tuneefy\Utils\CustomNotFoundHandler;
use tuneefy\Utils\Utils;

class Application
{
    const PATHS = [
        'parameters' => '/../../app/config/parameters.yml',
        'platforms' => '/../../app/config/platforms.yml',
        'templates' => '/../../app/templates',
        'cache' => '/../../var/cache',
    ];

    private $slimApp;
    private $engine;

    public function __construct()
    {
        $this->slimApp = new App();

        // Get container
        $container = $this->slimApp->getContainer();

        // Register component on container
        $container['view'] = function ($container) {
            return new Twig(self::getPath('templates'), [
                'cache' => self::getPath('cache'),
            ]);
        };

        // Custom 404 and 500 handlers
        // Override the default Not Found Handler
        $container['notFoundHandler'] = function ($container) {
            return new CustomNotFoundHandler();
        };
        // FIXME remove comments
        // $container['errorHandler'] = function ($container) {
        //     return new CustomErrorHandler();
        // };

        // $container['phpErrorHandler'] = function ($container) {
        //     return $container['errorHandler'];
        // };

        // JSON / XML renderer
        $this->renderer = new Renderer();

        // Application engine
        $this->engine = new PlatformEngine();
    }

    public static function getPath(string $which)
    {
        return dirname(__FILE__).self::PATHS[$which];
    }

    public function set(string $key, $value)
    {
        $container = $this->slimApp->getContainer();
        $container[$key] = $value;
    }

    public function run(bool $returnObject = false)
    {
        return $this->slimApp->run($returnObject);
    }

    public function configure()
    {
        // Fetch config files
        try {
            $platforms = Yaml::parse(file_get_contents(self::getPath('platforms')));
        } catch (\Exception $e) {
            // TODO  : translate / template : this will not happen in Slim's run loop, handle differently
            throw new \Exception('No config file found');
        }

        if ($platforms === null) {
            // TODO  : translate / template : this will not happen in Slim's run loop, handle differently
            throw new \Exception('Bad config files');
        }

        foreach ($platforms as $key => $platform) {
            $p = $this->engine->getPlatformByTag($key);

            if ($p === null) {
                continue;
            }

            $p->setEnables($platform['enable'])
              ->setCapabilities($platform['capabilities'])
              ->setCredentials($platform['key'], $platform['secret']);
        }
    }

    public function prepare()//: mixed
    {
        // Connect to DB
        try {
            $container = $this->slimApp->getContainer();
            $container['db'] = DatabaseHandler::getInstance();
            // TODO : instantiate DB once FIXME
        } catch (\Exception $e) {
            // TODO  : translate / template
            $this->slimApp->halt(500, 'Problem with database instantiation : '.$e->getMesage());
        }

        $engine = $this->engine;
        $renderer = $this->renderer;

        // Add middleware for checking Oauth when necessary (API)
        //$this->slimApp->add();

        // Binds the API, with a custom view handler at the end
        $this->slimApp->group('/api', function () use ($engine, $renderer) {
            /*
              Lookup (for a permalink)
            */
            $this->get('/lookup', function ($request, $response, $args) use ($engine, $renderer) {
                $permalink = $request->getQueryParam('q');
                $real_mode = $engine->translateFlag('mode', $request->getQueryParam('mode'));

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
                    $result = $engine->lookup($permalink, $real_mode);
                } catch (PlatformException $e) {
                    $data = ['msg' => 'There was a problem while fetching data from the platform'];
                }

                if ($result === []) {
                    $data = ['msg' => 'No match was found for this permalink'];
                } else if ($result !== null) {
                    $data = ['results' => $result->toArray()];
                }

                $response = $renderer->render($request, $response, $data);

                return $response->withStatus(200);
            });

            /*
              Search (on one platform only)
            */
            $this->get('/search/{type}/{platform_str}', function ($request, $response, $args) use ($engine, $renderer) {
                $query = $request->getQueryParam('q');
                $limit = $request->getQueryParam('limit');
                $real_type = $engine->translateFlag('type', $args['type']);
                $real_mode = $engine->translateFlag('mode', $request->getQueryParam('mode'));

                if ($query === null || $query === '') {
                    // TODO translation
                    $response->write('Missing or empty parameter : q (query)');

                    return $response->withStatus(400);
                }

                $platform = $engine->getPlatformByTag($args['platform_str']);
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
                    $result = $engine->search($platform, $real_type, $query, intval($limit), $real_mode);
                } catch (PlatformException $e) {
                    $data = ['msg' => 'There was a problem while fetching data from the platform'];
                }

                if ($result === []) {
                    // TODO translation
                    $data = ['msg' => 'No match was found for this search on this platform'];
                } else if ($result !== null) {
                    $data = ['results' => array_map(function ($e) { return $e->toArray(); }, $result)];
                }

                $response = $renderer->render($request, $response, $data);

                return $response->withStatus(200);
            });

            /*
              Aggregate (all platforms)
            */
            $this->get('/aggregate/{type}', function ($request, $response, $args) use ($engine, $renderer) {
                $query = $request->getQueryParam('q');
                $limit = $request->getQueryParam('limit');
                $include = $request->getQueryParam('include'); // Included platforms?
                $aggressive = true && ($request->getQueryParam('aggressive') && $request->getQueryParam('aggressive') == 'true'); // If aggressive, merge more (actual behaviour depends on the type)
                $real_type = $engine->translateFlag('type', $args['type']);
                $real_mode = $engine->translateFlag('mode', $request->getQueryParam('mode'));

                if ($query === null || $query === '') {
                    // TODO translation
                    $response->write('Missing or empty parameter : q (query)');

                    return $response->withStatus(400);
                }

                // TODO FIXME: it's a bit cumbersome, should refactor
                if ($include === null || $include === '') { // If empty, include all.
                    $platforms = $engine->getAllPlatforms();
                } else {
                    $platforms = $engine->getPlatformsByTags(explode(',', strtolower($include)));
                    if ($platforms === null) { // Silently fails if a name is invalid, that's ok
                        $platforms = $engine->getAllPlatforms();
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
                    $result = $engine->aggregate($real_type, $query, intval($limit), $real_mode, $aggressive, $platforms);
                } catch (PlatformException $e) {
                    $data = ['msg' => 'There was a problem while fetching data from the platforms'];
                }

                if ($result === null) {
                    // TODO translation
                    $data = ['msg' => 'No match was found for this search'];
                } else {
                    $data = ['results' => array_map(function ($e) { return $e->toArray(); }, $result)];
                }

                $response = $renderer->render($request, $response, $data);

                return $response->withStatus(200);
            });

            /*
              Share via the API
            */
            $this->get('/share/{intent}', function ($request, $response, $args) {
                $intent = $args['intent'];

                if ($intent === null || $intent === '') {
                    // TODO translation
                    $response->write('Missing or empty parameter : intent');

                    return $response->withStatus(400);
                }

                // Intent is a GUID
                $result = $engine->share($intent);

                if ($result === null) {
                    // TODO translation
                    $data = ['msg' => 'This intent is not correct or has expired'];
                } else {
                    $data = ['data' => $result];
                }

                $response = $renderer->render($request, $response, $data);

                return $response->withStatus(200);
            });
        })->add(function ($request, $response, $next) use ($renderer) {
            // Accept the 'format' modifier
            $request = $request->withHeader('Accept', 'application/json'); // default
            $format = $request->getParam('format');
            if ($format) {
                $mapping = [
                    'html' => 'text/html',
                    'xml' => 'application/xml',
                    'json' => 'application/json',
                ];
                if (isset($mapping[$format])) {
                    $request = $request->withHeader('Accept', $mapping[$format]);
                }
            }

            $response = $next($request, $response);

            // If we have an error (Bad request), handle it
            if (400 === $response->getStatusCode()) {
                $response = $renderer->render($request, $response, ['error' => $response->getBody()->__toString()]);
            }

            return $response;
        });

        /*
          The sharing page
        */
        $this->slimApp->get('/{type}/{uid}', function ($request, $response, $args) {
            // Translate into good id
            $id = Utils::fromUId($args['uid']);
            $item = $this->db->getItem($id);

            if (!is_null($item)) {
                return $this->view->render($response, 'item.html.twig', ['item' => $item->toArray()]);
            } else {
                return $response->withStatus(404);
            }
        });

        /*
          Listen to a musical entity => goes to the platform link
        */
        $this->slimApp->get('/{type}/{uid}/listen/{platform}', function ($request, $response, $args) {
            // TODO

            // Eventually, redirect to platform
            return $response->withStatus(303)->withHeader('Location', 'http://the/link/on/the/platform'); // "See Other"
        });

        /*
          The home page
        */
        $this->slimApp->get('/', function ($request, $response, $args) use ($engine) {
            // TODO
            return $this->view->render($response, 'home.html.twig', ['platforms' => $engine->getAllPlatforms()]);
        });

        /*
          The about page
        */
        $this->slimApp->get('/about', function ($request, $response, $args) {
            // TODO
            return $this->view->render($response, 'about.html.twig');
        });

        /*
          The trends page
        */
        $this->slimApp->get('/trends', function ($request, $response, $args) {
            // TODO
            return $this->view->render($response, 'trends.html.twig');
        });

        return $this;
    }
}
