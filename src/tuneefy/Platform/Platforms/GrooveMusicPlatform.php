<?php

namespace tuneefy\Platform\Platforms;

use tuneefy\MusicalEntity\Entities\AlbumEntity;
use tuneefy\MusicalEntity\Entities\TrackEntity;
use tuneefy\Platform\Platform;
use tuneefy\Platform\PlatformException;
use tuneefy\Platform\PlatformResult;
use tuneefy\Platform\WebStreamingPlatformInterface;
use tuneefy\Utils\Utils;

class GrooveMusicPlatform extends Platform implements WebStreamingPlatformInterface
{
    const NAME = 'Groove Music';
    const HOMEPAGE = 'https://music.microsoft.com/';
    const TAG = 'groove';
    const COLOR = '007500';

    const API_ENDPOINT = 'https://music.xboxlive.com/1/content/';
    const API_METHOD = Platform::METHOD_GET;

    protected $endpoints = [
        Platform::LOOKUP_TRACK => self::API_ENDPOINT.'music.%s/lookup',
        Platform::LOOKUP_ALBUM => self::API_ENDPOINT.'music.%s/lookup',
        Platform::LOOKUP_ARTIST => self::API_ENDPOINT.'music.%s/lookup',
        Platform::SEARCH_TRACK => self::API_ENDPOINT.'music/search',
        Platform::SEARCH_ALBUM => self::API_ENDPOINT.'music/search',
       // Platform::SEARCH_ARTIST => self::API_ENDPOINT . "music/search"
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
        Platform::LOOKUP_TRACK => ['contentType' => 'json'],
        Platform::LOOKUP_ALBUM => ['contentType' => 'json'],
        Platform::LOOKUP_ARTIST => ['contentType' => 'json'],
        Platform::SEARCH_TRACK => ['contentType' => 'json', 'filters' => 'tracks', 'maxItems' => Platform::LIMIT],
        Platform::SEARCH_ALBUM => ['contentType' => 'json', 'filters' => 'albums', 'maxItems' => Platform::LIMIT],
       // Platform::SEARCH_ARTIST => Map { "contentType" => "json", "filters" => "artists", "maxItems" => Platform::LIMIT }
    ];

    // http://music.xbox.com/Album/C954F807-0100-11DB-89CA-0019B92A3933
    const REGEX_GROOVE_ALBUM = "/Album\/(?<album_id>".Platform::REGEX_FULLSTRING.").*[\/]?$/";
    // http://music.xbox.com/Track/87CF3706-0100-11DB-89CA-0019B92A3933
    const REGEX_GROOVE_TRACK = "/Track\/(?<track_id>".Platform::REGEX_FULLSTRING.").*[\/]?$/";
    // Artist ??

    public function hasPermalink(string $permalink): bool
    {
        return strpos($permalink, 'music.xbox.') !== false;
    }

    protected function addContextHeaders(): array
    {
        // From the XBOX docs : http://msdn.microsoft.com/en-us/library/dn546688.aspx
        // and then from https://docs.microsoft.com/en-us/groove/getting-started
        $serviceauth = 'https://login.live.com/accesstoken.srf';
        $scope = 'app.music.xboxlive.com';
        $grantType = 'client_credentials';

        $requestData = ['client_id' => $this->key, 'client_secret' => $this->secret, 'scope' => $scope, 'grant_type' => $grantType];

        $ch = curl_init();
        curl_setopt_array($ch, [
          CURLOPT_URL => $serviceauth,
          CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
          CURLOPT_POST => 1,
          CURLOPT_RETURNTRANSFER => 1,
          CURLOPT_POSTFIELDS => http_build_query($requestData),
        ]);

        $result = json_decode(curl_exec($ch), true);
        curl_close($ch);

        return ['Authorization: Bearer '.$result['access_token']];
    }

    public function expandPermalink(string $permalink, int $mode): PlatformResult
    {
        $musical_entity = null;
        $query_words = [$permalink];

        $match = [];

        if (preg_match(self::REGEX_GROOVE_TRACK, $permalink, $match)) {
            $response = $this->fetchSync(Platform::LOOKUP_TRACK, $match['track_id']);

            if ($response === null || property_exists($response->data, 'Error')) {
                throw new PlatformException($this);
            }

            $entity = $response->data->Tracks->Items[0];
            $musical_entity = new TrackEntity($entity->Name, new AlbumEntity($entity->Album->Name, $entity->Artists[0]->Artist->Name, $entity->Album->ImageUrl));
            $musical_entity->addLink(static::TAG, $entity->Link);

            $query_words = [
                $musical_entity->getAlbum()->getArtist(),
                $musical_entity->getSafeTitle(),
            ];
        } elseif (preg_match(self::REGEX_GROOVE_ALBUM, $permalink, $match)) {
            $response = $this->fetchSync(Platform::LOOKUP_ALBUM, $match['album_id']);

            if ($response === null || property_exists($response->data, 'Error')) {
                throw new PlatformException($this);
            }

            $entity = $response->data->Albums->Items[0];
            $musical_entity = new AlbumEntity($entity->Name, $entity->Artists[0]->Artist->Name, $entity->ImageUrl);
            $musical_entity->addLink(static::TAG, $entity->Link);

            $query_words = [
                $musical_entity->getArtist(),
                $musical_entity->getSafeTitle(),
            ];
        }

        // Consolidate results
        $metadata = ['query_words' => $query_words];

        if ($musical_entity !== null) {
            $metadata['platform'] = $this->getName();
        }

        return new PlatformResult($metadata, $musical_entity);
    }

    public function search(int $type, string $query, int $limit, int $mode): array
    {
        $response = $this->fetchSync($type, $query);

        if ($response === null || property_exists($response->data, 'Error')) {
            throw new PlatformException($this);
        }
        $entities = $response->data;

        switch ($type) {
            case Platform::SEARCH_TRACK:
                $results = $entities->Tracks->Items;
                break;
            case Platform::SEARCH_ALBUM:
                $results = $entities->Albums->Items;
                break;
        }
        $length = min(count($results), $limit ? $limit : Platform::LIMIT);

        $musical_entities = [];

        // Normalizing each track found
        for ($i = 0; $i < $length; ++$i) {
            $current_item = $results[$i];

            if ($type === Platform::SEARCH_TRACK) {
                $musical_entity = new TrackEntity($current_item->Name, new AlbumEntity($current_item->Album->Name, $current_item->Artists[0]->Artist->Name, $current_item->ImageUrl));
                $musical_entity->addLink(static::TAG, $current_item->Link);
            } else /*if ($type === Platform::SEARCH_ALBUM)*/ {
                $musical_entity = new AlbumEntity($current_item->Name, $current_item->Artists[0]->Artist->Name, $current_item->ImageUrl);
                $musical_entity->addLink(static::TAG, $current_item->Link);
          }

            $musical_entities[] = new PlatformResult(['score' => Utils::indexScore($i)], $musical_entity);
        }

        return $musical_entities;
    }
}
