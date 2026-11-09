<?php

namespace App\Services\Platforms;

use App\Dataclass\MusicalEntity\Entities\Album;
use App\Dataclass\MusicalEntity\Entities\Track;
use App\Services\Platforms\Interfaces\WebStreamingPlatformInterface;
use App\Utils\Utils;

class YoutubePlatform extends Platform implements WebStreamingPlatformInterface
{
    protected $default = false;
    protected $enables = ['api' => true, 'website' => true];
    protected $capabilities = ['track_search' => true, 'album_search' => false, 'lookup' => true];

    public const NAME = 'Youtube';
    public const HOMEPAGE = 'https://youtube.com/';
    public const TAG = 'youtube';
    public const COLOR = 'c8120b';

    // https://developers.google.com/youtube/v3/docs/search
    public const API_ENDPOINT = 'https://www.googleapis.com/youtube/v3/';
    public const API_METHOD = Platform::METHOD_GET;

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
    public const REGEX_YOUTUBE_ALL = "/\/watch\?v\=(?P<video_id>[a-zA-Z0-9\-\_]*)(|\&(.*))$/";

    #[\Override]
    public function hasPermalink(string $permalink): bool
    {
        return false !== strpos($permalink, 'youtube.');
    }

    #[\Override]
    protected function addContextOptions(?array $data, ?string $countryCode = null): array
    {
        $data['key'] = $this->key;
        $data['regionCode'] = strtolower($countryCode ?: self::DEFAULT_COUNTRY_CODE);

        return $data;
    }

    private function getPermalinkFromTrackId(string $video_id): string
    {
        return sprintf('https://www.youtube.com/watch?v=%s', $video_id);
    }

    #[\Override]
    public function expandPermalink(string $permalink, int $mode): PlatformResult
    {
        $musical_entity = null;
        $query_words = [$permalink];

        $match = [];

        if (preg_match(self::REGEX_YOUTUBE_ALL, $permalink, $match)) {
            $response = self::fetch($this, Platform::LOOKUP_TRACK, $match['video_id']);

            if (property_exists($response->data, 'error')) {
                throw new PlatformException($this, $response->data->error->message);
            }

            if (count($response->data->items) > 0) {
                $entity = $response->data->items[0];

                // Extract title and author
                list($title, $artist) = $this->parseYoutubeMusicVideoTitle($entity->snippet->title);
                if (null !== $title && null !== $artist) {
                    $musical_entity = new Track(new Album('', $artist, $entity->snippet->thumbnails->medium->url), $title);

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
    public function parseYoutubeMusicVideoTitle(string $string): array
    {
        // "ARTIST - TITLE", possibly followed by qualifiers: "(Official Video)", "[Official Audio]",
        // "(Clip officiel)", "(Lyrics)", "Official Video", ...
        $parts = preg_split('/\s+[-\x{2013}\x{2014}]\s+/u', $string, 2);

        if (count($parts) < 2) {
            return [null, null];
        }

        $artist = trim($parts[0]);
        $title = trim($parts[1]);

        // Bracketed qualifiers at the end of the title, as many as there are. Other bracketed
        // parts ("(Live at ...)", "(Remix)") are kept: the Track itself interprets them.
        $qualifier = '(?:offici|clip|video|audio|lyric|visuali[sz]er|hd|4k)';
        while (preg_match('/^(?P<title>.*\S)\s*[\(\[][^\)\]]*'.$qualifier.'[^\)\]]*[\)\]]\s*$/iu', $title, $matches)) {
            $title = $matches['title'];
        }

        // Unbracketed "official video" style suffixes ("Video Games" alone is a title, not a suffix)
        $title = preg_replace('/\s+(?:(?:offici(?:a|e)l|official)\s+(?:music\s+)?(?:video|audio|clip|lyric\s+video)|(?:clip|vid[ée]o)\s+offici(?:a|e)l(?:le)?)\s*$/iu', '', $title);

        $title = trim($title);

        if ('' === $title || '' === $artist) {
            return [null, null];
        }

        return [$title, $artist];
    }

    #[\Override]
    public function extractSearchResults(\stdClass $response, int $type, string $query, int $limit, int $mode): array
    {
        // Catch errors (invalid key, quota exceeded, ...)
        if (property_exists($response->data, 'error')) {
            throw new PlatformException($this, $response->data->error->message ?? null);
        }

        $entities = $response->data->items ?? [];
        $limit = $limit ? $limit : Platform::LIMIT;

        $musical_entities = [];

        // Normalizing each track found. Videos whose title cannot be parsed are skipped,
        // so every fetched item is considered until $limit tracks have been found.
        foreach ($entities as $i => $current_item) {
            if (count($musical_entities) >= $limit) {
                break;
            }

            if (Platform::SEARCH_TRACK === $type) {
                // Extract title and author
                list($title, $artist) = $this->parseYoutubeMusicVideoTitle($current_item->snippet->title ?? '');
                if (null === $title || null === $artist) {
                    continue;
                }

                $musical_entity = new Track(new Album('', $artist, $current_item->snippet->thumbnails->medium->url), $title);
                $musical_entity->addLink(static::TAG, $this->getPermalinkFromTrackId($current_item->id->videoId));
                $musical_entities[] = new PlatformResult(['score' => Utils::indexScore($i), 'externalIds' => [static::TAG => $current_item->id->videoId]], $musical_entity);
            }
        }

        return $musical_entities;
    }
}
