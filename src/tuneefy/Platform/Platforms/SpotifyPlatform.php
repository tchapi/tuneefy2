<?php

namespace tuneefy\Platform\Platforms;

use tuneefy\MusicalEntity\Entities\AlbumEntity;
use tuneefy\MusicalEntity\Entities\TrackEntity;
use tuneefy\Platform\Platform;
use tuneefy\Platform\PlatformException;
use tuneefy\Platform\PlatformResult;
use tuneefy\Platform\WebStreamingPlatformInterface;
use tuneefy\Utils\Utils;

class SpotifyPlatform extends Platform implements WebStreamingPlatformInterface
{
    public const NAME = 'Spotify';
    public const HOMEPAGE = 'https://www.spotify.com/';
    public const TAG = 'spotify';
    public const COLOR = '4DA400';

    public const API_ENDPOINT = 'https://api.spotify.com/v1/';
    public const API_METHOD = Platform::METHOD_GET;

    protected $endpoints = [
        Platform::LOOKUP_TRACK => self::API_ENDPOINT.'tracks/%s',
        Platform::LOOKUP_ALBUM => self::API_ENDPOINT.'albums/%s',
        Platform::LOOKUP_ARTIST => self::API_ENDPOINT.'artists/%s',
        Platform::SEARCH_TRACK => self::API_ENDPOINT.'search',
        Platform::SEARCH_ALBUM => self::API_ENDPOINT.'search',
       // Platform::SEARCH_ARTIST => self::API_ENDPOINT . "search"
    ];
    protected $terms = [
        Platform::LOOKUP_TRACK => null,
        Platform::LOOKUP_ALBUM => null,
        Platform::LOOKUP_ARTIST => null,
        Platform::SEARCH_TRACK => 'q',
        Platform::SEARCH_ALBUM => 'q',
       // Platform::SEARCH_ARTIST => "q"
    ];
    protected $options = [
        Platform::LOOKUP_TRACK => [],
        Platform::LOOKUP_ALBUM => [],
        Platform::LOOKUP_ARTIST => [],
        Platform::SEARCH_TRACK => ['type' => 'track', 'limit' => Platform::LIMIT],
        Platform::SEARCH_ALBUM => ['type' => 'album', 'limit' => Platform::LIMIT],
       // Platform::SEARCH_ARTIST => Map { "type" => "artist", "limit" => Platform::LIMIT }
    ];

    // http://open.spotify.com/track/5jhJur5n4fasblLSCOcrTp
    public const REGEX_SPOTIFY_ALL = "/(?P<type>artist|album|track)(:|\/)(?P<item_id>[a-zA-Z0-9]*)[\/]?$/";
    private $lookup_type_correspondance = [
        'track' => Platform::LOOKUP_TRACK,
        'album' => Platform::LOOKUP_ALBUM,
        'artist' => Platform::LOOKUP_ARTIST,
    ];

    // LOCAL files : http://open.spotify.com/local/hang+the+bastard/raw+sorcery/doomed+fucking+doomed/206
    public const REGEX_SPOTIFY_LOCAL = "/local\/(?P<artist_name>".Platform::REGEX_FULLSTRING.")\/(?P<album_name>".Platform::REGEX_FULLSTRING.")\/(?P<track_name>".Platform::REGEX_FULLSTRING.")\/[0-9]+$/";

    public function hasPermalink(string $permalink): bool
    {
        return false !== strpos($permalink, 'spotify:') || false !== strpos($permalink, 'open.spotify.') || false !== strpos($permalink, 'play.spotify.');
    }

    protected function addContextOptions(?array $data, string $countryCode = null): array
    {
        $data['market'] = strtoupper($countryCode ?: self::DEFAULT_COUNTRY_CODE);

        return $data;
    }

    protected function addContextHeaders(): array
    {
        // From https://developer.spotify.com/web-api/authorization-guide/
        $serviceauth = 'https://accounts.spotify.com/api/token';
        $grantType = 'client_credentials';

        $requestData = ['client_id' => $this->key, 'client_secret' => $this->secret, 'grant_type' => $grantType];

        $ch = curl_init();
        curl_setopt_array($ch, [
          CURLOPT_URL => $serviceauth,
          CURLOPT_POST => 1,
          CURLOPT_RETURNTRANSFER => 1,
          CURLOPT_POSTFIELDS => http_build_query($requestData),
        ]);

        $result = json_decode(curl_exec($ch), true);
        curl_close($ch);

        if (!isset($result['access_token'])) {
          return [];
        }

        return ['Authorization: Bearer '.$result['access_token']];
    }

    public function expandPermalink(string $permalink, int $mode): PlatformResult
    {
        $musical_entity = null;
        $query_words = [$permalink];

        $match = [];

        if (preg_match(self::REGEX_SPOTIFY_ALL, $permalink, $match)) {
            // We have a nicely formatted share url

            $object_type = $this->lookup_type_correspondance[$match['type']];
            $response = self::fetch($this, $object_type, $match['item_id']);

            if (null === $response || property_exists($response->data, 'error')) {
                throw new PlatformException($this);
            }
            $entity = $response->data;

            if (Platform::LOOKUP_TRACK === $object_type) {
                $musical_entity = new TrackEntity($entity->name, new AlbumEntity($entity->album->name, $entity->artists[0]->name, $entity->album->images[1]->url));
                $musical_entity->addLink(static::TAG, $entity->external_urls->spotify);

                $query_words = [
                    $musical_entity->getAlbum()->getArtist(),
                    $musical_entity->getSafeTitle(),
                ];
            } elseif (Platform::LOOKUP_ALBUM === $object_type) {
                $musical_entity = new AlbumEntity($entity->name, $entity->artists[0]->name, $entity->images[1]->url);
                $musical_entity->addLink(static::TAG, $entity->external_urls->spotify);

                $query_words = [
                    $musical_entity->getArtist(),
                    $musical_entity->getSafeTitle(),
                ];
            } elseif (Platform::LOOKUP_ARTIST === $object_type) {
                $query_words = [$entity->name];
            }
        } elseif (preg_match(self::REGEX_SPOTIFY_LOCAL, $permalink, $match)) {
            // We have a nicely formatted local url, but can only retrieve query words
            $query_words = [$match['artist_name'], $match['track_name']];
        }

        // Consolidate results
        $metadata = ['query_words' => $query_words];

        if (null !== $musical_entity) {
            $metadata['platform'] = $this->getName();
        }

        return new PlatformResult($metadata, $musical_entity);
    }

    public function extractSearchResults(\stdClass $response, int $type, string $query, int $limit, int $mode): array
    {
        $entities = $response->data;

        if (!isset($entities->tracks) && !isset($entities->albums)) {
            return [];
        }

        switch ($type) {
            case Platform::SEARCH_TRACK:
                $results = $entities->tracks->items;
                break;
            case Platform::SEARCH_ALBUM:
                $results = $entities->albums->items;
                break;
        }
        $length = min(count($results), $limit ? $limit : Platform::LIMIT);

        $musical_entities = [];

        if (!isset($results[0])) {
            return [];
        }
        // Tracks bear a popularity score
        // that we're using to rate the results
        $max_track_popularity = 1;
        if (Platform::SEARCH_TRACK === $type) {
            $max_track_popularity = max(intval($results[0]->popularity), 1);
        }

        for ($i = 0; $i < $length; ++$i) {
            $current_item = $results[$i];

            if (Platform::SEARCH_TRACK === $type) {
                $images = $current_item->album->images;
                $musical_entity = new TrackEntity($current_item->name, new AlbumEntity($current_item->album->name, $current_item->artists[0]->name, (isset($images[1]) ? $images[1]->url : (isset($images[0]) ? $images[0]->url : null))));
                $musical_entity->addLink(static::TAG, $current_item->external_urls->spotify);
                $externalIds = [static::TAG => $current_item->id];

                $musical_entities[] = new PlatformResult(['score' => round($current_item->popularity / $max_track_popularity, 2), 'externalIds' => $externalIds], $musical_entity);
            } else /*if ($type === Platform::SEARCH_ALBUM)*/ {
                $images = $current_item->images;
                $musical_entity = new AlbumEntity($current_item->name, $current_item->artists[0]->name, (isset($images[1]) ? $images[1]->url : (isset($images[0]) ? $images[0]->url : null)));
                $musical_entity->addLink(static::TAG, $current_item->external_urls->spotify);
                $externalIds = [static::TAG => $current_item->id];

                $musical_entities[] = new PlatformResult(['score' => Utils::indexScore($i), 'externalIds' => $externalIds], $musical_entity);
            }
        }

        return $musical_entities;
    }
}
