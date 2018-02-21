<?php

namespace tuneefy;

use Chadicus\Slim\OAuth2\Middleware;
use Chadicus\Slim\OAuth2\Routes;
use OAuth2;
use Slim\App;
use Slim\Views\Twig;
use Slim\Views\TwigExtension;
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Symfony\Component\Translation\Loader\YamlFileLoader;
use Symfony\Component\Translation\Translator;
use Symfony\Component\Yaml\Yaml;
use tuneefy\Controller\ApiController;
use tuneefy\Controller\BackendController;
use tuneefy\Controller\FrontendController;
use tuneefy\DB\DatabaseHandler;
use tuneefy\Platform\Platform;
use tuneefy\Utils\ApiBypassMiddleware;
use tuneefy\Utils\ContentTypeMiddleware;
use tuneefy\Utils\CustomErrorHandler;
use tuneefy\Utils\CustomNotFoundHandler;
use tuneefy\Utils\Utils;

class Application
{
    const COOKIE_LANG = 'tuneefyLocale';

    const PATHS = [
        'parameters' => '/../../app/config/parameters.yml',
        'platforms' => '/../../app/config/platforms.yml',
        'templates' => '/../../app/templates',
        'langs' => '/../../app/lang',
        'cache' => '/../../var/cache',
    ];

    private $slimApp;
    private $engine;
    private $params;
    private $container;
    private $db;

    public function __construct()
    {
        $this->slimApp = new App();

        // Get container
        $container = $this->slimApp->getContainer();

        // Register component on container
        $container['view'] = function ($container) {
            $view = new Twig(self::getPath('templates'), [
                //'cache' => self::getPath('cache'),
            ]);

            $basePath = rtrim(str_ireplace('index.php', '', $container['request']->getUri()->getBasePath()), '/');
            $view->addExtension(new TwigExtension($container['router'], $basePath));

            // First param is the "default language" to use.
            if (isset($_COOKIE[self::COOKIE_LANG])) {
                $locale = $_COOKIE[self::COOKIE_LANG];
            } elseif (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
                $prefLocales = array_reduce(explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE']), function ($res, $el) {
                    list($l, $q) = array_merge(explode(';q=', $el), [1]);
                    $res[$l] = (float) $q;

                    return $res;
                }, []);
                asort($prefLocales);

                $locale = array_reduce(array_keys($prefLocales), function ($default, $prefLocale) {
                    return in_array($prefLocale, ['en_US', 'fr_FR']) ? $prefLocale : $default;
                }, 'en_US');
            } else {
                $locale = 'en_US';
            }

            $translator = new Translator($locale);
            $view->getEnvironment()->addGlobal('locale', $locale);

            // If we want to add specific data to the context, it's here
            $view->getEnvironment()->addGlobal('context', [
                'slack' => isset($_SERVER['HTTP_USER_AGENT']) ? (0 !== preg_match('/Slackbot/', $_SERVER['HTTP_USER_AGENT'])) : false,  // ** Detect Slack
            ]);

            // Set a fallback language incase you don't have a translation in the default language
            $translator->setFallbackLocales(['en_US']);
            $translator->addLoader('yaml', new YamlFileLoader());

            // Add language files here
            $translator->addResource('yaml', self::getPath('langs').'/fr_FR.yml', 'fr_FR'); // French
            $translator->addResource('yaml', self::getPath('langs').'/en_US.yml', 'en_US'); // English

            // add translator functions to Twig
            $view->addExtension(new TranslationExtension($translator));

            return $view;
        };

        // $container['errorHandler'] = function ($container) {
        //     return new CustomErrorHandler($container->get('view'), 500, 'GENERAL_ERROR');
        // };

        // $container['phpErrorHandler'] = function ($container) {
        //     return $container['errorHandler'];
        // };

        // Application engine
        $this->engine = new PlatformEngine();
    }

    public static function getPath(string $which)
    {
        return dirname(__FILE__).self::PATHS[$which];
    }

    public function getEngine()
    {
        return $this->engine;
    }

    public function run()
    {
        return $this->slimApp->run();
    }

    public function configure()
    {
        // Fetch config files
        try {
            $platforms = Yaml::parse(file_get_contents(self::getPath('platforms')));
            $this->params = Yaml::parse(file_get_contents(self::getPath('parameters')));
        } catch (\Exception $e) {
            // TODO  : translate / template : this will not happen in Slim's run loop, handle differently
            throw new \Exception('No config files found: '.$e->getMessage());
        }

        if (null === $platforms || null === $this->params) {
            // TODO  : translate / template : this will not happen in Slim's run loop, handle differently
            throw new \Exception('Bad config files');
        }

        // Sets the base for the urls
        Utils::setBase($this->params['urls']['base']);

        foreach ($platforms as $key => $platform) {
            $p = $this->engine->getPlatformByTag($key);

            if (null === $p) {
                continue;
            }

            $p->setEnables($platform['enable'])
              ->setCapabilities($platform['capabilities'])
              ->setDefault($platform['default'])
              ->setCredentials($platform['key'], $platform['secret']);
        }

        // Connect to DB
        try {
            $this->db = new DatabaseHandler($this->params);
        } catch (\Exception $e) {
            // TODO  : translate / template
            throw new \Exception('Problem with database instantiation : '.$e->getMessage());
        }

        $this->container = $this->slimApp->getContainer();
        $this->container['params'] = $this->params;

        /* Add session (for bypassing OAuth) */
        $this->slimApp->add(new \Slim\Middleware\Session([
          'name' => 'TUNEEFY_SESSION',
          'autorefresh' => true,
          'secure' => false,
          'domain' => $this->params['website']['cookie_domain'],
          'lifetime' => '5 minutes',
        ]));
        ini_set('session.gc_probability', 0); // This is because the Session constructor has calls to ini_set
                                              // and PHP doesn't have the rights to gc the session folder
    }

    public function setupV2ApiRoutes()
    {
        // Custom 404, 405 and 500 handlers
        // Override the default handlers
        $this->container['notFoundHandler'] = function ($container) {
            return new CustomNotFoundHandler($container->get('view'), true, 404, 'NOT_FOUND');
        };
        $this->container['notAllowedHandler'] = function ($container) {
            return new CustomNotFoundHandler($container->get('view'), true, 405, 'NOT_ALLOWED');
        };

        /* Documentation for the API, has to go first */
        $this->slimApp->get('/', FrontendController::class.':api')->setName('api');

        /* The API group, behind an (optional) OAuth2 Server */
        $api = $this->slimApp->group('/v2', function () {
            $this->get('/', ApiController::class.':redirect');
            $this->get('/platforms', ApiController::class.':getAllPlatforms');
            $this->get('/platform/{tag}', ApiController::class.':getPlatform');
            $this->get('/lookup', ApiController::class.':lookup');
            $this->get('/search/{type}/{platform_str}', ApiController::class.':search');
            $this->get('/aggregate/{type}', ApiController::class.':aggregate');
            $this->get('/share/{intent}', ApiController::class.':share');
        });

        if ($this->params['api']['use_oauth'] === true) {
            /* Set up storage (tokens, credentials) for OAuth2 server */
            $storage = new OAuth2\Storage\Pdo($this->db->getConnection());

            /* Create the oauth2 server */
            $this->oauth2Server = new OAuth2\Server(
                $storage,
                ['access_lifetime' => $this->params['api']['access_lifetime']],
                [new OAuth2\GrantType\ClientCredentials($storage)]
            );

            /* OAuth2 Middleware for the API */
            $api->add(new Middleware\Authorization($this->oauth2Server, $this->container));
            $api->add(new ApiBypassMiddleware($this->params['api']));

            /* The token route for OAuth */
            $this->slimApp->post('/v2/auth'.Routes\Token::ROUTE, new Routes\Token($this->oauth2Server))->setName('token');
        } else {
            $this->container['token'] = null;
        }

        $this->slimApp->add(new ContentTypeMiddleware($this->container));
    }

    public function setupWebsiteRoutes()
    {
        // Custom 404, 405 and 500 handlers
        // Override the default handlers
        $this->container['notFoundHandler'] = function ($container) {
            return new CustomNotFoundHandler($container->get('view'), false, 404, 'NOT_FOUND');
        };
        $this->container['notAllowedHandler'] = function ($container) {
            return new CustomNotFoundHandler($container->get('view'), false, 405, 'NOT_ALLOWED');
        };

        /* The display/show page for a musical entity */
        $this->slimApp->get($this->params['urls']['format'], FrontendController::class.':show')->setName('show');
        $this->slimApp->get('/a/{uid}', FrontendController::class.':show')->setName('legacy_show_album');
        $this->slimApp->get('/t/{uid}', FrontendController::class.':show')->setName('legacy_show_track');

        /* Listen to a musical entity => goes to the platform link */
        $this->slimApp->get($this->params['urls']['format'].'/listen/{platform}[/{i:[0-9]+}]', FrontendController::class.':listen')->setName('listen');
        $this->slimApp->get('/listen/{platform}', FrontendController::class.':listenDirect')->setName('listen_direct');

        /* The other frontend routes */
        $this->slimApp->get('/', FrontendController::class.':home')->setName('home');
        $this->slimApp->get('/about', FrontendController::class.':about')->setName('about');
        $this->slimApp->get('/trends', FrontendController::class.':trends')->setName('trends');
        $this->slimApp->post('/mail', FrontendController::class.':mail')->setName('mail');

        /* The backend routes */
        $admin = $this->slimApp->group('/admin', function () {
            $this->get('/migrate', BackendController::class.':migrate')->setName('admin_migrate');
            $this->get('/dashboard', BackendController::class.':dashboard')->setName('admin_dashboard');
            $this->get('/api/clients', BackendController::class.':clients')->setName('admin_clients');
            $this->map(['GET', 'POST'], '/api/clients/new', BackendController::class.':createClient')->setName('admin_new_client');
        });

        /* Middlewares should be in a certain order, the authentication must be added last */
        $this->slimApp->add(new \Slim\Middleware\HttpBasicAuthentication([
            'path' => ['/admin'],
            'realm' => 'Protected access',
            'secure' => $this->params['admin_secure'],
            'users' => $this->params['admin_users'],
        ]));
    }
}
