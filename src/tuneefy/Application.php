<?php

namespace tuneefy;

use Slim\Slim;
use Slim\Views\Twig;
use Symfony\Component\Yaml\Yaml;
use tuneefy\DB\DatabaseHandler;
use tuneefy\Platform\Platform;
use tuneefy\Utils\CustomViewHandler;
use tuneefy\Utils\Utils;

class Application
{
    const APP_PARAMETERS_PATH = '../app/config/parameters.yml';
    const APP_PLATFORMS_PATH = '../app/config/platforms.yml';

    private $slim_app;
    private $engine;

    public function __construct()
    {
        $this->slim_app = new Slim([
        'view' => new Twig(),
        'templates.path' => dirname(__FILE__).'/../../app/templates',
    ]);
        $this->engine = new PlatformEngine();
    }

    public function run()//: void
    {
        $this->slim_app->run();
    }

    public function configure()//: mixed
    {
        // Configure Twig
    $view = $this->slim_app->view();
        $view->parserOptions = [
        // 'cache' => dirname(__FILE__) . '/../../app/cache' // For Production
    ];

    // Fetch config files
    try {
        $platforms = Yaml::parse(file_get_contents(self::APP_PLATFORMS_PATH));
        $platforms = $platforms['platforms'];
    } catch (\Exception $e) {
        // TODO  : translate / template
        $this->slim_app->halt(500, 'No config file found');
    }

        if ($platforms === null) {
            // TODO  : translate / template
            $this->slim_app->halt(500, 'Bad config files');
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
        $this->slim_app->container->singleton('db', function () {
            return DatabaseHandler::getInstance();
        });
    } catch (\Exception $e) {
        $this->slim_app->halt(500, 'No DB Connection');
    }

    // Add middleware for checking Oauth when necessary (API)
    //$this->slim_app->add();

    // Binds the API, with a custom view handler
    $this->slim_app->group('/api', function () {
        // This is a custom middleware style view
        $app = Slim::getInstance();
        $app->view(new CustomViewHandler());
    }, function () {
        /*
        Lookup (for a permalink)
      */
      $this->slim_app->get('/lookup', function () {
          $permalink = $this->slim_app->request->params('q');
          $real_mode = $this->engine->translateFlag('mode', $this->slim_app->request->params('mode'));

        // Permalink could be null, but we don't accept that
        if ($permalink === null || $permalink === '') {
            // TODO translation
          $this->error('Missing or empty parameter : q (permalink)');
        }

          if ($real_mode === null) {
              $real_mode = Platform::MODE_LAZY;
          }

          $result = $this->engine->lookup($permalink, $real_mode);

          if ($result === null) {
              $this->slim_app->render(200, ['msg' => 'No match was found for this permalink']);
          } else {
              $this->slim_app->render(200, ['data' => $result->toArray()]);
          }
      });

      /*
        Search (on one platform only)
      */
      $this->slim_app->get('/search/:type/:platform_str', function (string $type, string $platform_str) {
          $query = $this->slim_app->request->params('q');
          $limit = $this->slim_app->request->params('limit');
          $real_type = $this->engine->translateFlag('type', $type);
          $real_mode = $this->engine->translateFlag('mode', $this->slim_app->request->params('mode'));

          if ($query === null || $query === '') {
              // TODO translation
          $this->error('Missing or empty parameter : q (query)');
          }

          $platform = $this->engine->getPlatformByTag($platform_str);
          if ($platform === null) {
              // TODO translation
          $this->error("Invalid parameter : platform '$platform_str' does not exist");
          }

          if ($real_type === null) {
              $this->error("Invalid parameter : type '$type' does not exist");
          }

          if ($real_mode === null) {
              $real_mode = Platform::MODE_LAZY;
          }

          $result = $this->engine->search($platform, $real_type, $query, intval($limit), $real_mode);

          if ($result === null) {
              $this->slim_app->render(200, ['msg' => 'No match was found for this search on this platform']);
          } else {
              $this->slim_app->render(200, ['data' => $result->map(function ($e) { return $e->toArray(); })]);
          }
      });

      /*
        Aggregate (all platforms)
      */
      $this->slim_app->get('/aggregate/:type', function (string $type) {
          // TODO
        $query = $this->slim_app->request->params('q');
          $limit = $this->slim_app->request->params('limit');
          $include = $this->slim_app->request->params('include'); // Included platforms?
        $aggressive = ($this->slim_app->request->params('aggressive') && $this->slim_app->request->params('aggressive') == 'true') ? true : false; // If aggressive, merge more (actual behaviour depends on the type)
        $real_type = $this->engine->translateFlag('type', $type);
          $real_mode = $this->engine->translateFlag('mode', $this->slim_app->request->params('mode'));

          if ($query === null || $query === '') {
              // TODO translation
          $this->error('Missing or empty parameter : q (query)');
          }

        // TODO : it's a bit cumbersome, should refactor
        if ($include === null || $include === '') { // If empty, include all.
          $platforms = $this->engine->getAllPlatforms();
        } else {
            $platforms = $this->engine->getPlatformsByTags(explode(',', strtolower($include)));
            if ($platforms === null) { // Silently fails if a name is invalid, that's ok
            $platforms = $this->engine->getAllPlatforms();
            }
        }

          if ($real_type === null) {
              $this->error("Invalid parameter : type '$type' does not exist");
          }

          if ($real_mode === null) {
              $real_mode = Platform::MODE_LAZY;
          }

          $result = $this->engine->aggregate($real_type, $query, intval($limit), $real_mode, $aggressive, $platforms);
        // For TEST purposes : $result = $this->engine->aggregateSync($type, $query, intval($limit), $platforms);

        if ($result === null) {
            $this->slim_app->render(200, ['msg' => 'No match was found for this search']);
        } else {
            $this->slim_app->render(200, ['data' => $result->map(function ($e) { return $e->toArray(); })]);
        }
      });

      /*
        Share via the API
      */
      $this->slim_app->get('/share/:intent', function (string $intent) {
          // Intent is a GUID
        $result = $this->engine->share($intent);

          if ($result === null) {
              $this->slim_app->render(200, ['msg' => 'This intent is not correct or has expired']);
          } else {
              $this->slim_app->render(200, ['data' => $result]);
          }
      });
    });

    /*
      The sharing page
    */
    $this->slim_app->get('/:type/:uid', function (string $type, string $uid) {
        // Translate into good id
      $id = Utils::fromUId($uid);
        $item = $this->slim_app->db->getItem($id)->getWaitHandle()->join();

        if (!is_null($item)) {
            $this->slim_app->render('item.html.twig', ['item' => $item->toArray()]);
        } else {
            $this->notFound();
        }
    });

    /*
      Listen to a musical entity => goes to the platform link
    */
    $this->slim_app->get('/:type/:uid/listen/:platform', function (string $type, string $uid, string $platform) {
        // TODO

      // Eventually, redirect to platform
      $this->slim_app->redirect('http://the/link/on/the/platform', 303); // "See Other"
    });

    /*
      The home page
    */
    $this->slim_app->get('/', function () {
        // TODO
      $this->slim_app->render('home.html.twig', ['platforms' => $this->engine->getAllPlatforms()]);
    });

    /*
      The about page
    */
    $this->slim_app->get('/about', function () {
        // TODO
      $this->slim_app->render('about.html.twig');
    });

    /*
      The trends page
    */
    $this->slim_app->get('/trends', function () {
        // TODO
      $this->slim_app->render('trends.html.twig');
    });

    // Not found
    $this->slim_app->notFound(function () {
        $this->slim_app->render('404.html.twig');
    });

    // 50X
    $this->slim_app->error(function (\Exception $e) {
        $this->slim_app->render('error.html.twig', ['message' => $e->getMessage()]);
    });

        return $this;
    }

  /*
    Helper to end an API request on an error
  */
  public function error(string $msg)//: mixed
  {
      $this->slim_app->render(200, [
        'error' => true,
        'msg' => $msg,
    ]);
  }
}
