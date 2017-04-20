<?php

namespace tuneefy\Platform\Platforms;

use tuneefy\MusicalEntity\Entities\AlbumEntity;
use tuneefy\MusicalEntity\Entities\TrackEntity;
use tuneefy\Platform\Platform;
use tuneefy\Platform\PlatformResult;
use tuneefy\Platform\WebStreamingPlatformInterface;
use tuneefy\Utils\Utils;

class BeatsMusicPlatform extends Platform implements WebStreamingPlatformInterface
{
    const NAME = 'Beats Music';
    const TAG = 'beats';
    const COLOR = 'E31937';

    const API_ENDPOINT = 'https://partner.api.beatsmusic.com/v1/api/';
    const API_METHOD = Platform::METHOD_GET;

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

    // http://on.beatsmusic.com/albums/al8992411/tracks/tr8992441
    // http://on.beatsmusic.com/artists/ar27304
    // http://on.beatsmusic.com/albums/al6960443
    const REGEX_BEATS_TRACK = "/albums\/(?P<album_id>al".Platform::REGEX_NUMERIC_ID.")\/tracks\/(?P<track_id>tr".Platform::REGEX_NUMERIC_ID.")[\/]?$/";
    const REGEX_BEATS_ALBUM = "/albums\/(?P<album_id>al".Platform::REGEX_NUMERIC_ID.")[\/]?$/";
    const REGEX_BEATS_ARTIST = "/artists\/(?P<artist_id>ar".Platform::REGEX_NUMERIC_ID.")[\/]?$/";

    public function hasPermalink(string $permalink): bool
    {
        return strpos($permalink, 'beatsmusic.com') !== false;
    }

    private function getCoverUrlFromAlbumId(string $album_id): string
    {
        // http://mn.ec.cdn.beatsmusic.com/albums/008/992/411/m.jpeg
        // s = small, m = medium, b = large, g = large
        $padded_id = str_pad(substr($album_id, 2), 9, '0', STR_PAD_LEFT);

        return sprintf('http://mn.ec.cdn.beatsmusic.com/albums/%s/%s/%s/g.jpeg', substr($padded_id, 0, 3), substr($padded_id, 3, 3), substr($padded_id, -3));
    }

    protected function addContextOptions(array $data): array
    {
        $data['client_id'] = $this->key;

        return $data;
    }

    public function expandPermalink(string $permalink, int $mode)//: ?PlatformResult
    {
        $musical_entity = null;
        $query_words = [$permalink];

        $match = [];

        if (preg_match(self::REGEX_BEATS_TRACK, $permalink, $match)) {
            $response = $this->fetchSync(Platform::LOOKUP_TRACK, $match['track_id']);

            if ($response === null || !property_exists($response->data, 'data')) {
                return null;
            }

            $entity = $response->data;
            $musical_entity = new TrackEntity($entity->data->title, new AlbumEntity($entity->data->refs->album->display, $entity->data->artist_display_name, $this->getCoverUrlFromAlbumId($match['album_id'])));
            $musical_entity->addLink(static::TAG, $permalink);

            $query_words = [
                $musical_entity->getAlbum()->getArtist(),
                $musical_entity->getSafeTitle(),
            ];
        } elseif (preg_match(self::REGEX_BEATS_ALBUM, $permalink, $match)) {
            $response = $this->fetchSync(Platform::LOOKUP_ALBUM, $match['album_id']);

            if ($response === null || !property_exists($response->data, 'data')) {
                return null;
            }

            $entity = $response->data;
            $musical_entity = new AlbumEntity($entity->title, $entity->artist->name, $entity->cover);
            $musical_entity->addLink(static::TAG, $permalink);

            $query_words = [
                $musical_entity->getArtist(),
                $musical_entity->getSafeTitle(),
            ];
        } elseif (preg_match(self::REGEX_BEATS_ARTIST, $permalink, $match)) {
            $response = $this->fetchSync(Platform::LOOKUP_ARTIST, $match['artist_id']);

            if ($response === null || !property_exists($response->data, 'data')) {
                return null;
            }

            $query_words = [$response->data->name];
        }

        // Consolidate results
        $metadata = ['query_words' => $query_words];

        if ($musical_entity !== null) {
            $metadata['platform'] = $this->getName();
        }

        return new PlatformResult($metadata, $musical_entity);
    }

    public function search(int $type, string $query, int $limit, int $mode)//: Awaitable<?Vector<PlatformResult>>
    {
        $response = $this->fetchSync($type, $query);

        if ($response === null || count($response->data->data) === 0) {
            return null;
        }
        $entities = $response->data;

        // We actually don't pass the limit to the fetch()
        // request since it's not really useful, in fact
        $length = min(count($entities->data), $limit ? $limit : Platform::LIMIT);

        $musical_entities = [];

        // Normalizing each track found
        for ($i = 0; $i < $length; ++$i) {
            $current_item = $entities->data[$i];

            if ($type === Platform::SEARCH_TRACK) {
                $musical_entity = new TrackEntity($current_item->display, new AlbumEntity($current_item->related->display, $current_item->detail, $this->getCoverUrlFromAlbumId($current_item->related->id)));
                $musical_entity->addLink(static::TAG, sprintf('http://on.beatsmusic.com/albums/%s/tracks/%s', $current_item->related->id, $current_item->id));
            } else /*if ($type === Platform::SEARCH_ALBUM)*/ {
                $musical_entity = new AlbumEntity($current_item->display, $current_item->detail, $this->getCoverUrlFromAlbumId($current_item->id));
                $musical_entity->addLink(static::TAG, sprintf('http://on.beatsmusic.com/albums/%s', $current_item->id));
            }

            $musical_entities[] = new PlatformResult(['score' => Utils::indexScore($i)], $musical_entity);
        }

        return $musical_entities;
    }
}
