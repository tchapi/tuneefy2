<?hh // strict

namespace tuneefy;

// Vendor - This is written in PHP
use Symfony\Component\Yaml\Yaml,
    Slim\Slim,
    tuneefy\Utils\CustomViewHandler;

// Local classes
use tuneefy\PlatformEngine;

class Application
{

  const string APP_PARAMETERS_PATH = "../app/config/parameters.yml";
  const string APP_PLATFORMS_PATH  = "../app/config/platforms.yml";

  private Slim $slim_app;
  private PlatformEngine $engine;

  public function __construct()
  {
    $this->slim_app = new Slim();
    $this->engine = new PlatformEngine();
  }

  public function run(): void
  {
    $this->slim_app->run();
  }

  public function configure(): mixed
  {

    $platforms = null; $parameters = null;
    try {
      $parameters = Yaml::parse(file_get_contents(self::APP_PARAMETERS_PATH));
      $platforms  = Yaml::parse(file_get_contents(self::APP_PLATFORMS_PATH));
    } catch (\Exception $e) {
      // TODO  : translate / template
      $this->slim_app->halt(500, "No config file found");
    }

    if ($platforms === null || $platforms['platforms'] === null || $parameters === null) {
      // TODO  : translate / template
      $this->slim_app->halt(500, "Bad config files");
      return; // This is to make the HH TypeChecker happy
    }

    foreach ($platforms['platforms'] as $key => $platform)
    {
      $p = $this->engine->getPlatformByTag($key);
      if ($p === null) { continue; }

      $p->setEnables(new Map($platform['enable']))
        ->setCapabilities(new Map($platform['capabilities']))
        ->setCredentials($platform['key'],$platform['secret']);
    }

  }

  public function prepare(): mixed
  {

    // Add middleware for checking Oauth when necessary (API)
    //$this->slim_app->add();

    // Binds the API, with a custom view handler
    $this->slim_app->group('/api', function()
    {
        // This is a custom middleware style view
        $app = Slim::getInstance();
        $app->view(new CustomViewHandler());

    }, function () {

      /*
        Lookup (for a permalink)
      */
      $this->slim_app->get('/lookup', function() {

        $permalink = $this->slim_app->request->params('q');

        // Permalink could be null, but we don't accept that
        if ($permalink === null || $permalink === ""){
          // TODO translation
          $this->error("Missing or empty parameter : q (permalink)");
        }

        $result = $this->engine->lookup($permalink); // ?Map<string,mixed>

        if ($result === null) {
          // TODO create result
          $this->slim_app->render(200, array(
            'msg' => "No match was found for this permalink"
          ));
        } else {
          $this->slim_app->render(200, array(
            'data' => $result
          ));
        }
        
      });

      /*
        Search (on one platform only)
      */
      $this->slim_app->get('/search/:platform', function(string $platform) {
        // TODO
      });

      /*
        Aggregate (all platforms)
      */
      $this->slim_app->get('/aggregate', function() {
        // TODO
      });

    });

    /*
      The sharing page
    */
    $this->slim_app->get('/:type/:id', function(string $type, int $id) {
      // TODO
    });

    /*
      Listen to a musical entity => goes to the platform link
    */
    $this->slim_app->get('/:type/:id/listen/:platform', function(string $type, int $id, string $platform) {
      // TODO
    });

    /*
      The home page
    */
    $this->slim_app->get('/', function() {
      // TODO
    });

    /*
      The about page
    */
    $this->slim_app->get('/about', function() {
      // TODO
    });

    /*
      The trends page
    */
    $this->slim_app->get('/trends', function() {
      // TODO
    });

    return $this;
    
  }

  /*
    Helper to end an API request on an error
  */
  public function error(string $msg): mixed {
    $this->slim_app->render(200, array(
        'error' => true,
        'msg' => $msg,
    ));
  }

}
