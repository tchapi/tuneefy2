<?hh // strict
// vim: foldmethod=marker

namespace tuneefy\Utils\OAuth;

class OAuthRequest {

  protected array<string,mixed> $parameters;
  protected string $http_method;
  protected string $http_url;

  // for debug purposes
  public string $base_string = "";
  public static string $version = "1.0";
  public static string $POST_INPUT = "php://input";

  public function __construct(string $http_method, string $http_url, array<string,mixed> $parameters = array())
  {
    $parameters = !is_null($parameters) ? $parameters : array();
    $parameters = array_merge( OAuthUtil::parse_parameters(parse_url($http_url, PHP_URL_QUERY)), $parameters);
    $this->parameters = $parameters;
    $this->http_method = $http_method;
    $this->http_url = $http_url;
  }

  /**
   * pretty much a helper function to set up the request
   */
  public static function from_consumer_and_token(OAuthConsumer $consumer, ?OAuthToken $token, string $http_method, string $http_url, array<string,mixed> $parameters = array()): OAuthRequest
  {
    $parameters = is_null($parameters) ?  $parameters : array();
    $defaults = array("oauth_version" => OAuthRequest::$version,
                      "oauth_nonce" => OAuthRequest::generate_nonce(),
                      "oauth_timestamp" => OAuthRequest::generate_timestamp(),
                      "oauth_consumer_key" => $consumer->key);
    if ($token)
      $defaults['oauth_token'] = $token->key;

    $parameters = array_merge($defaults, $parameters);

    return new OAuthRequest($http_method, $http_url, $parameters);
  }

  public function set_parameter(string $name, string $value, bool $allow_duplicates = true): void
  {
    if ($allow_duplicates && array_key_exists($name, $this->parameters)) {
      // We have already added parameter(s) with this name, so add to the list
      if (!is_null($this->parameters) && is_scalar($this->parameters[$name])) {
        // This is the first duplicate, so transform scalar (string)
        // into an array so we can add the duplicates
        $this->parameters[$name] = array($this->parameters[$name], $value);
      } else if (is_array($this->parameters[$name])) {
        $this->parameters[$name] = array_merge($this->parameters[$name], array($value));
      }
    } else {
      $this->parameters[$name] = $value;
    }
  }

  public function get_parameter(string $name): mixed
  {
    return array_key_exists($name, $this->parameters) ? $this->parameters[$name] : null;
  }

  public function get_parameters(): ?array<string,mixed>
  {
    return $this->parameters;
  }

  public function unset_parameter(string $name): void
  {
    $params = array();
    foreach ($this->parameters as $key => $value) {
      // Remove oauth_signature if present
      // Ref: Spec: 9.1.1 ("The oauth_signature parameter MUST be excluded.")
      if ($key !== $name) {
        $params[$key] = $value;
      }
    }
    $this->parameters = $params;
  }

  /**
   * The request parameters, sorted and concatenated into a normalized string.
   * @return string
   */
  public function get_signable_parameters(): string
  {
    // Grab all parameters
    $params = array();
    foreach ($this->parameters as $key => $value) {
      // Remove oauth_signature if present
      // Ref: Spec: 9.1.1 ("The oauth_signature parameter MUST be excluded.")
      if ($key !== 'oauth_signature') {
        $params[$key] = $value;
      }
    }
    return OAuthUtil::build_http_query($params);
  }

  /**
   * Returns the base string of this request
   *
   * The base string defined as the method, the url
   * and the parameters (normalized), each urlencoded
   * and the concated with &.
   */
  public function get_signature_base_string(): string
  {
    $parts = array(
      $this->get_normalized_http_method(),
      $this->get_normalized_http_url(),
      $this->get_signable_parameters()
    );

    $parts = OAuthUtil::urlencode_rfc3986($parts);

    return implode('&', $parts);
  }

  /**
   * just uppercases the http method
   */
  public function get_normalized_http_method(): string
  {
    return strtoupper($this->http_method);
  }

  /**
   * parses the url and rebuilds it to be
   * scheme://host/path
   */
  public function get_normalized_http_url(): string
  {
    $parts = parse_url($this->http_url);

    $scheme = (array_key_exists('scheme', $parts)) ? $parts['scheme'] : 'http';
    $port = (array_key_exists('port', $parts)) ? $parts['port'] : (($scheme == 'https') ? '443' : '80');
    $host = (array_key_exists('host', $parts)) ? strtolower($parts['host']) : '';
    $path = (array_key_exists('path', $parts)) ? $parts['path'] : '';

    if (($scheme == 'https' && $port != '443')
        || ($scheme == 'http' && $port != '80')) {
      $host = "$host:$port";
    }
    return "$scheme://$host$path";
  }

  /**
   * builds a url usable for a GET request
   */
  public function to_url(): string
  {
    $post_data = $this->to_postdata();
    $out = $this->get_normalized_http_url();
    if ($post_data) {
      $out .= '?'.$post_data;
    }
    return $out;
  }

  /**
   * builds the data one would send in a POST request
   */
  public function to_postdata(): string
  {
    return OAuthUtil::build_http_query($this->parameters);
  }

  /**
   * builds the Authorization: header
   */
  public function to_header(?string $realm = null): string
  {
    $first = true;
    if (!is_null($realm)) {
      $out = 'Authorization: OAuth realm="' . (string) OAuthUtil::urlencode_rfc3986($realm) . '"';
      $first = false;
    } else {
      $out = 'Authorization: OAuth';
    }

    $total = array();
    foreach ($this->parameters as $k => $v) {
      if (substr($k, 0, 5) != "oauth") continue;
      if (is_array($v)) {
        throw new OAuthException('Arrays not supported in headers');
      }
      $out .= is_null($first) ? ' ' : ',';
      $out .= (string) OAuthUtil::urlencode_rfc3986($k) .
              '="' .
              (string) OAuthUtil::urlencode_rfc3986($v) .
              '"';
      $first = false;
    }
    return $out;

  }

  public function __toString(): string
  {
    return $this->to_url();
  }

  public function sign_request(OAuthSignatureMethod $signature_method, OAuthConsumer $consumer, ?OAuthToken $token): void
  {
    $this->set_parameter(
      "oauth_signature_method",
      $signature_method->get_name(),
      false
    );
    $signature = $this->build_signature($signature_method, $consumer, $token);
    $this->set_parameter("oauth_signature", $signature, false);
  }

  public function build_signature(OAuthSignatureMethod $signature_method, OAuthConsumer $consumer, ?OAuthToken $token): string
  {
    $signature = $signature_method->build_signature($this, $consumer, $token);
    return $signature;
  }

  /**
   * util function: current timestamp
   */
  private static function generate_timestamp(): int
  {
    return time();
  }

  /**
   * util function: current nonce
   */
  private static function generate_nonce(): string
  {
    $mt = microtime();
    $rand = mt_rand();

    return md5($mt . $rand); // md5s look nicer than numbers
  }
}
