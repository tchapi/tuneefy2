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

  /*
    Table and columns names
  */
 const string TABLE_ITEMS = "items";
   const string TABLE_ITEMS_COL_ID = "id";
   const string TABLE_ITEMS_COL_TYPE = "type";
   const string TABLE_ITEMS_COL_CREATED_AT = "created_at";

 const string TABLE_INTENTS = "intents";
   const string TABLE_INTENTS_COL_UID = "uid";
   const string TABLE_INTENTS_COL_OBJECT = "object";
   const string TABLE_INTENTS_COL_CREATED_AT = "created_at";

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

  public async function getItem(int $item_id): Awaitable<void>
  {
    $res = await $this->connection->queryf("SELECT * FROM %T WHERE %C = %d", 
      self::TABLE_ITEMS,
      self::TABLE_ITEMS_COL_ID,
      $item_id);

    if ($res->numRows() !== 1){
      // Error
      throw \Exception();
    }

    $rows = $res->mapRowsTyped();

    return $rows[0];
  }

  public async function addItem(/* TBC */): Awaitable<void>
  {
    $res = await $this->connection->queryf("INSERT INTO %T (%C, %C, %C) VALUES (%s, %s, NOW()) ", 
      self::TABLE_ITEMS,
      /* TBC*/
      self::TABLE_ITEMS_COL_CREATED_AT);

    return $this;
  }

  public async function addIntent(string $uid, PlatformResult $object): Awaitable<this>
  {
    // Persist intent and object in DB for a later share if necessary
    $res = await $this->connection->queryf("INSERT INTO %T (%C, %C, %C) VALUES (%s, %s, NOW()) ", 
      self::TABLE_INTENTS,
      self::TABLE_INTENTS_COL_UID,
      self::TABLE_INTENTS_COL_OBJECT, 
      self::TABLE_INTENTS_COL_CREATED_AT,
      $uid,
      serialize($object));

    if ($res->numRowsAffected() !== 1){
      // Error
      throw \Exception("Error adding intent");
    }

    return $this;
  }

}
