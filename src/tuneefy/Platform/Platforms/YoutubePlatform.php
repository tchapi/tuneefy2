<?php

namespace tuneefy\Platform\Platforms;

use tuneefy\MusicalEntity\Entities\AlbumEntity;
use tuneefy\MusicalEntity\Entities\TrackEntity;
use tuneefy\Platform\Platform;
use tuneefy\Platform\PlatformResult;
use tuneefy\Platform\WebStreamingPlatformInterface;
use tuneefy\Utils\Utils;

class YoutubePlatform extends Platform implements WebStreamingPlatformInterface
{
    const NAME = 'Youtube';
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

    const REGEX_YOUTUBE_ALL = "/\/watch\?v\=(?P<video_id>[a-zA-Z0-9\-\_]*)(|\&(.*))$/";

    public function hasPermalink(string $permalink): bool
    {
        return strpos($permalink, 'youtube.') !== false;
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

    public function expandPermalink(string $permalink, int $mode)//: ?PlatformResult
    {
        $musical_entity = null;
        $query_words = [$permalink];

        $match = [];

        if (preg_match(self::REGEX_YOUTUBE_ALL, $permalink, $match)) {
            $response = $this->fetchSync(Platform::LOOKUP_TRACK, $match['video_id']);

            if ($response === null || count($response->data->items) === 0) {
                return null;
            }

            $entity = $response->data->items[0];
            $musical_entity = new TrackEntity($entity->snippet->title, new AlbumEntity('', '', $entity->snippet->thumbnails->medium->url));
            $musical_entity->addLink(static::TAG, $this->getPermalinkFromTrackId($entity->id));

            $query_words = [$musical_entity->getSafeTitle()];
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

        if ($response === null || count($response->data->items) === 0) {
            return null;
        }
        $entities = $response->data->items;

        // We actually don't pass the limit to the fetch()
        // request since it's not really useful, in fact
        $length = min(count($entities), $limit ? $limit : Platform::LIMIT);

        $musical_entities = [];

        // Normalizing each track found
        for ($i = 0; $i < $length; ++$i) {
            $current_item = $entities[$i];

            if ($type === Platform::SEARCH_TRACK) {
                $musical_entity = new TrackEntity($current_item->snippet->title, new AlbumEntity('', '', $current_item->snippet->thumbnails->medium->url));
                $musical_entity->addLink(static::TAG, $this->getPermalinkFromTrackId($current_item->id->videoId));
                $musical_entities[] = new PlatformResult(['score' => Utils::indexScore($i)], $musical_entity);
            }
        }

        return $musical_entities;
    }
}
