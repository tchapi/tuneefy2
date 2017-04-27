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
    const NAME = 'Soundcloud';
    const HOMEPAGE = 'https://soundcloud.com/';
    const TAG = 'soundcloud';
    const COLOR = 'ff6600';

    const API_ENDPOINT = 'https://api.soundcloud.com/';
    const API_METHOD = Platform::METHOD_GET;

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

    // http://soundcloud.com/mariecolonna/eminem-feat-tricky-welcome-to
    const REGEX_SOUNDCLOUD_ALL = "/\/".Platform::REGEX_FULLSTRING."\/".Platform::REGEX_FULLSTRING."[\/]?$/";

    public function hasPermalink(string $permalink): bool
    {
        return strpos($permalink, 'soundcloud.') !== false;
    }

    protected function addContextOptions(array $data): array
    {
        $data['client_id'] = $this->key;

        return $data;
    }

    public function expandPermalink(string $permalink, int $mode): PlatformResult
    {
        $musical_entity = null;
        $query_words = [$permalink];

        $match = [];

        if (preg_match(self::REGEX_SOUNDCLOUD_ALL, $permalink, $match)) {
            $response = $this->fetchSync(Platform::LOOKUP_TRACK, $permalink);

            if ($response === null || property_exists($response->data, 'errors')) {
                throw new PlatformException();
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

        if ($musical_entity !== null) {
            $metadata['platform'] = $this->getName();
        }

        return new PlatformResult($metadata, $musical_entity);
    }

    public function search(int $type, string $query, int $limit, int $mode): array
    {
        $response = $this->fetchSync($type, $query);

        if ($response === null) {
            throw new PlatformException();
        }

        $entities = $response->data;

        // We actually don't pass the limit to the fetch()
        // request since it's not really useful, in fact
        $length = min(count($entities), $limit ? $limit : Platform::LIMIT);

        $musical_entities = [];

        // Tracks bear a "playback_count" score
        // that we're using to rate the results
        $max_playback_count = 1;
        if ($type === Platform::SEARCH_TRACK) {
            $max_playback_count = max(intval($entities[0]->playback_count), 1);
        }

        // Normalizing each track found
        for ($i = 0; $i < $length; ++$i) {
            $current_item = $entities[$i];

            if ($type === Platform::SEARCH_TRACK) {
                $musical_entity = new TrackEntity($current_item->title, new AlbumEntity('', $current_item->user->username, $current_item->artwork_url ? $current_item->artwork_url : ''));
                $musical_entity->addLink(static::TAG, $current_item->permalink_url);

                $musical_entities[] = new PlatformResult(['score' => $current_item->playback_count / $max_playback_count], $musical_entity);
            }
        }

        return $musical_entities;
    }
}
