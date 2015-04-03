<?hh // strict

namespace tuneefy;

// Vendor - This is written in PHP
use Symfony\Component\Yaml\Yaml,
    Slim\Slim,
    tuneefy\Utils\CustomViewHandler;

// Local classes
use tuneefy\MusicalEntity\MusicalEntity,
    tuneefy\PlatformEngine;

class Application
{

  const string APP_PARAMETERS_PATH = "config/parameters.yml";
  const string APP_PLATFORMS_PATH  = "config/platforms.yml";

  private Slim $slim_app;

  public function __construct()
  {
    $this->slim_app = new Slim();
  }

  public function run(): void
  {
    $this->slim_app->run();
  }

  public function configure(): mixed
  {

    try {
      $parameters = Yaml::parse(file_get_contents(self::APP_PARAMETERS_PATH));
      $platforms  = Yaml::parse(file_get_contents(self::APP_PLATFORMS_PATH));
    } catch (\Exception $e) {
      // TODO  : translate / template
      $this->slim_app->halt(500, "No config file found.");
    }

    // TODO : what do we do with these arrays now ?
     // - convert them to map
     // - store in object
     // - create platform instances
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
        Lookup
      */
      $this->slim_app->get('/lookup', function() {

        $permalink = $this->slim_app->request->params('q');

        // Permalink could be null, but we don't accept that
        if ($permalink === null || $permalink === ""){
          // TODO translation
          // TODO : output json/xml with error : missing parameter
          $this->error("Missing or empty parameter : q (permalink)");
        }

        $engine = new PlatformEngine();
        $result = $engine->lookup($permalink); // A MusicalEntity or null

        if ($result === null) {
          // We haven't found any match
          // TODO create result
          $this->slim_app->render(200, array(
            'msg' => "No match was found for this permalink"
          ));
        } else {
          $this->slim_app->render(200, array(
            'msg' => "A match was found"
            // DATA here : $result->toMap()
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
