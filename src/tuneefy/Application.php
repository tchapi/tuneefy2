<?php

namespace tuneefy;

use Chadicus\Slim\OAuth2\Middleware;
use Chadicus\Slim\OAuth2\Routes;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use OAuth2;
use Slim\Exception\HttpNotFoundException;
use Slim\Factory\AppFactory;
use Slim\Routing\RouteCollectorProxy;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Symfony\Component\Translation\Loader\YamlFileLoader;
use Symfony\Component\Translation\Translator;
use Symfony\Component\Yaml\Yaml;
use tuneefy\Controller\ApiController;
use tuneefy\Controller\BackendController;
use tuneefy\Controller\FrontendController;
use tuneefy\DB\DatabaseHandler;
use tuneefy\Platform\Platform;
use tuneefy\Utils\ApiActiveMiddleware;
use tuneefy\Utils\ApiBypassMiddleware;
use tuneefy\Utils\ApiStatsMiddleware;
use tuneefy\Utils\ContentTypeMiddleware;
use tuneefy\Utils\CustomErrorHandler;
use tuneefy\Utils\CustomNotFoundHandler;
use tuneefy\Utils\Utils;

class Application
{
    public const COOKIE_LANG = 'tuneefyLocale';

    public const PATHS = [
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
    private $twig;

    public function __construct()
    {
        $container = new \DI\Container();

        AppFactory::setContainer($container);
        $this->slimApp = AppFactory::create();

        $this->twig = Twig::create(self::getPath('templates'), [
          'cache' => false,
          //'cache' => self::getPath('cache'),
        ]);

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
        $this->twig->getEnvironment()->addGlobal('locale', $locale);

        // If we want to add specific data to the context, it's here
        $this->twig->getEnvironment()->addGlobal('context', [
            // ** Detect Slack
            'slack' => isset($_SERVER['HTTP_USER_AGENT']) ? (0 !== preg_match('/Slackbot/', $_SERVER['HTTP_USER_AGENT'])) : false,
        ]);

        // Set a fallback language in case you don't have a translation in the default language
        $translator->setFallbackLocales(['en_US']);
        $translator->addLoader('yaml', new YamlFileLoader());

        // Add language files here
        $translator->addResource('yaml', self::getPath('langs').'/fr_FR.yml', 'fr_FR'); // French
        $translator->addResource('yaml', self::getPath('langs').'/en_US.yml', 'en_US'); // English

        // add translator functions to Twig
        $this->twig->addExtension(new TranslationExtension($translator));

        $this->slimApp->add(TwigMiddleware::create($this->slimApp, $this->twig));

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
        $this->container->set('params', $this->params);

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

    private function addErrorMiddlewares(bool $isApiRoute)
    {
        $logger = new Logger('tuneefy');
        $streamHandler = new StreamHandler(__DIR__.'/../../var/logs/main.log', 100);
        $logger->pushHandler($streamHandler);

        $errorMiddleware = $this->slimApp->addErrorMiddleware(true, true, true, $logger);
        $errorMiddleware->setErrorHandler(HttpNotFoundException::class, new CustomNotFoundHandler($isApiRoute));
        $errorMiddleware->setErrorHandler(HttpMethodNotAllowedException::class, new CustomNotFoundHandler($isApiRoute));
        $errorMiddleware->setDefaultErrorHandler(new CustomErrorHandler($isApiRoute, $this->twig, $logger));
    }

    public function setupV2ApiRoutes()
    {
        /* Documentation for the API, has to go first */
        $this->slimApp->get('/', FrontendController::class.':api')->setName('api');

        // Allow CORS requests (OPTIONS)
        $this->slimApp->options('{routes:.+}', function ($request, $response, $args) {
            return $response;
        });

        /* The API group, behind an (optional) OAuth2 Server */
        $api = $this->slimApp->group('/v2', function (RouteCollectorProxy $app) {
            $app->get('/', ApiController::class.':redirect');
            $app->get('/platforms', ApiController::class.':getAllPlatforms');
            $app->get('/platform/{tag}', ApiController::class.':getPlatform');
            $app->get('/lookup', ApiController::class.':lookup');
            $app->get('/search/{type}/{platform_str}', ApiController::class.':search');
            $app->get('/aggregate/{type}', ApiController::class.':aggregate');
            $app->get('/share/{intent}', ApiController::class.':share');
        });

        if (true === $this->params['api']['use_oauth']) {
            /* Set up storage (tokens, credentials) for OAuth2 server */
            $storage = new OAuth2\Storage\Pdo($this->db->getConnection());

            /* Create the oauth2 server */
            $this->oauth2Server = new OAuth2\Server(
                $storage,
                ['access_lifetime' => $this->params['api']['access_lifetime']],
                [new OAuth2\GrantType\ClientCredentials($storage)]
            );

            /* OAuth2 Middleware for the API */
            $api->add(new ApiStatsMiddleware($this->container));
            $api->add(new ApiActiveMiddleware($this->container));
            $api->add(new Middleware\Authorization($this->oauth2Server));
            $api->add(new ApiBypassMiddleware($this->params['api']));

            /* The token route for OAuth */
            $this->slimApp->post('/v2/auth'.Routes\Token::ROUTE, new Routes\Token($this->oauth2Server))->setName('token');
        } else {
            $this->container->set('token', null);
        }

        $this->slimApp->add(new ContentTypeMiddleware($this->container));

        $this->addErrorMiddlewares(true);
    }

    public function setupWebsiteRoutes()
    {
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
        $this->slimApp->get('/rate-limiting', FrontendController::class.':apiRateLimiting');

        /* The backend routes */
        $admin = $this->slimApp->group('/admin', function (RouteCollectorProxy $app) {
            $app->get('/migrate', BackendController::class.':migrate')->setName('admin_migrate');
            $app->get('/dashboard', BackendController::class.':dashboard')->setName('admin_dashboard');
            $app->get('/api/clients', BackendController::class.':clients')->setName('admin_clients');
            $app->map(['GET', 'POST'], '/api/clients/new', BackendController::class.':createClient')->setName('admin_new_client');
        });

        /* Middlewares should be in a certain order, the authentication must be added last */
        $this->slimApp->add(new \Tuupola\Middleware\HttpBasicAuthentication([
            'path' => ['/admin'],
            'realm' => 'Protected access',
            'secure' => $this->params['admin_secure'],
            'users' => $this->params['admin_users'],
        ]));

        $this->addErrorMiddlewares(false);
    }
}
