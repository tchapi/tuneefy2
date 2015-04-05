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

  // Helper constants for API calls
  const int LOOKUP_TRACK = 0;
  const int LOOKUP_ALBUM = 1;
  const int LOOKUP_ARTIST = 2;

  const int SEARCH_TRACK = 3;
  const int SEARCH_ALBUM = 4;
  const int SEARCH_ARTIST = 5;

  const string METHOD_GET = "GET";
  const string METHOD_POST = "POST";

  const int LIMIT = 10;

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

  protected function fetch(int $type, string $query, ...): ?Map<string, mixed>
  {

    $url = $this->endpoints->get($type);
    $data = $this->search_options->zip(Map {$this->search_term => $query});


    if (self::NEEDS_OAUTH) {
      // TODO
    }

    $ch = curl_init();
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_HEADER => 0
    ));

    if (self::API_METHOD === Platform::METHOD_GET) {
      curl_setopt($ch, CURLOPT_URL, $url .'?'. http_build_query($data));
    } else if (self::API_METHOD === Platform::METHOD_POST) {
      curl_setopt($ch, CURLOPT_URL, $url);
      curl_setopt($ch, CURLOPT_POST, 1);
      curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    }

    $response = curl_exec($ch);
    // Close request to clear up some resources
    curl_close($ch);

    if ($response === false) {
      // Error in the request, we should gracefully fail returning null
      return null;
    } else {

      // PROBABLY NOT NECESSARY NOW THAT WE USE cURL ?
      // $mustUnchunk = false;
      //   if (strpos(strtolower($result), "transfer-encoding: chunked") !== FALSE) {
      //       $mustUnchunk = true;
      //   }

      //   // Split the result header from the content
      //   $result = explode("\r\n\r\n", $result, 2);
      //   $result = isset($result[1]) ? $result[1] : null;

      //   if ($mustUnchunk === true) {
      //     $result = self::unchunkHttp11($result);
      //   }

      return $response;
    }

  }

}
