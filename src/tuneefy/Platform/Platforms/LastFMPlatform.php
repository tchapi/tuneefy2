<?php

namespace tuneefy\Platform\Platforms;

use tuneefy\MusicalEntity\Entities\AlbumEntity;
use tuneefy\MusicalEntity\Entities\TrackEntity;
use tuneefy\Platform\Platform;
use tuneefy\Platform\PlatformResult;
use tuneefy\Platform\ScrobblingPlatformInterface;
use tuneefy\Utils\Utils;

class LastFMPlatform extends Platform implements ScrobblingPlatformInterface
{
    const NAME = 'Last.fm';
    const TAG = 'lastfm';
    const COLOR = 'e41c1c';

    const API_ENDPOINT = 'http://ws.audioscrobbler.com/2.0/';
    const API_METHOD = Platform::METHOD_GET;

    protected $endpoints = [
        Platform::LOOKUP_TRACK => self::API_ENDPOINT,
        Platform::LOOKUP_ALBUM => self::API_ENDPOINT,
        Platform::LOOKUP_ARTIST => self::API_ENDPOINT,
        Platform::SEARCH_TRACK => self::API_ENDPOINT,
        Platform::SEARCH_ALBUM => self::API_ENDPOINT,
       // Platform::SEARCH_ARTIST => self::API_ENDPOINT
    ];
    protected $terms = [
        Platform::LOOKUP_TRACK => 'track',
        Platform::LOOKUP_ALBUM => 'album',
        Platform::LOOKUP_ARTIST => 'artist',
        Platform::SEARCH_TRACK => 'track',
        Platform::SEARCH_ALBUM => 'album',
       // Platform::SEARCH_ARTIST => "artist"
    ];
    protected $options = [
        Platform::LOOKUP_TRACK => ['format' => 'json', 'autocorrect' => 1, 'method' => 'track.getinfo', 'artist' => '%s'],
        Platform::LOOKUP_ALBUM => ['format' => 'json', 'autocorrect' => 1, 'method' => 'album.getinfo', 'artist' => '%s'],
        Platform::LOOKUP_ARTIST => ['format' => 'json', 'autocorrect' => 1, 'method' => 'artist.getinfo'],
        Platform::SEARCH_TRACK => ['format' => 'json', 'autocorrect' => 1, 'method' => 'track.search', 'limit' => Platform::LIMIT],
        Platform::SEARCH_ALBUM => ['format' => 'json', 'autocorrect' => 1, 'method' => 'album.search', 'limit' => Platform::LIMIT],
       // Platform::SEARCH_ARTIST => ["format" => "json", "autocorrect" => 1, "method" => "artist.search", "limit" => Platform::LIMIT ]
    ];

    // http://www.lastfm.fr/music/The+Clash/London+Calling/London+Calling
    const REGEX_LASTFM_TRACK = "/music\/(?P<artist_slug>".Platform::REGEX_FULLSTRING.")\/(?P<album_slug>".Platform::REGEX_FULLSTRING.")\/(?P<track_slug>".Platform::REGEX_FULLSTRING.")[\/]?$/";
    // http://www.lastfm.fr/music/The+Clash/London+Calling
    const REGEX_LASTFM_ALBUM = "/music\/(?P<artist_slug>".Platform::REGEX_FULLSTRING.")\/(?P<album_slug>".Platform::REGEX_FULLSTRING.")[\/]?$/";
    // http://www.lastfm.fr/music/Sex+Pistols
    const REGEX_LASTFM_ARTIST = "/music\/(?P<artist_slug>".Platform::REGEX_FULLSTRING.")[\/]?$/";

    public function hasPermalink(string $permalink): bool
    {
        return strpos($permalink, 'lastfm.') !== false || strpos($permalink, 'last.fm') !== false;
    }

    protected function addContextOptions(array $data): array
    {
        $data['api_key'] = $this->key;

        return $data;
    }

    public function expandPermalink(string $permalink, int $mode)//: ?PlatformResult
    {
        $musical_entity = null;
        $query_words = [$permalink];

        $match = [];

        if (preg_match(self::REGEX_LASTFM_TRACK, $permalink, $match)) {
            // This is a bit dirty, I must admit.
            $this->options[Platform::LOOKUP_TRACK]['artist'] = $match['artist_slug'];
            $response = $this->fetchSync(Platform::LOOKUP_TRACK, $match['track_slug']);

            if ($response === null || property_exists($response->data, 'error')) {
                return null;
            }

            $entity = $response->data->track;

            if (property_exists($entity->album, 'image')) {
                $picture = get_object_vars($entity->album->image[2]);
                $picture = $picture['#text'];
            } else {
                $picture = '';
            }

            $musical_entity = new TrackEntity($entity->name, new AlbumEntity($entity->album->title, $entity->artist->name, $picture));
            $musical_entity->addLink(static::TAG, $entity->url);

            $query_words = [
                $musical_entity->getAlbum()->getArtist(),
                $musical_entity->getSafeTitle(),
            ];
        } elseif (preg_match(self::REGEX_LASTFM_ALBUM, $permalink, $match)) {
            // This is a bit dirty, I must admit.
            $this->options[Platform::LOOKUP_ALBUM]['artist'] = $match['artist_slug'];
            $response = $this->fetchSync(Platform::LOOKUP_ALBUM, $match['album_slug']);

            if ($response === null || property_exists($response->data, 'error')) {
                return null;
            }

            $entity = $response->data->album;

            if (property_exists($entity, 'image')) {
                $picture = get_object_vars($entity->image[2]);
                $picture = $picture['#text'];
            } else {
                $picture = '';
            }

            $musical_entity = new AlbumEntity($entity->name, $entity->artist, $picture);
            $musical_entity->addLink(static::TAG, $entity->url);

            $query_words = [
                $musical_entity->getArtist(),
                $musical_entity->getSafeTitle(),
            ];
        } elseif (preg_match(self::REGEX_LASTFM_ARTIST, $permalink, $match)) {
            $response = $this->fetchSync(Platform::LOOKUP_ARTIST, $match['artist_slug']);

            if ($response === null || property_exists($response->data, 'error')) {
                return null;
            }

            $query_words = [$response->data->artist->name];
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
        $response = $this->fetch($type, $query);

        if ($response === null) {
            return null;
        }
        $entities = $response->data;

        switch ($type) {
            case Platform::SEARCH_TRACK:
                $results = $entities->results->trackmatches->track;
                break;
            case Platform::SEARCH_ALBUM:
                $results = $entities->results->albummatches->album;
                break;
        }

        // We actually don't pass the limit to the fetch()
        // request since it's not really useful, in fact
        $length = min(count($results), $limit ? $limit : Platform::LIMIT);

        $musical_entities = [];

        // Normalizing each track found
        for ($i = 0; $i < $length; ++$i) {
            $current_item = $results[$i];

            if ($type === Platform::SEARCH_TRACK) {
                if (property_exists($current_item, 'image')) {
                    $picture = get_object_vars($current_item->image[2]);
                    $picture = $picture['#text'];
                } else {
                    $picture = '';
                }

                $musical_entity = new TrackEntity($current_item->name, new AlbumEntity('', $current_item->artist, $picture));
                $musical_entity->addLink(static::TAG, $current_item->url);
            } else /*if ($type === Platform::SEARCH_ALBUM)*/ {
                if (property_exists($current_item, 'image')) {
                    $picture = get_object_vars($current_item->image[2]);
                    $picture = $picture['#text'];
                } else {
                    $picture = '';
                }

                $musical_entity = new AlbumEntity($current_item->name, $current_item->artist, $picture);
                $musical_entity->addLink(static::TAG, $current_item->url);
            }

            $musical_entities[] = new PlatformResult(['score' => Utils::indexScore($i)], $musical_entity);
        }

        return $musical_entities;
    }
}
