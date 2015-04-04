<?hh // strict

namespace tuneefy\Platform;

use tuneefy\Platform\GeneralPlatformInterface;

abstract class Platform implements GeneralPlatformInterface
{

  const string NAME = "";
  const string SAFE_NAME = "";
  const string COLOR = "FFFFFF";

  // Helper Regexes
  const string REGEX_FULLSTRING = "[a-zA-Z0-9%\+-\s\_\.]*";
  const string REGEX_NUMERIC_ID = "[0-9]*";

  protected bool $default;

  protected Map<string, bool> $enables;
  protected Map<string, bool> $capabilities;

  protected string $key;
  protected string $secret;

  /**
   * The singleton instance of the class.
   */
  protected static ?Platform $instance = null;

  /**
   * Protected constructor to ensure there are no instantiations.
   */
  final protected function __construct()
  {
    $this->default = false;
    $this->capabilities = Map {};
    $this->enables = Map {};
    $this->key = "";
    $this->secret = "";
  }

  /**
   * Retrieves the singleton instance.
   */
  public static function getInstance(): Platform
  {
      if (static::$instance === null) {
          static::$instance = new static();
      }
      return static::$instance;
  }

  public function getName(): string
  {
    return self::NAME;
  }

  public function getSafeName(): string
  {
    return self::SAFE_NAME;
  }

  public function getColor(): string
  {
    return self::COLOR;
  }

  // Credentials
  public function setCredentials(string $key, string $secret): this
  {
    $this->key = $key;
    $this->secret = $secret;
    return $this;
  }

  // Enabled & default
  public function setEnables(Map<string,bool> $enables): this
  {
    $this->enables = $enables;
    return $this;
  }

  public function isDefault(): bool
  {
    return $this->default;
  }

  public function isEnabledForApi(): bool
  {
    return $this->enables['api'];
  }

  public function isEnabledForWebsite(): bool
  {
    return $this->enables['website'];
  }


  // Capabilities
  public function setCapabilities(Map<string,bool> $capabilities): this
  {
    $this->capabilities = $capabilities;
    return $this;
  }

  public function isCapableOfSearchingTracks(): bool
  {
    return $this->capabilities['track_search'];
  }

  public function isCapableOfSearchingAlbums(): bool
  {
    return $this->capabilities['album_search'];
  }

  public function isCapableOfLookingUp(): bool
  {
    return $this->capabilities['lookup'];
  }

}
