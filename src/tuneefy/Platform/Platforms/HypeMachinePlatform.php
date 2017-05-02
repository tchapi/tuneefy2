<?php

namespace tuneefy\Platform\Platforms;

use tuneefy\MusicalEntity\Entities\AlbumEntity;
use tuneefy\MusicalEntity\Entities\TrackEntity;
use tuneefy\Platform\Platform;
use tuneefy\Platform\PlatformException;
use tuneefy\Platform\PlatformResult;
use tuneefy\Platform\WebStreamingPlatformInterface;
use tuneefy\Utils\Utils;

class HypeMachinePlatform extends Platform implements WebStreamingPlatformInterface
{
    const NAME = 'Hype Machine';
    const HOMEPAGE = 'https://hypem.com/';
    const TAG = 'hypem';
    const COLOR = '83C441';

    const API_ENDPOINT = 'http://hypem.com/';
    const API_METHOD = Platform::METHOD_GET;

    protected $endpoints = [
        Platform::LOOKUP_TRACK => self::API_ENDPOINT.'playlist/item/%s/json/1/data.js',
        Platform::LOOKUP_ALBUM => null,
        Platform::LOOKUP_ARTIST => self::API_ENDPOINT.'playlist/artist/%s/json/1/data.js',
        Platform::SEARCH_TRACK => self::API_ENDPOINT.'playlist/search/%s/json/1/data.js',
        Platform::SEARCH_ALBUM => self::API_ENDPOINT.'playlist/search/%s/json/1/data.js',
       // Platform::SEARCH_ARTIST => self::API_ENDPOINT . "playlist/search/%s/json/1/data.js"
    ];
    protected $terms = [
        Platform::LOOKUP_TRACK => null,
        Platform::LOOKUP_ALBUM => null,
        Platform::LOOKUP_ARTIST => null,
        Platform::SEARCH_TRACK => null,
        Platform::SEARCH_ALBUM => null,
       // Platform::SEARCH_ARTIST => null
    ];
    protected $options = [
        Platform::LOOKUP_TRACK => [],
        Platform::LOOKUP_ALBUM => [],
        Platform::LOOKUP_ARTIST => [],
        Platform::SEARCH_TRACK => [],
        Platform::SEARCH_ALBUM => [],
       // Platform::SEARCH_ARTIST => []
    ];

    // http://hypem.com/item/1arwr/Digitalism+-+2+Hearts
    const REGEX_HYPEM_TRACK = "/\/(?:item|track)\/(?P<track_id>[0-9a-zA-Z]*)(|\/(?P<track_slug>".Platform::REGEX_FULLSTRING."))[\/]?$/";
    // http://hypem.com/artist/Digitalism
    const REGEX_HYPEM_ARTIST = "/\/artist\/(?P<artist_slug>".Platform::REGEX_FULLSTRING.")[\/]?$/";

    public function hasPermalink(string $permalink): bool
    {
        return strpos($permalink, 'hypem.') !== false;
    }

    private function getPermalinkFromTrackId(string $track_id): string
    {
        return sprintf('http://hypem.com/track/%s', $track_id);
    }

    public function expandPermalink(string $permalink, int $mode): PlatformResult
    {
        $musical_entity = null;
        $query_words = [$permalink];

        $match = [];

        if (preg_match(self::REGEX_HYPEM_TRACK, $permalink, $match)) {
            $response = self::fetch($this, Platform::LOOKUP_TRACK, $match['track_id']);

            if ($response === null || !property_exists($response->data, '0')) {
                throw new PlatformException($this);
            }

            $entity = array_values(get_object_vars($response->data)); // "O" as a key, seriously ?

            // No cover : on HypeM, covers are not the album's, so they are not relevant
            $musical_entity = new TrackEntity($entity[1]->title, new AlbumEntity('', $entity[1]->artist, ''));
            $musical_entity->addLink(static::TAG, $permalink);

            $query_words = [
                $musical_entity->getAlbum()->getArtist(),
                $musical_entity->getSafeTitle(),
            ];
        } elseif (preg_match(self::REGEX_HYPEM_ARTIST, $permalink, $match)) {
            $response = self::fetch($this, Platform::LOOKUP_ARTIST, $match['artist_slug']);

            if ($response === null || !property_exists($response->data, '0')) {
                throw new PlatformException($this);
            }

            $entity = array_values(get_object_vars($response->data));
            $query_words = [$entity[1]->artist];
        }

        // Consolidate results
        $metadata = ['query_words' => $query_words];

        if ($musical_entity !== null) {
            $metadata['platform'] = $this->getName();
        }

        return new PlatformResult($metadata, $musical_entity);
    }

    public function extractSearchResults(\stdClass $response, int $type, string $query, int $limit, int $mode): array
    {
        if (!property_exists($response->data, '0')) {
            //throw new PlatformException($this);
            return [];
        }
        unset($response->data->version);
        $entities = array_values(get_object_vars($response->data)); // "O" as a key, seriously ?

        $length = min(count($entities), $limit ? $limit : Platform::LIMIT);

        $musical_entities = [];
        // Normalizing each track found
        for ($i = 0; $i < $length; ++$i) {
            $current_item = $entities[$i];

            if ($type === Platform::SEARCH_TRACK) {
                $musical_entity = new TrackEntity($current_item->title, new AlbumEntity('', $current_item->artist, ''));
                $musical_entity->addLink(static::TAG, $this->getPermalinkFromTrackId($current_item->mediaid));
                $musical_entities[] = new PlatformResult(['score' => Utils::indexScore($i)], $musical_entity);
            }
        }

        return $musical_entities;
    }
}
