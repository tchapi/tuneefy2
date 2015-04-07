<?hh // partial

  /*
  
    Partial and not strict type checking here since we are 
    using classes written in PHP, not in Hack :

    http://docs.hhvm.com/manual/en/hack.modes.strict.php

  */

namespace tuneefy;

// Vendor - This is written in PHP
use Symfony\Component\Yaml\Yaml,
    Slim\Slim, 
    Slim\Views\Twig;

// Local classes
use tuneefy\PlatformEngine,
    tuneefy\Utils\CustomViewHandler;

class Application
{

  const string APP_PARAMETERS_PATH = "../app/config/parameters.yml";
  const string APP_PLATFORMS_PATH  = "../app/config/platforms.yml";

  private Slim $slim_app;
  private PlatformEngine $engine;

  public function __construct()
  {
    $this->slim_app = new Slim(array(
        'view' => new Twig(),
        'templates.path' => dirname(__FILE__) . '/../../app/templates'
    ));
    $this->engine = new PlatformEngine();
  }

  public function run(): void
  {
    $this->slim_app->run();
  }

  public function configure(): mixed
  {

    // Configure Twig
    $view = $this->slim_app->view();
    $view->parserOptions = array(
        // 'cache' => dirname(__FILE__) . '/../../app/cache' // For Production
    );

    // Fetch config files
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
          $this->slim_app->render(200, array( 'msg' => "No match was found for this permalink" ));
        } else {
          $this->slim_app->render(200, array( 'data' => $result ));
        }
        
      });

      /*
        Search (on one platform only)
      */
      $this->slim_app->get('/search/:type/:platform_str', function(string $type, string $platform_str) {

        $query = $this->slim_app->request->params('q');
        $limit = $this->slim_app->request->params('limit');

        if ($query === null || $query === ""){
          // TODO translation
          $this->error("Missing or empty parameter : q (query)");
        }

        $platform = $this->engine->getPlatformByTag($platform_str);
        if ($platform === null){
          // TODO translation
          $this->error("Invalid parameter : platform '$platform_str' does not exist");
          return; // This is to make the HH TypeChecker happy
        }

        $result = $this->engine->search($platform, $type, $query, intval($limit)); // ?Vector<Map<string,mixed>>

        if ($result === null) {
          $this->slim_app->render(200, array( 'msg' => "No match was found for this search on this platform" ));
        } else {
          $this->slim_app->render(200, array( 'data' => $result ));
        }

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
    $this->slim_app->get('/:type/:id', function(string $type, string $id) {
      // TODO
      $this->slim_app->render('item.html.twig', array('id' => $id, 'type' => $type));
    });

    /*
      Listen to a musical entity => goes to the platform link
    */
    $this->slim_app->get('/:type/:id/listen/:platform', function(string $type, string $id, string $platform) {
      // TODO

      // Eventually, redirect to platform
      $this->slim_app->redirect('http://the/link/on/the/platform', 303); // "See Other"
    });

    /*
      The home page
    */
    $this->slim_app->get('/', function() {
      // TODO
      $this->slim_app->render('home.html.twig');
    });

    /*
      The about page
    */
    $this->slim_app->get('/about', function() {
      // TODO
      $this->slim_app->render('about.html.twig');
    });

    /*
      The trends page
    */
    $this->slim_app->get('/trends', function() {
      // TODO
      $this->slim_app->render('trends.html.twig');
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
