<?hh // strict

namespace tuneefy\Platform;

use tuneefy\Platform\GeneralPlatformInterface,
    tuneefy\Utils\Utils;

abstract class Platform implements GeneralPlatformInterface
{

  const string NAME = "";
  const string TAG = "";
  const string COLOR = "FFFFFF";

  // Helper Regexes
  const string REGEX_FULLSTRING = "[a-zA-Z0-9%\+-\s\_\.]*";
  const string REGEX_NUMERIC_ID = "[0-9]*";

  // Helper constants for API calls
  const int LOOKUP_TRACK = 0;
  const int LOOKUP_ALBUM = 1;
  const int LOOKUP_ARTIST = 2;

  const int SEARCH_TRACK = 3;
  const int SEARCH_ALBUM = 4;
  //const int SEARCH_ARTIST = 5;

  const string METHOD_GET = "GET";
  const string METHOD_POST = "POST";

  const int LIMIT = 10;

  protected bool $default;

  protected Map<string, bool> $enables;
  protected Map<string, bool> $capabilities;

  protected string $key;
  protected string $secret;

  // Redeclared in child classes
  const string API_ENDPOINT = "";
  const string API_METHOD = Platform::METHOD_GET;
  const bool NEEDS_OAUTH = false;

  protected ImmMap<int,?string> $endpoints = ImmMap {};
  protected ImmMap<int,?string> $terms = ImmMap {};
  protected ImmMap<int,ImmMap<string,mixed>> $options = ImmMap {};

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
    return static::NAME;
  }

  public function getTag(): string
  {
    return static::TAG;
  }

  public function getColor(): string
  {
    return static::COLOR;
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

  protected function fetch(int $type, string $query, ...): ?\stdClass
  {

    $url = $this->endpoints->get($type);

    if ($this->terms->get($type) === null) {
      // See: https://github.com/facebook/hhvm/issues/3725
      // The format string should be litteral but it's nearly impossible to achieve here
      // UNSAFE
      $url = sprintf($url, $query);
      $data = $this->options->get($type);
    } else {
      $merge = (Map<?string,mixed> $a,$b) ==> $a->setAll($b); // Lambda function to merge a Map and an ImmMap
      $data = $merge(Map{ $this->terms->get($type) => $query}, $this->options->get($type));
    }
    
    if (static::NEEDS_OAUTH) {
      // TODO
    }

    $ch = curl_init();
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_HEADER => 0
    ));

    if (static::API_METHOD === Platform::METHOD_GET) {
      curl_setopt($ch, CURLOPT_URL, $url .'?'. http_build_query($data)); // It's ok to have a trailing "?"
    } else if (static::API_METHOD === Platform::METHOD_POST) {
      curl_setopt($ch, CURLOPT_URL, $url);
      curl_setopt($ch, CURLOPT_POST, 1);
      curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    }

    $response = curl_exec($ch);
    curl_close($ch);

    // For some platforms
    $response = Utils::removeBOM($response);

    if ($response === false) {
      // Error in the request, we should gracefully fail returning null
      return null;
    } else {
      // If there is a problem with the data, we want to return null to gracefully fail as well :
      // "NULL is returned if the json cannot be decoded or if the encoded data is deeper than the recursion limit."
      return json_decode($response, false);
    }

  }

}
