<?php

namespace tuneefy\Platform\Platforms;

use tuneefy\MusicalEntity\Entities\AlbumEntity;
use tuneefy\MusicalEntity\Entities\TrackEntity;
use tuneefy\Platform\Platform;
use tuneefy\Platform\PlatformException;
use tuneefy\Platform\PlatformResult;
use tuneefy\Platform\WebStreamingPlatformInterface;
use tuneefy\Utils\Utils;

class YoutubePlatform extends Platform implements WebStreamingPlatformInterface
{
    const NAME = 'Youtube';
    const HOMEPAGE = 'https://youtube.com/';
    const TAG = 'youtube';
    const COLOR = 'c8120b';

    const API_ENDPOINT = 'https://www.googleapis.com/youtube/v3/';
    const API_METHOD = Platform::METHOD_GET;

    protected $endpoints = [
        Platform::LOOKUP_TRACK => self::API_ENDPOINT.'videos',
        Platform::LOOKUP_ALBUM => null,
        Platform::LOOKUP_ARTIST => null,
        Platform::SEARCH_TRACK => self::API_ENDPOINT.'search',
        Platform::SEARCH_ALBUM => null,
       // Platform::SEARCH_ARTIST => null
    ];
    protected $terms = [
        Platform::LOOKUP_TRACK => 'id',
        Platform::LOOKUP_ALBUM => null,
        Platform::LOOKUP_ARTIST => null,
        Platform::SEARCH_TRACK => 'q',
        Platform::SEARCH_ALBUM => null,
       // Platform::SEARCH_ARTIST => null
    ];
    protected $options = [
        Platform::LOOKUP_TRACK => ['part' => 'snippet'],
        Platform::LOOKUP_ALBUM => [],
        Platform::LOOKUP_ARTIST => [],
        Platform::SEARCH_TRACK => ['part' => 'snippet', 'order' => 'relevance', 'topicId' => '/m/04rlf', 'type' => 'video', 'videoCategoryId' => '10', 'maxResults' => Platform::LIMIT], // Music category
        Platform::SEARCH_ALBUM => [],
       // Platform::SEARCH_ARTIST => []
    ];

    // https://www.youtube.com/watch?v=FNdC_3LR2AI
    const REGEX_YOUTUBE_ALL = "/\/watch\?v\=(?P<video_id>[a-zA-Z0-9\-\_]*)(|\&(.*))$/";

    public function hasPermalink(string $permalink): bool
    {
        return false !== strpos($permalink, 'youtube.');
    }

    protected function addContextOptions(array $data): array
    {
        $data['key'] = $this->key;

        return $data;
    }

    private function getPermalinkFromTrackId(string $video_id): string
    {
        return sprintf('https://www.youtube.com/watch?v=%s', $video_id);
    }

    public function expandPermalink(string $permalink, int $mode): PlatformResult
    {
        $musical_entity = null;
        $query_words = [$permalink];

        $match = [];

        if (preg_match(self::REGEX_YOUTUBE_ALL, $permalink, $match)) {
            $response = self::fetch($this, Platform::LOOKUP_TRACK, $match['video_id']);

            if (null === $response) {
                throw new PlatformException($this);
            }

            if (count($response->data->items) > 0) {
                $entity = $response->data->items[0];

                // Extract title and author
                list($title, $artist) = $this->parseYoutubeMusicVideoTitle($entity->snippet->title);
                if (null !== $title && null !== $artist) {
                    $musical_entity = new TrackEntity($title, new AlbumEntity('', $artist, $entity->snippet->thumbnails->medium->url));

                    $musical_entity->addLink(static::TAG, $this->getPermalinkFromTrackId($entity->id));

                    $query_words = [
                        $musical_entity->getSafeTitle(),
                        $musical_entity->getAlbum()->getArtist(),
                    ];
                } else {
                    $query_words = [$entity->snippet->title];
                }
            }
        }

        // Consolidate results
        $metadata = ['query_words' => $query_words];

        if (null !== $musical_entity) {
            $metadata['platform'] = $this->getName();
        }

        return new PlatformResult($metadata, $musical_entity);
    }

    // We want title like "ARTIST - TITLE [Official Video]" only
    private function parseYoutubeMusicVideoTitle(string $string): array
    {
        $parts = explode(' - ', $string);

        // Check if parts[2] is something like "official video"
        if (count($parts) > 1) {
            // Inspired by https://github.com/tomahawk-player/tomahawk-resolvers/blob/master/youtube/content/contents/code/youtube.js#L578
            if (preg_match("/(?P<title>[^\(^\[]*)(?:[\(\[].*?(?:offici(?:a|e)l|clip).*?(?:[\)\]])|(?:(?:offici(?:a|e)l|video)).*?(?:video|clip))/iu", $parts[1], $matches)) {
                $title = trim($matches['title']);

                return [$title, trim($parts[0])];
            }
        }

        return [null, null];
    }

    public function extractSearchResults(\stdClass $response, int $type, string $query, int $limit, int $mode): array
    {
        $entities = $response->data->items;

        // We actually don't pass the limit to the fetch()
        // request since it's not really useful, in fact
        $length = min(count($entities), $limit ? $limit : Platform::LIMIT);

        $musical_entities = [];

        // Normalizing each track found
        for ($i = 0; $i < $length; ++$i) {
            $current_item = $entities[$i];

            if (Platform::SEARCH_TRACK === $type) {
                // Extract title and author
                list($title, $artist) = $this->parseYoutubeMusicVideoTitle($current_item->snippet->title);
                if (null === $title || null === $artist) {
                    continue;
                }

                $musical_entity = new TrackEntity($title, new AlbumEntity('', $artist, $current_item->snippet->thumbnails->medium->url));
                $musical_entity->addLink(static::TAG, $this->getPermalinkFromTrackId($current_item->id->videoId));
                $musical_entities[] = new PlatformResult(['score' => Utils::indexScore($i)], $musical_entity);
            }
        }

        return $musical_entities;
    }
}
