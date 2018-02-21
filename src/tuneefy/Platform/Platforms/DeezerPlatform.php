<?php

namespace tuneefy\Platform\Platforms;

use tuneefy\MusicalEntity\Entities\AlbumEntity;
use tuneefy\MusicalEntity\Entities\TrackEntity;
use tuneefy\Platform\Platform;
use tuneefy\Platform\PlatformException;
use tuneefy\Platform\PlatformResult;
use tuneefy\Platform\WebStreamingPlatformInterface;
use tuneefy\Utils\Utils;

class DeezerPlatform extends Platform implements WebStreamingPlatformInterface
{
    const NAME = 'Deezer';
    const HOMEPAGE = 'https://www.deezer.com/';
    const TAG = 'deezer';
    const COLOR = '181818';

    const API_ENDPOINT = 'https://api.deezer.com/';
    const API_METHOD = Platform::METHOD_GET;

    protected $endpoints = [
        Platform::LOOKUP_TRACK => self::API_ENDPOINT.'track/%s',
        Platform::LOOKUP_ALBUM => self::API_ENDPOINT.'album/%s',
        Platform::LOOKUP_ARTIST => self::API_ENDPOINT.'artist/%s',
        Platform::SEARCH_TRACK => self::API_ENDPOINT.'search/track',
        Platform::SEARCH_ALBUM => self::API_ENDPOINT.'search/album',
       // Platform::SEARCH_ARTIST => self::API_ENDPOINT . "search/artist"
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
        Platform::SEARCH_TRACK => ['nb_items' => Platform::LIMIT],
        Platform::SEARCH_ALBUM => ['nb_items' => Platform::LIMIT],
       // Platform::SEARCH_ARTIST => Map { "nb_items" => Platform::LIMIT }
    ];

    // NOT VALID ANYMORE http://www.deezer.com/listen-10236179
    // NOT VALID ANYMORE http://www.deezer.com/music/track/10240179
    // http://www.deezer.com/track/10444623
    const REGEX_DEEZER_TRACK = "/(?:listen-|music\/track\/|\/track\/)(?P<track_id>".Platform::REGEX_NUMERIC_ID.")[\/]?$/";
    // NOT VALID ANYMORE http://www.deezer.com/fr/music/rjd2/deadringer-144183
    // http://www.deezer.com/fr/album/955330
    const REGEX_DEEZER_ALBUM = "/\/album\/(?P<album_id>".Platform::REGEX_NUMERIC_ID.")[\/]?$/";
    // http://www.deezer.com/fr/artist/16948
    const REGEX_DEEZER_ARTIST = "/\/artist\/(?P<artist_id>".Platform::REGEX_FULLSTRING.")[\/]?$/";

    public function hasPermalink(string $permalink): bool
    {
        return false !== strpos($permalink, 'deezer.');
    }

    public function expandPermalink(string $permalink, int $mode): PlatformResult
    {
        $musical_entity = null;
        $query_words = [$permalink];

        $match = [];

        if (preg_match(self::REGEX_DEEZER_TRACK, $permalink, $match)) {
            $response = self::fetch($this, Platform::LOOKUP_TRACK, $match['track_id']);

            if (null === $response || property_exists($response->data, 'error')) {
                throw new PlatformException($this);
            }

            $entity = $response->data;
            $musical_entity = new TrackEntity($entity->title, new AlbumEntity($entity->album->title, $entity->artist->name, $entity->album->cover));
            $musical_entity->addLink(static::TAG, $permalink);

            $query_words = [
                $musical_entity->getAlbum()->getArtist(),
                $musical_entity->getSafeTitle(),
            ];
        } elseif (preg_match(self::REGEX_DEEZER_ALBUM, $permalink, $match)) {
            $response = self::fetch($this, Platform::LOOKUP_ALBUM, $match['album_id']);

            if (null === $response || property_exists($response->data, 'error')) {
                throw new PlatformException($this);
            }

            $entity = $response->data;
            $musical_entity = new AlbumEntity($entity->title, $entity->artist->name, $entity->cover);
            $musical_entity->addLink(static::TAG, $permalink);

            $query_words = [
                $musical_entity->getArtist(),
                $musical_entity->getSafeTitle(),
            ];
        } elseif (preg_match(self::REGEX_DEEZER_ARTIST, $permalink, $match)) {
            $response = self::fetch($this, Platform::LOOKUP_ARTIST, $match['artist_id']);

            if (null === $response || property_exists($response->data, 'error')) {
                throw new PlatformException($this);
            }

            $query_words = [$response->data->name];
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

        // We actually don't pass the limit to the fetch()
        // request since it's not really useful, in fact
        $length = min(count($entities->data), $limit ? $limit : Platform::LIMIT);

        $musical_entities = [];

        // Normalizing each track found
        for ($i = 0; $i < $length; ++$i) {
            $current_item = $entities->data[$i];

            if (Platform::SEARCH_TRACK === $type) {
                if (property_exists($current_item->album, 'cover')) {
                    $picture = $current_item->album->cover;
                } else {
                    $picture = $current_item->artist->picture;
                }

                $musical_entity = new TrackEntity($current_item->title, new AlbumEntity($current_item->album->title, $current_item->artist->name, $picture));
                $musical_entity->addLink(static::TAG, $current_item->link);
            } else /*if ($type === Platform::SEARCH_ALBUM)*/ {
                if (property_exists($current_item, 'cover')) {
                    $picture = $current_item->cover;
                } else {
                    $picture = $current_item->artist->picture;
                }

                $musical_entity = new AlbumEntity($current_item->title, $current_item->artist->name, $picture);
                $musical_entity->addLink(static::TAG, $current_item->link);
            }

            $musical_entities[] = new PlatformResult(['score' => Utils::indexScore($i)], $musical_entity);
        }

        return $musical_entities;
    }
}
