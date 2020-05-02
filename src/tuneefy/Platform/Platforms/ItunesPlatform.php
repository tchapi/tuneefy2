<?php

namespace tuneefy\Platform\Platforms;

use tuneefy\MusicalEntity\Entities\AlbumEntity;
use tuneefy\MusicalEntity\Entities\TrackEntity;
use tuneefy\Platform\Platform;
use tuneefy\Platform\PlatformException;
use tuneefy\Platform\PlatformResult;
use tuneefy\Platform\WebStoreInterface;
use tuneefy\Utils\Utils;

class ItunesPlatform extends Platform implements WebStoreInterface
{
    const NAME = 'iTunes';
    const HOMEPAGE = 'https://itunes.apple.com';
    const TAG = 'itunes';
    const COLOR = '216be4';

    const API_ENDPOINT = 'https://itunes.apple.com/';
    const API_METHOD = Platform::METHOD_GET;

    protected $endpoints = [
        Platform::LOOKUP_TRACK => null,
        Platform::LOOKUP_ALBUM => self::API_ENDPOINT.'lookup',
        Platform::LOOKUP_ARTIST => self::API_ENDPOINT.'lookup',
        Platform::SEARCH_TRACK => self::API_ENDPOINT.'search/track',
        Platform::SEARCH_ALBUM => self::API_ENDPOINT.'search/album',
       // Platform::SEARCH_ARTIST => self::API_ENDPOINT . "search/artist"
    ];
    protected $terms = [
        Platform::LOOKUP_TRACK => null,
        Platform::LOOKUP_ALBUM => 'id',
        Platform::LOOKUP_ARTIST => 'id',
        Platform::SEARCH_TRACK => 'term',
        Platform::SEARCH_ALBUM => 'term',
       // Platform::SEARCH_ARTIST => "term"
    ];
    protected $options = [
        Platform::LOOKUP_TRACK => [],
        Platform::LOOKUP_ALBUM => [],
        Platform::LOOKUP_ARTIST => [],
        Platform::SEARCH_TRACK => ['media' => 'music', 'entity' => 'song', 'limit' => Platform::LIMIT],
        Platform::SEARCH_ALBUM => ['media' => 'music', 'entity' => 'album', 'limit' => Platform::LIMIT],
       // Platform::SEARCH_ARTIST => Map { "media" => "music", "entity" => "musicArtist", "limit" => Platform::LIMIT }
    ];

    // https://itunes.apple.com/us/artist/jack-johnson/id909253
    const REGEX_ITUNES_ARTIST = "/\/artist\/(?P<artist_name>".Platform::REGEX_FULLSTRING.")\/(id)?(?P<artist_id>".Platform::REGEX_NUMERIC_ID.")[\/]?$/";

    // https://itunes.apple.com/us/album/weezer/id1136784464
    const REGEX_ITUNES_ALBUM = "/\/album\/(?P<album_name>".Platform::REGEX_FULLSTRING.")\/(id)?(?P<album_id>".Platform::REGEX_NUMERIC_ID.")[\/]?$/";

    public function hasPermalink(string $permalink): bool
    {
        return false !== strpos($permalink, 'music.apple.') || false !== strpos($permalink, 'itunes.apple.');
    }

    public function expandPermalink(string $permalink, int $mode): PlatformResult
    {
        $musical_entity = null;
        $query_words = [$permalink];

        $match = [];

        if (preg_match(self::REGEX_ITUNES_ALBUM, $permalink, $match)) {
            $response = self::fetch($this, Platform::LOOKUP_ALBUM, $match['album_id']);

            if (null === $response) {
                throw new PlatformException($this);
            }

            if (intval($response->data->resultCount) > 0) {
                $entity = $response->data->results[0];
                $musical_entity = new AlbumEntity($entity->collectionName, $entity->artistName, $entity->artworkUrl100);
                $musical_entity->addLink(static::TAG, $entity->collectionViewUrl);

                $query_words = [
                    $musical_entity->getArtist(),
                    $musical_entity->getSafeTitle(),
                ];
            }
        } elseif (preg_match(self::REGEX_ITUNES_ARTIST, $permalink, $match)) {
            $response = self::fetch($this, Platform::LOOKUP_ARTIST, $match['artist_id']);

            if (null === $response) {
                throw new PlatformException($this);
            }

            if (intval($response->data->resultCount) > 0) {
                $query_words = [$response->data->results[0]->artistName];
            }
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
        $entities = $response->data->results;

        // We actually don't pass the limit to the fetch()
        // request since it's not really useful, in fact
        $length = min(intval($response->data->resultCount), $limit ? $limit : Platform::LIMIT);

        $musical_entities = [];

        // Normalizing each track found
        for ($i = 0; $i < $length; ++$i) {
            $current_item = $entities[$i];

            if (Platform::SEARCH_TRACK === $type) {
                $musical_entity = new TrackEntity($current_item->trackName, new AlbumEntity($current_item->collectionName, $current_item->artistName, $current_item->artworkUrl100));
                $musical_entity->addLink(static::TAG, $current_item->trackViewUrl);
            } else /*if ($type === Platform::SEARCH_ALBUM)*/ {
                $musical_entity = new AlbumEntity($current_item->collectionName, $current_item->artistName, $current_item->artworkUrl100);
                $musical_entity->addLink(static::TAG, $current_item->collectionViewUrl);
          }

            $musical_entities[] = new PlatformResult(['score' => Utils::indexScore($i)], $musical_entity);
        }

        return $musical_entities;
    }
}
