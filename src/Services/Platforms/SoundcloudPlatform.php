<?php

namespace App\Services\Platforms;

use App\Dataclass\MusicalEntity\Entities\Album;
use App\Dataclass\MusicalEntity\Entities\Track;
use App\Services\Platforms\Interfaces\WebStreamingPlatformInterface;

class SoundcloudPlatform extends Platform implements WebStreamingPlatformInterface
{
    use CachedAccessTokenTrait;

    protected $default = false;
    protected $enables = ['api' => true, 'website' => true];
    protected $capabilities = ['track_search' => true, 'album_search' => false, 'lookup' => true];

    public const NAME = 'Soundcloud';
    public const HOMEPAGE = 'https://soundcloud.com/';
    public const TAG = 'soundcloud';
    public const COLOR = 'ff6600';

    // https://developers.soundcloud.com/docs/api/reference#tracks
    public const API_ENDPOINT = 'https://api.soundcloud.com/';
    public const API_METHOD = Platform::METHOD_GET;

    protected $endpoints = [
        Platform::LOOKUP_TRACK => self::API_ENDPOINT.'resolve.json',
        Platform::LOOKUP_ALBUM => null,
        Platform::LOOKUP_ARTIST => null,
        Platform::SEARCH_TRACK => self::API_ENDPOINT.'tracks',
        Platform::SEARCH_ALBUM => null,
        // Platform::SEARCH_ARTIST => self::API_ENDPOINT . "users"
    ];
    protected $terms = [
        Platform::LOOKUP_TRACK => 'url',
        Platform::LOOKUP_ALBUM => null,
        Platform::LOOKUP_ARTIST => null,
        Platform::SEARCH_TRACK => 'q',
        Platform::SEARCH_ALBUM => null,
        // Platform::SEARCH_ARTIST => "q" // Search for a user, in fact
    ];
    protected $options = [
        Platform::LOOKUP_TRACK => [],
        Platform::LOOKUP_ALBUM => [],
        Platform::LOOKUP_ARTIST => [],
        Platform::SEARCH_TRACK => ['limit' => Platform::LIMIT],
        Platform::SEARCH_ALBUM => ['limit' => Platform::LIMIT],
        // Platform::SEARCH_ARTIST => Map { "limit" => Platform::LIMIT }
    ];

    // https://soundcloud.com/robbabicz/pink-trees-1
    public const REGEX_SOUNDCLOUD_ALL = "/\/".Platform::REGEX_FULLSTRING."\/".Platform::REGEX_FULLSTRING."[\/]?$/";

    public function hasPermalink(string $permalink): bool
    {
        return false !== strpos($permalink, 'soundcloud.');
    }

    protected function addContextHeaders(): array
    {
        // https://developers.soundcloud.com/docs/api/guide#authentication
        // (50 tokens per 12 hours per application: see CachedAccessTokenTrait)
        $token = $this->getAccessToken();

        return null === $token ? [] : ['Authorization: OAuth '.$token];
    }

    /**
     * client_credentials flow: HTTP Basic authentication only, credentials in the body are not supported.
     */
    protected function requestAccessToken(): array
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => 'https://secure.soundcloud.com/oauth/token',
            CURLOPT_POST => 1,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_CONNECTTIMEOUT => static::CONNECT_TIMEOUT,
            CURLOPT_TIMEOUT_MS => static::CURL_TIMEOUT,
            CURLOPT_USERPWD => $this->key.':'.$this->secret,
            CURLOPT_HTTPHEADER => ['accept: application/json; charset=utf-8', 'Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_POSTFIELDS => http_build_query(['grant_type' => 'client_credentials']),
        ]);

        $response = curl_exec($ch);

        if (!\is_string($response)) {
            return [];
        }

        return json_decode($response, true) ?: [];
    }

    public function expandPermalink(string $permalink, int $mode): PlatformResult
    {
        $musical_entity = null;
        $query_words = [$permalink];

        $match = [];

        if (preg_match(self::REGEX_SOUNDCLOUD_ALL, $permalink, $match)) {
            $response = self::fetch($this, Platform::LOOKUP_TRACK, $permalink);

            if (property_exists($response->data, 'errors')) {
                throw new PlatformException($this);
            }

            $entity = $response->data;

            $musical_entity = new Track(new Album('', $entity->user->username, $entity->artwork_url ? $entity->artwork_url : ''), $entity->title);
            $musical_entity->addLink(static::TAG, $entity->permalink_url);

            $query_words = [
                $musical_entity->getAlbum()->getArtist(),
                $musical_entity->getSafeTitle(),
            ];
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

        if (null === $entities || !is_array($entities)) {
            // e.g. {"code": 401, "message": "..."}: unauthorized, rate limited or expired credentials
            throw new PlatformException($this, is_object($entities) && property_exists($entities, 'message') ? $entities->message : null);
        }

        // We actually don't pass the limit to the fetch()
        // request since it's not really useful, in fact
        $length = min(count($entities), $limit ? $limit : Platform::LIMIT);

        $musical_entities = [];

        if (0 === count($entities)) {
            return [];
        }

        // Tracks bear a "playback_count" score
        // that we're using to rate the results
        $max_playback_count = 1;
        if (Platform::SEARCH_TRACK === $type) {
            $max_playback_count = max(intval($entities[0]->playback_count), 1);
        }

        // Normalizing each track found
        for ($i = 0; $i < $length; ++$i) {
            $current_item = $entities[$i];

            if (Platform::SEARCH_TRACK === $type) {
                $musical_entity = new Track(new Album('', $current_item->user->username, $current_item->artwork_url ? $current_item->artwork_url : ''), $current_item->title);
                $musical_entity->addLink(static::TAG, $current_item->permalink_url);

                $musical_entities[] = new PlatformResult(['score' => $current_item->playback_count / $max_playback_count, 'externalIds' => [static::TAG => $current_item->id]], $musical_entity);
            }
        }

        return $musical_entities;
    }
}
