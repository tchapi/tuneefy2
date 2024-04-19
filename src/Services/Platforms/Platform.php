<?php

namespace App\Services\Platforms;

use App\Services\Platforms\Interfaces\GeneralPlatformInterface;
use App\Services\Platforms\Interfaces\ScrobblingPlatformInterface;
use App\Services\Platforms\Interfaces\WebStoreInterface;
use App\Services\Platforms\Interfaces\WebStreamingPlatformInterface;
use App\Utils\Utils;

enum PlatformType: string
{
    case WebStreamingPlatform = 'streaming';
    case WebStore = 'store';
    case ScrobblingPlatform = 'scrobbling';
    case GeneralPlatform = 'general';
}

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

    protected $endpoints = [];
    protected $terms = [];
    protected $options = [];

    // For hosts that resolve slooooooowly, add the IP here. Quite dangerous but helpful
    // It's better to do this directly on the target server that will run the tuneefy api
    public const RESOLVED_IPS = [
        'listen.tidal.com:443:18.165.227.109',
        'api.deezer.com:443:23.72.248.223',
        'itunes.apple.com:443:23.58.192.28',
        'ws.audioscrobbler.com:443:130.211.19.189',
        'api.mixcloud.com:443:216.119.155.235',
        'api.napster.com:443:151.101.2.233',
        'www.qobuz.com:443:54.78.190.126',
        'api.soundcloud.com:443:18.245.253.65',
    ];

    public const CONNECT_TIMEOUT = 2000;
    public const CURL_TIMEOUT = 2000;
    public const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/105.0.0.0 Safari/537.36';

    /**
     * The singleton instance of the class.
     */
    protected static $instances = [];

    final public function __construct(string $key, string $secret)
    {
        $this->key = $key;
        $this->secret = $secret;
    }

    public function getName(): string
    {
        return static::NAME;
    }

    public static function getTag(): string
    {
        return static::TAG;
    }

    public function getColor(): string
    {
        return static::COLOR;
    }

    public function getType(): PlatformType
    {
        if ($this instanceof WebStreamingPlatformInterface) {
            return PlatformType::WebStreamingPlatform;
        } elseif ($this instanceof WebStoreInterface) {
            return PlatformType::WebStore;
        } elseif ($this instanceof ScrobblingPlatformInterface) {
            return PlatformType::ScrobblingPlatform;
        } else {
            return PlatformType::GeneralPlatform;
        }
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
    protected function addContextOptions(?array $data, ?string $countryCode = null): array
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

    private function prepareCall(int $type, string $query, ?string $countryCode = null)
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

    protected static function fetch(Platform $platform, int $type, string $query, ?string $countryCode = null)
    {
        $ch = $platform->prepareCall($type, $query, $countryCode);
        $response = curl_exec($ch);
        curl_close($ch);

        if (null === $response) {
            throw new PlatformException($platform, curl_error($ch));
        }

        return $platform->postProcessResult($response);
    }

    public static function search(Platform $platform, int $type, string $query, int $limit, int $mode, ?string $countryCode = null)
    {
        $response = self::fetch($platform, $type, $query, $countryCode);

        return $platform->extractSearchResults($response, $type, $query, $limit, $mode);
    }

    public static function aggregate(array $platforms, int $type, string $query, int $limit, int $mode, ?string $countryCode = null): array
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
