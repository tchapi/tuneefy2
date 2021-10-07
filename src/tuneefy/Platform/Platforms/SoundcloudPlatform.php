<?php

namespace tuneefy\Platform\Platforms;

use tuneefy\MusicalEntity\Entities\AlbumEntity;
use tuneefy\MusicalEntity\Entities\TrackEntity;
use tuneefy\Platform\Platform;
use tuneefy\Platform\PlatformException;
use tuneefy\Platform\PlatformResult;
use tuneefy\Platform\WebStreamingPlatformInterface;

class SoundcloudPlatform extends Platform implements WebStreamingPlatformInterface
{
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
        // From https://developers.soundcloud.com/blog/security-updates-api
        $serviceauth = 'https://api.soundcloud.com/oauth2/token';
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

        return ['Authorization: OAuth '.$result['access_token']];
    }

    public function expandPermalink(string $permalink, int $mode): PlatformResult
    {
        $musical_entity = null;
        $query_words = [$permalink];

        $match = [];

        if (preg_match(self::REGEX_SOUNDCLOUD_ALL, $permalink, $match)) {
            $response = self::fetch($this, Platform::LOOKUP_TRACK, $permalink);

            if (null === $response || property_exists($response->data, 'errors')) {
                throw new PlatformException($this);
            }

            $entity = $response->data;

            $musical_entity = new TrackEntity($entity->title, new AlbumEntity('', $entity->user->username, $entity->artwork_url ? $entity->artwork_url : ''));
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

        if (null === $entities || !is_array($entities) || (is_object($entities) && property_exists($entities, 'code') && 401 === $entities->code)) {
            // 401 = unauthorized, probably a rate limit
            return [];
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
                $musical_entity = new TrackEntity($current_item->title, new AlbumEntity('', $current_item->user->username, $current_item->artwork_url ? $current_item->artwork_url : ''));
                $musical_entity->addLink(static::TAG, $current_item->permalink_url);

                $musical_entities[] = new PlatformResult(['score' => $current_item->playback_count / $max_playback_count, 'externalIds' => [static::TAG => $current_item->id]], $musical_entity);
            }
        }

        return $musical_entities;
    }
}
