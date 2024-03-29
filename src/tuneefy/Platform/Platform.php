<?php

namespace tuneefy\Platform;

use tuneefy\Utils\OAuth\OAuthConsumer;
use tuneefy\Utils\OAuth\OAuthRequest;
use tuneefy\Utils\OAuth\OAuthSignatureMethod_HMAC_SHA1;
use tuneefy\Utils\Utils;

abstract class Platform implements GeneralPlatformInterface
{
    public const NAME = '';
    public const TAG = '';
    public const HOMEPAGE = '';
    public const COLOR = 'FFFFFF';

    // Helper Regexes
    public const REGEX_FULLSTRING = "[a-zA-Z0-9%\+\-\s\_\.]*";
    public const REGEX_NUMERIC_ID = '[0-9]*';

    // Helper constants for API calls
    public const LOOKUP_TRACK = 0;
    public const LOOKUP_ALBUM = 1;
    public const LOOKUP_ARTIST = 2;

    public const SEARCH_TRACK = 3;
    public const SEARCH_ALBUM = 4;
    public const SEARCH_ARTIST = 5;

    public const DEFAULT_COUNTRY_CODE = 'FR';

    // The 'mode' indicates whether
    // we're going to eagerly fetch data
    // when it's missing from the platform
    // response
    public const MODE_LAZY = 0;
    public const MODE_EAGER = 1;

    // Different HTTP Methods used
    public const METHOD_GET = 'GET';
    public const METHOD_POST = 'POST';

    // Different Return content-type
    public const RETURN_JSON = 1;
    public const RETURN_XML = 2;

    // Default limit for requests
    public const LIMIT = 10; // 1 < LIMIT < 25
    public const AGGREGATE_LIMIT = 50; // The more the merrier

    protected $default;

    protected $enables;
    protected $capabilities;

    protected $key = '';
    protected $secret = '';

    // Redeclared in child classes
    public const API_ENDPOINT = '';
    public const API_METHOD = self::METHOD_GET;
    public const RETURN_CONTENT_TYPE = self::RETURN_JSON;
    public const NEEDS_OAUTH = false;

    protected $endpoints = [];
    protected $terms = [];
    protected $options = [];

    // For hosts that resolve slooooooowly, add the IP here. Quite dangerous but helpful
    // It's better to do this directly on the target server that will run the tuneefy api
    /*
        $hosts = [
          'listen.tidal.com',
          'www.amazon.com',
          'api.deezer.com',
          'www.googleapis.com',
          'itunes.apple.com',
          'ws.audioscrobbler.com',
          'api.mixcloud.com',
          'api.napster.com',
          'www.qobuz.com',
          'api.soundcloud.com',
        ];

        echo "[\n";
        foreach ($hosts as $id => $value) {
            $ips = gethostbynamel($value);
            echo "  '".$value.':443'.':'.$ips[0]."',\n";
        }
        echo "];\n";
    */
    public const RESOLVED_IPS = [
      'listen.tidal.com:443:143.204.209.85',
      'www.amazon.com:443:23.43.17.196',
      'api.deezer.com:443:185.60.92.36',
      'www.googleapis.com:443:142.250.185.74',
      'itunes.apple.com:443:96.16.108.28',
      'ws.audioscrobbler.com:443:130.211.19.189',
      'api.napster.com:443:92.123.148.44',
      'www.qobuz.com:443:18.203.143.35',
      'api.soundcloud.com:443:143.204.203.38',
    ];

    public const CONNECT_TIMEOUT = 2000;
    public const CURL_TIMEOUT = 2000;
    public const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/105.0.0.0 Safari/537.36';

    /**
     * The singleton instance of the class.
     */
    protected static $instances = [];

    /**
     * Protected constructor to ensure there are no instantiations.
     */
    final protected function __construct()
    {
        $this->default = false;
        $this->capabilities = [];
        $this->enables = [];
        $this->key = '';
        $this->secret = '';
    }

    /**
     * Retrieves the singleton instance.
     * We need a Map of instances otherwise
     * only one instance of child class will
     * we created.
     */
    public static function getInstance(): Platform
    {
        $class = get_called_class();
        if (!isset(static::$instances[$class]) || null === static::$instances[$class]) {
            static::$instances[$class] = new static();
        }

        return static::$instances[$class];
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

    public function getType(): string
    {
        if ($this instanceof WebStreamingPlatformInterface) {
            return 'streaming';
        } elseif ($this instanceof WebStoreInterface) {
            return 'store';
        } elseif ($this instanceof ScrobblingPlatformInterface) {
            return 'scrobbling';
        } else {
            return 'general';
        }
    }

    // Credentials
    public function setCredentials(string $key, string $secret): Platform
    {
        $this->key = $key;
        $this->secret = $secret;

        return $this;
    }

    // Enabled & default
    public function setEnables(array $enables): Platform
    {
        $this->enables = $enables;

        return $this;
    }

    public function setDefault(bool $default): Platform
    {
        $this->default = $default;

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
    public function setCapabilities(array $capabilities): Platform
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

    // This function, or its children class' counterpart,
    // is called in the fetch method to give the child
    // class a chance to add other contextual options
    protected function addContextOptions(?array $data, string $countryCode = null): array
    {
        return $data;
    }

    // This function, or its children class' counterpart,
    // is called in the fetch method to give the child
    // class a chance to add other contextual headers
    protected function addContextHeaders(): array
    {
        return [];
    }

    public function toArray(): array
    {
        $result = [
            'name' => static::NAME,
            'type' => $this->getType(),
            'homepage' => static::HOMEPAGE,
            'tag' => static::TAG,
            'mainColorAccent' => static::COLOR,
            'enabled' => $this->enables,
            'capabilities' => $this->capabilities,
        ];

        return $result;
    }

    private function prepareCall(int $type, string $query, string $countryCode = null)
    {
        $url = $this->endpoints[$type];

        if (null === $this->terms[$type]) {
            $url = sprintf($url, $query);
            $data = $this->options[$type];
        } else {
            $data = array_merge([$this->terms[$type] => $query], $this->options[$type]);
        }

        // Gives the child class a chance to add options and headers that are
        // contextual to the request, eg. for Xbox, a token, or
        // just simply the API key ...
        $data = $this->addContextOptions($data, $countryCode);
        $headers = $this->addContextHeaders();

        if (static::NEEDS_OAUTH) {
            // We add the signature to the request data
            $consumer = new OAuthConsumer($this->key, $this->secret, null);
            $req = OAuthRequest::from_consumer_and_token($consumer, null, static::API_METHOD, $url, $data->toArray());
            $hmac_method = new OAuthSignatureMethod_HMAC_SHA1();
            $req->sign_request($hmac_method, $consumer, null);

            array_merge($data, $req->get_parameters());
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_FOLLOWLOCATION => 1, // Some APIs redirect to content with a 3XX code
            CURLOPT_CONNECTTIMEOUT => static::CONNECT_TIMEOUT,
            CURLOPT_TIMEOUT_MS => static::CURL_TIMEOUT,
            CURLOPT_ENCODING => '',
            CURLOPT_RESOLVE => static::RESOLVED_IPS,
            CURLOPT_USERAGENT => static::USER_AGENT,
        ]);

        if (self::METHOD_GET === static::API_METHOD) {
            curl_setopt($ch, CURLOPT_URL, $url.($data ? '?'.http_build_query($data) : ''));
        } elseif (self::METHOD_POST === static::API_METHOD) {
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        }

        return $ch;
    }

    private function postProcessResult(string $response)
    {
        if (!$response) {
            // Error in the request, we should gracefully fail returning null
            return null;
        } else {
            if (self::RETURN_XML === static::RETURN_CONTENT_TYPE) {
                $response = Utils::flattenMetaXMLNodes($response);
                try {
                    $xml = simplexml_load_string($response);
                    $response = json_encode($xml);
                } catch (\Exception $e) {
                    return null; // XML can't be parsed
                }
            }

            // For some platforms
            $response = Utils::removeBOM($response);

            // If there is a problem with the data, we want to return null to gracefully fail as well :
            // "NULL is returned if the json cannot be decoded or if the encoded data is deeper than the recursion limit."
            //
            // Why the "data" key ? It's to cope with the result of json_decode. If the first level of $response
            // is pure array, json_decode will return a array object instead of an expected stdClass object.
            // To bypass that, we force the inclusion of the response in a data key, making it de facto an object.
            return json_decode('{"data":'.$response.'}', false);
        }
    }

    protected static function fetch(Platform $platform, int $type, string $query, string $countryCode = null)
    {
        $ch = $platform->prepareCall($type, $query, $countryCode);
        $response = curl_exec($ch);
        curl_close($ch);

        return $platform->postProcessResult($response);
    }

    public static function search(Platform $platform, int $type, string $query, int $limit, int $mode, string $countryCode = null)
    {
        $response = self::fetch($platform, $type, $query, $countryCode);

        if (null === $response) {
            throw new PlatformException($platform);
        }

        return $platform->extractSearchResults($response, $type, $query, $limit, $mode);
    }

    public static function aggregate(array $platforms, int $type, string $query, int $limit, int $mode, string $countryCode = null): array
    {
        // Create the multiple cURL handle
        $mh = curl_multi_init();
        $handles = [];

        foreach ($platforms as $platform) {
            $ch = $platform->prepareCall($type, $query, $countryCode);
            curl_multi_add_handle($mh, $ch);
            $handles[] = [
                'handle' => $ch,
                'platform' => $platform,
            ];
        }

        $running = null;
        do {
            curl_multi_exec($mh, $running);
        } while ($running > 0);

        // Get the actual content
        $results = [];
        $errors = [];
        foreach ($handles as $object) {
            $response = curl_multi_getcontent($object['handle']); // get the content
            curl_multi_remove_handle($mh, $object['handle']); // remove the handle

            $response = $object['platform']->postProcessResult($response);

            if (null === $response || false === $response->data) {
                $errors[] = ['FETCH_PROBLEM' => (new PlatformException($object['platform']))->getMessage()];
                continue;
            }

            $result = $object['platform']->extractSearchResults($response, $type, $query, $limit, $mode);

            $results = array_merge($results, $result);
        }

        curl_multi_close($mh);

        return [
            'results' => $results,
            'errors' => $errors,
        ];
    }

    abstract public function extractSearchResults(\stdClass $response, int $type, string $query, int $limit, int $mode);
}
