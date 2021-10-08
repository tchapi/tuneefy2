<?php

namespace tuneefy\Platform\Platforms;

use tuneefy\MusicalEntity\Entities\AlbumEntity;
use tuneefy\MusicalEntity\Entities\TrackEntity;
use tuneefy\Platform\Platform;
use tuneefy\Platform\PlatformException;
use tuneefy\Platform\PlatformResult;
use tuneefy\Platform\WebStoreInterface;
use tuneefy\Utils\Utils;

class AmazonMusicPlatform extends Platform implements WebStoreInterface
{
    public const NAME = 'Amazon Music';
    public const HOMEPAGE = 'https://music.amazon.com/home';
    public const TAG = 'amazon';
    public const COLOR = 'E47911';

    // https://github.com/mattdennewitz/python-amazon-mp3-api
    public const API_ENDPOINT = 'https://www.amazon.com/gp/dmusic/aws/';
    public const API_METHOD = Platform::METHOD_GET;
    public const RETURN_CONTENT_TYPE = Platform::RETURN_XML;

    public const CURL_TIMEOUT = 3000;

    protected $endpoints = [
        Platform::LOOKUP_TRACK => self::API_ENDPOINT.'lookup.html',
        Platform::LOOKUP_ALBUM => self::API_ENDPOINT.'lookup.html',
        Platform::LOOKUP_ARTIST => self::API_ENDPOINT.'lookup.html',
        Platform::SEARCH_TRACK => self::API_ENDPOINT.'search.html',
        Platform::SEARCH_ALBUM => self::API_ENDPOINT.'search.html',
       // Platform::SEARCH_ARTIST => self::API_ENDPOINT . "search.html"
    ];
    protected $terms = [
        Platform::LOOKUP_TRACK => 'ASIN',
        Platform::LOOKUP_ALBUM => 'ASIN',
        Platform::LOOKUP_ARTIST => 'ASIN',
        Platform::SEARCH_TRACK => 'field-keywords',
        Platform::SEARCH_ALBUM => 'field-keywords',
       // Platform::SEARCH_ARTIST => "field-keywords"
    ];
    protected $options = [
        Platform::LOOKUP_TRACK => [],
        Platform::LOOKUP_ALBUM => [],
        Platform::LOOKUP_ARTIST => [],
        Platform::SEARCH_TRACK => ['type' => 'TRACK', 'ie' => 'UTF8', 'pagesize' => Platform::LIMIT],
        Platform::SEARCH_ALBUM => ['type' => 'ALBUM', 'ie' => 'UTF8', 'pagesize' => Platform::LIMIT],
       // Platform::SEARCH_ARTIST => Map { "type" => "ARTIST", "pagesize" => Platform::LIMIT }
    ];

    // http://www.amazon.com/gp/product/B00GLQQ07E/whatever
    // http://www.amazon.com/dp/B00GLQQ0JW/ref=dm_ws_tlw_trk1
    public const REGEX_AMAZON_ALL = "/\/(?:gp\/product|dp)\/(?P<asin>".Platform::REGEX_FULLSTRING.")[\/]?.*$/";

    public function hasPermalink(string $permalink): bool
    {
        return false !== strpos($permalink, 'amazon.');
    }

    protected function addContextOptions(?array $data, string $countryCode = null): array
    {
        $data['clientid'] = $this->key;

        return $data;
    }

    private function getPermalinkFromASIN(string $asin): string
    {
        /// Returns the global amazon.com link, by default...
        return sprintf('https://www.amazon.com/gp/product/%s', $asin);
    }

    public function expandPermalink(string $permalink, int $mode): PlatformResult
    {
        $musical_entity = null;
        $query_words = [$permalink];

        $match = [];

        if (preg_match(self::REGEX_AMAZON_ALL, $permalink, $match)) {
            $response = self::fetch($this, Platform::LOOKUP_TRACK, $match['asin']);

            if (null === $response) {
                throw new PlatformException($this);
            }

            if (property_exists($response->data, 'trackList')) { // It's a track then
                $entity = $response->data->trackList->track;
                $musical_entity = new TrackEntity($entity->title, new AlbumEntity($entity->album, $entity->creator, $entity->imageMedium));
                $musical_entity->addLink(static::TAG, $this->getPermalinkFromASIN($match['asin']));

                $query_words = [
                    $musical_entity->getAlbum()->getArtist(),
                    $musical_entity->getSafeTitle(),
                ];
            } elseif (property_exists($response->data, 'album')) { // It's an album
                $entity = $response->data->album;
                $musical_entity = new AlbumEntity($entity->title, $entity->creator, $entity->imageMedium);
                $musical_entity->addLink(static::TAG, $this->getPermalinkFromASIN($match['asin']));

                $query_words = [
                    $musical_entity->getArtist(),
                    $musical_entity->getSafeTitle(),
                ];
            } elseif (property_exists($response->data, 'artist')) {
                $query_words = [$response->data->artist->title];
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
        if (!property_exists($response->data->results, 'result') || 0 === $response->data->results->stats->totalCount) {
            return [];
        }

        $entities = $response->data->results->result;

        $entitiesCount = is_array($entities) ? count($entities ?? []) : 1;

        // We actually don't pass the limit to the fetch()
        // request since it's not really useful, in fact
        $length = min($entitiesCount, $limit ? $limit : Platform::LIMIT);

        $musical_entities = [];

        // Normalizing each track found
        for ($i = 0; $i < $length; ++$i) {
            if (1 == $response->data->results->stats->totalCount) {
                $entity = $entities;
            } else {
                $entity = $entities[$i];
            }
            if (Platform::SEARCH_TRACK === $type) {
                $current_item = $entity->track;

                $musical_entity = new TrackEntity($current_item->title, new AlbumEntity($current_item->album, $current_item->creator, $current_item->imageMedium));
                $musical_entity->addLink(static::TAG, $this->getPermalinkFromASIN($current_item->ASIN));
                $externalIds = [static::TAG => $current_item->ASIN];
            } else /*if ($type === Platform::SEARCH_ALBUM)*/ {
                $current_item = $entity->album;

                $musical_entity = new AlbumEntity($current_item->title, $current_item->creator, $current_item->imageMedium);
                $musical_entity->addLink(static::TAG, $this->getPermalinkFromASIN($current_item->ASIN));
                $externalIds = [static::TAG => $current_item->ASIN];
            }

            $musical_entities[] = new PlatformResult(['score' => Utils::indexScore($i), 'externalIds' => $externalIds], $musical_entity);
        }

        return $musical_entities;
    }
}
