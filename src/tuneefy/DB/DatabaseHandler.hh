<?hh // strict

namespace tuneefy\DB;

use Symfony\Component\Yaml\Yaml;

use tuneefy\Application,
    tuneefy\Platform\PlatformResult;

class DatabaseHandler
{

  /**
   * The singleton instance of the class.
   */
  protected static ?DatabaseHandler $instance = null;

  private array<string,mixed> $parameters = array();
  private \AsyncMysqlConnection $connection;

  /**
   * Protected constructor to ensure there are no instantiations.
   */
  protected function __construct()
  { 

    try {
      $params = Yaml::parse(file_get_contents(Application::APP_PARAMETERS_PATH));
    } catch (\Exception $e) {
      // TODO  : translate / template
      $this->slim_app->halt(500, "No config file found");
    }

    if ($params['database'] === null) {
      // TODO  : translate / template
      $this->slim_app->halt(500, "No DB parameters");
    }

    $this->parameters = $params['database'];
    $this->connect()->getWaitHandle()->join();
  }

  private async function connect(): Awaitable<\AsyncMysqlConnection>
  {

    $this->connection = await \AsyncMysqlClient::connect(
      (string) $this->parameters['server'],
      (int) $this->parameters['port'],
      (string) $this->parameters['name'],
      (string) $this->parameters['user'],
      (string) $this->parameters['password']
    );

    return $this->connection;

  }

  /**
   * Retrieves the singleton instance.
   */
  public static function getInstance(): DatabaseHandler
  {
      if (self::$instance === null) {
          self::$instance = new self();
      }
      return self::$instance;
  }

  public async function query(): Awaitable<void>
  {
    $res = await $this->connection->queryf("SELECT * FROM %T WHERE foo = %s", 'junk', 'herp');
    var_dump($res->vectorRowsTyped());
  }

  public function addIntent(string $uid, PlatformResult $object): this
  {

    return $this;
  }

}
