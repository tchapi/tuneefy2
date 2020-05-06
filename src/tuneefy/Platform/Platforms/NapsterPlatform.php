<?php

namespace tuneefy\Platform\Platforms;

use tuneefy\MusicalEntity\Entities\AlbumEntity;
use tuneefy\MusicalEntity\Entities\TrackEntity;
use tuneefy\Platform\Platform;
use tuneefy\Platform\PlatformResult;
use tuneefy\Platform\WebStreamingPlatformInterface;
use tuneefy\Utils\Utils;

class NapsterPlatform extends Platform implements WebStreamingPlatformInterface
{
    const NAME = 'Napster';
    const HOMEPAGE = 'https://napster.com';
    const TAG = 'napster';
    const COLOR = '0682AA';

    const API_ENDPOINT = 'https://api.napster.com/v2.2/';
    const API_METHOD = Platform::METHOD_GET;

    protected $endpoints = [
        // Platform::LOOKUP_TRACK => self::API_ENDPOINT.'tracks/%s',
        // Platform::LOOKUP_ALBUM => self::API_ENDPOINT.'albums/%s',
        // Platform::LOOKUP_ARTIST => self::API_ENDPOINT.'artists/%s',
        Platform::SEARCH_TRACK => self::API_ENDPOINT.'search/verbose',
        Platform::SEARCH_ALBUM => self::API_ENDPOINT.'search/verbose',
        //Platform::SEARCH_ARTIST => self::API_ENDPOINT.'search/verbose',
    ];
    protected $terms = [
        Platform::LOOKUP_TRACK => null,
        Platform::LOOKUP_ALBUM => null,
        Platform::LOOKUP_ARTIST => null,
        Platform::SEARCH_TRACK => 'query',
        Platform::SEARCH_ALBUM => 'query',
        //Platform::SEARCH_ARTIST => 'q',
    ];
    protected $options = [
        Platform::LOOKUP_TRACK => [],
        Platform::LOOKUP_ALBUM => [],
        Platform::LOOKUP_ARTIST => [],
        Platform::SEARCH_TRACK => ['type' => 'track', 'limit' => Platform::LIMIT],
        Platform::SEARCH_ALBUM => ['type' => 'album', 'limit' => Platform::LIMIT],
        //Platform::SEARCH_ARTIST => ['type' => 'artist', 'limit' => Platform::LIMIT],
    ];

    // http://fr.napster.com/artist/ed-sheeran/album/shape-of-you/track/shape-of-you
    const REGEX_NAPSTER_ARTIST = "/\/artist\/(?P<artist_slug>".Platform::REGEX_FULLSTRING.")[\/]?$/";
    const REGEX_NAPSTER_ALBUM = "/\/artist\/(?P<artist_slug>".Platform::REGEX_FULLSTRING.")\/album\/(?P<album_slug>".Platform::REGEX_FULLSTRING.")[\/]?$/";
    const REGEX_NAPSTER_TRACK = "/\/artist\/(?P<artist_slug>".Platform::REGEX_FULLSTRING.")\/album\/(?P<album_slug>".Platform::REGEX_FULLSTRING.")\/track\/(?P<track_slug>".Platform::REGEX_FULLSTRING.")[\/]?$/";

    const PICTURE_PATH = 'https://direct.rhapsody.com/imageserver/v2/albums/%s/images/400x400.jpg';
    const WEB_LINK = 'https://napster.com/%s';

    protected function addContextOptions(array $data): array
    {
        $data['apikey'] = $this->key;

        return $data;
    }

    public function hasPermalink(string $permalink): bool
    {
        return false !== strpos($permalink, 'napster.com');
    }

    public function expandPermalink(string $permalink, int $mode)//: ?PlatformResult
    {
        $query_words = [$permalink];

        $match = [];

        if (preg_match(self::REGEX_NAPSTER_ARTIST, $permalink, $match)) {
            $query_words = [
                str_replace('-', ' ', $match['artist_slug']),
            ];
        } elseif (preg_match(self::REGEX_NAPSTER_ALBUM, $permalink, $match)) {
            $query_words = [
                str_replace('-', ' ', $match['artist_slug']),
                str_replace('-', ' ', $match['album_slug']),
            ];
        } elseif (preg_match(self::REGEX_NAPSTER_TRACK, $permalink, $match)) {
            $query_words = [
                str_replace('-', ' ', $match['track_slug']),
                str_replace('-', ' ', $match['artist_slug']),
            ];
        }

        // Consolidate results
        $metadata = ['query_words' => $query_words];

        return new PlatformResult($metadata, null);
    }

    public function extractSearchResults(\stdClass $response, int $type, string $query, int $limit, int $mode): array
    {
        $results = $response->data->search->data->{$this->search_types[$type]};

        $length = min(count($results), $limit ? $limit : Platform::LIMIT);

        $musical_entities = [];

        // Normalizing each track found
        for ($i = 0; $i < $length; ++$i) {
            $current_item = $results[$i];

            if (Platform::SEARCH_TRACK === $type) {
                $picture = sprintf(self::PICTURE_PATH, $current_item->albumId);
                $musical_entity = new TrackEntity($current_item->name, new AlbumEntity($current_item->albumName, $current_item->artistName, $picture));
                $musical_entity->addLink(static::TAG, sprintf(self::WEB_LINK, $current_item->shortcut));
            } else /*if ($type === Platform::SEARCH_ALBUM)*/ {
                $picture = sprintf(self::PICTURE_PATH, $current_item->id);
                $musical_entity = new AlbumEntity($current_item->name, $current_item->artistName, $picture);
                $musical_entity->addLink(static::TAG, sprintf(self::WEB_LINK, $current_item->shortcut));
            }

            $musical_entities[] = new PlatformResult(['score' => Utils::indexScore($i)], $musical_entity);
        }

        return $musical_entities;
    }
}
