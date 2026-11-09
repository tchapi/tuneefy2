<?php

namespace App\Services\Platforms;

use App\Services\Platforms\Interfaces\GeneralPlatformInterface;
use App\Services\Platforms\Interfaces\ScrobblingPlatformInterface;
use App\Services\Platforms\Interfaces\WebStoreInterface;
use App\Services\Platforms\Interfaces\WebStreamingPlatformInterface;
use App\Utils\Utils;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Service\Attribute\Required;

enum PlatformType: string
{
    case WebStreamingPlatform = 'streaming';
    case WebStore = 'store';
    case ScrobblingPlatform = 'scrobbling';
    case GeneralPlatform = 'general';
}

abstract class Platform implements GeneralPlatformInterface
{
    public const string NAME = '';
    public const string TAG = '';
    public const string HOMEPAGE = '';
    public const string COLOR = 'FFFFFF';

    // Helper Regexes
    public const string REGEX_FULLSTRING = "[a-zA-Z0-9%\+\-\s\_\.]*";
    public const string REGEX_NUMERIC_ID = '[0-9]*';

    // Helper constants for API calls
    public const int LOOKUP_TRACK = 0;
    public const int LOOKUP_ALBUM = 1;
    public const int LOOKUP_ARTIST = 2;

    public const int SEARCH_TRACK = 3;
    public const int SEARCH_ALBUM = 4;
    public const int SEARCH_ARTIST = 5;

    public const string DEFAULT_COUNTRY_CODE = 'FR';

    protected static function countryCodeOrDefault(?string $countryCode): string
    {
        return null === $countryCode || '' === $countryCode ? static::DEFAULT_COUNTRY_CODE : $countryCode;
    }

    // The 'mode' indicates whether
    // we're going to eagerly fetch data
    // when it's missing from the platform
    // response
    public const int MODE_LAZY = 0;
    public const int MODE_EAGER = 1;

    // Different HTTP Methods used
    public const string METHOD_GET = 'GET';
    public const string METHOD_POST = 'POST';

    // Different Return content-type
    public const int RETURN_JSON = 1;
    public const int RETURN_XML = 2;

    // Default limit for requests
    public const int LIMIT = 10; // 1 < LIMIT < 25
    public const int AGGREGATE_LIMIT = 50; // The more the merrier

    protected bool $default = false;
    protected array $enables = [];
    protected array $capabilities = [];

    protected string $key = '';
    protected string $secret = '';

    // Redeclared in child classes
    public const string API_ENDPOINT = '';
    public const string API_METHOD = self::METHOD_GET;
    public const int RETURN_CONTENT_TYPE = self::RETURN_JSON;

    protected array $endpoints = [];
    protected array $terms = [];
    protected array $options = [];

    public const int CONNECT_TIMEOUT = 2000;
    public const int CURL_TIMEOUT = 2000;

    // Platform responses are cached: searches for a while, lookups (stable data) longer
    public const int SEARCH_CACHE_TTL = 3600;
    public const int LOOKUP_CACHE_TTL = 21600;

    protected ?CacheItemPoolInterface $cache = null;
    protected ?LoggerInterface $logger = null;

    #[Required]
    public function setCache(CacheItemPoolInterface $cache): void
    {
        $this->cache = $cache;
    }

    #[Required]
    public function setLogger(#[Autowire(service: 'monolog.logger.platforms')] LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    public function logFailure(string $what, ?string $details = null): void
    {
        $this->logger?->warning(sprintf('%s: %s failed%s', static::NAME, $what, null !== $details && '' !== $details ? ' ('.$details.')' : ''));
    }
    public const string USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/105.0.0.0 Safari/537.36';

    /**
     * The singleton instance of the class.
     */
    protected static array $instances = [];

    final public function __construct(string $key, string $secret)
    {
        $this->key = $key;
        $this->secret = $secret;
    }

    #[\Override]
    public function getName(): string
    {
        return static::NAME;
    }

    #[\Override]
    public static function getTag(): string
    {
        return static::TAG;
    }

    #[\Override]
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
        }

        return PlatformType::GeneralPlatform;
    }

    #[\Override]
    public function isDefault(): bool
    {
        return $this->default;
    }

    #[\Override]
    public function isEnabledForApi(): bool
    {
        return $this->enables['api'];
    }

    #[\Override]
    public function isEnabledForWebsite(): bool
    {
        return $this->enables['website'];
    }

    #[\Override]
    public function isCapableOfSearchingTracks(): bool
    {
        return $this->capabilities['track_search'];
    }

    #[\Override]
    public function isCapableOfSearchingAlbums(): bool
    {
        return $this->capabilities['album_search'];
    }

    #[\Override]
    public function isCapableOfLookingUp(): bool
    {
        return $this->capabilities['lookup'];
    }

    // This function, or its children class' counterpart,
    // is called in the fetch method to give the child
    // class a chance to add other contextual options
    protected function addContextOptions(?array $data, ?string $countryCode = null): array
    {
        return $data ?? [];
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

    /**
     * @return array{url: string, headers: string[], post: ?string, cacheKey: string}
     */
    private function buildRequest(int $type, string $query, ?string $countryCode = null): array
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

        if (self::METHOD_POST === static::API_METHOD) {
            $post = http_build_query($data);
        } else {
            $post = null;
            $url .= $data ? '?'.http_build_query($data) : '';
        }

        return [
            'url' => $url,
            'headers' => $headers,
            'post' => $post,
            'cacheKey' => 'platform_response_'.md5(static::TAG.'|'.$type.'|'.$url.'|'.$post),
        ];
    }

    private function prepareCall(array $request): \CurlHandle
    {
        $ch = curl_init();
        if (false === $ch) {
            throw new PlatformException($this, 'Could not initialize curl');
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_HTTPHEADER => $request['headers'],
            CURLOPT_FOLLOWLOCATION => 1, // Some APIs redirect to content with a 3XX code
            CURLOPT_CONNECTTIMEOUT => static::CONNECT_TIMEOUT,
            CURLOPT_TIMEOUT_MS => static::CURL_TIMEOUT,
            CURLOPT_ENCODING => '',
            CURLOPT_USERAGENT => static::USER_AGENT,
            CURLOPT_URL => $request['url'],
        ]);

        if (null !== $request['post']) {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $request['post']);
        }

        return $ch;
    }

    private function getCachedResponse(array $request): ?string
    {
        if (null === $this->cache) {
            return null;
        }

        $item = $this->cache->getItem($request['cacheKey']);

        return $item->isHit() ? $item->get() : null;
    }

    // Only successful answers are kept: errors, rate limits and timeouts must be retried
    private function cacheResponse(array $request, \CurlHandle $ch, string $response, int $type): void
    {
        if (null === $this->cache || '' === $response || 200 !== curl_getinfo($ch, CURLINFO_RESPONSE_CODE)) {
            return;
        }

        $isSearch = \in_array($type, [self::SEARCH_TRACK, self::SEARCH_ALBUM], true);
        $item = $this->cache->getItem($request['cacheKey'])->set($response)->expiresAfter($isSearch ? static::SEARCH_CACHE_TTL : static::LOOKUP_CACHE_TTL);
        $this->cache->save($item);
    }

    private function postProcessResult(string $response): ?\stdClass
    {
        if (!$response) {
            // Error in the request, we should gracefully fail returning null
            return null;
        }
        if (self::RETURN_XML === static::RETURN_CONTENT_TYPE) {
            $response = Utils::flattenMetaXMLNodes($response);
            try {
                $xml = simplexml_load_string($response);
                $response = json_encode($xml);
            } catch (\Exception $e) {
                return null; // XML can't be parsed
            }

            if (false === $response) {
                return null; // XML can't be re-encoded
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

    /**
     * @throws PlatformException when the platform cannot be reached or answers something undecodable
     */
    protected static function fetch(Platform $platform, int $type, string $query, ?string $countryCode = null): \stdClass
    {
        $request = $platform->buildRequest($type, $query, $countryCode);

        if (null !== ($cached = $platform->getCachedResponse($request))) {
            $response = $cached;
        } else {
            $ch = $platform->prepareCall($request);
            $response = curl_exec($ch);

            // With CURLOPT_RETURNTRANSFER the response is a string, or false on failure
            if (!is_string($response)) {
                $platform->logFailure('request', curl_error($ch));

                throw new PlatformException($platform, curl_error($ch));
            }

            $platform->cacheResponse($request, $ch, $response, $type);
        }

        $decoded = $platform->postProcessResult($response);

        if (null === $decoded) {
            $platform->logFailure('request', 'empty or undecodable response');

            throw new PlatformException($platform, 'empty or undecodable response');
        }

        return $decoded;
    }

    public static function search(Platform $platform, int $type, string $query, int $limit, int $mode, ?string $countryCode = null): array
    {
        $response = self::fetch($platform, $type, $query, $countryCode);

        if (false === $response->data) {
            // The XML could not be parsed
            $platform->logFailure('search', 'empty or undecodable response');

            throw new PlatformException($platform);
        }

        try {
            return $platform->extractSearchResults($response, $type, $query, $limit, $mode);
        } catch (PlatformException $e) {
            $platform->logFailure('search', $e->getMessage());

            throw $e;
        } catch (\Throwable $e) {
            // The platform answered something we do not understand (API change, error payload, ...):
            // this must surface as a FETCH_PROBLEM for this platform, not as a 500 for the whole API
            $platform->logFailure('search', $e->getMessage());

            throw new PlatformException($platform, $e->getMessage());
        }
    }

    public static function aggregate(array $platforms, int $type, string $query, int $limit, int $mode, ?string $countryCode = null): array
    {
        // Create the multiple cURL handle
        $mh = curl_multi_init();
        $handles = [];

        foreach ($platforms as $platform) {
            $request = $platform->buildRequest($type, $query, $countryCode);
            $cached = $platform->getCachedResponse($request);
            $ch = null;

            if (null === $cached) {
                $ch = $platform->prepareCall($request);
                curl_multi_add_handle($mh, $ch);
            }

            $handles[] = [
                'handle' => $ch,
                'platform' => $platform,
                'request' => $request,
                'cached' => $cached,
            ];
        }

        // Wait for the transfers without spinning the CPU
        $running = null;
        do {
            $status = curl_multi_exec($mh, $running);
            if ($running > 0) {
                curl_multi_select($mh, 1.0);
            }
        } while ($running > 0 && CURLM_OK === $status);

        // Get the actual content
        $results = [];
        $errors = [];
        foreach ($handles as $object) {
            $platform = $object['platform'];

            $handle = $object['handle'];

            if (null === $handle) {
                // No handle was created: the response came from the cache
                $response = $object['cached'] ?? '';
            } else {
                $response = curl_multi_getcontent($handle); // get the content
                curl_multi_remove_handle($mh, $handle); // remove the handle

                if (\is_string($response)) {
                    $platform->cacheResponse($object['request'], $handle, $response, $type);
                } else {
                    $platform->logFailure('aggregate request', curl_error($handle));
                    $response = '';
                }
            }

            $response = $platform->postProcessResult($response);

            if (null === $response || false === $response->data) {
                $platform->logFailure('aggregate', 'empty or undecodable response');
                $errors[] = ['FETCH_PROBLEM' => (new PlatformException($platform))->getMessage()];
                continue;
            }

            try {
                $result = $platform->extractSearchResults($response, $type, $query, $limit, $mode);
            } catch (\Throwable $e) {
                // Same as above: one misbehaving platform must not break the whole aggregation
                $platform->logFailure('aggregate', $e->getMessage());
                $errors[] = ['FETCH_PROBLEM' => (new PlatformException($platform))->getMessage()];
                continue;
            }

            $results = array_merge($results, $result);
        }

        curl_multi_close($mh);

        return [
            'results' => $results,
            'errors' => $errors,
        ];
    }

    abstract public function extractSearchResults(\stdClass $response, int $type, string $query, int $limit, int $mode): array;
}
