<?php

namespace tuneefy\Platform\Platforms;

use tuneefy\MusicalEntity\Entities\AlbumEntity;
use tuneefy\MusicalEntity\Entities\TrackEntity;
use tuneefy\Platform\Platform;
use tuneefy\Platform\PlatformException;
use tuneefy\Platform\PlatformResult;
use tuneefy\Platform\WebStreamingPlatformInterface;
use tuneefy\Utils\Utils;

class GooglePlayMusicPlatform extends Platform implements WebStreamingPlatformInterface
{
    const NAME = 'Google Play';
    const TAG = 'googleplay';
    const COLOR = 'ef6c00';

    const API_ENDPOINT = 'https://www.googleapis.com/sj/v1.11/';
    const API_METHOD = Platform::METHOD_GET;

    protected $endpoints = [
        Platform::LOOKUP_TRACK => self::API_ENDPOINT.'fetchtrack',
        Platform::LOOKUP_ALBUM => self::API_ENDPOINT.'fetchalbum',
        Platform::LOOKUP_ARTIST => self::API_ENDPOINT.'fetchartist',
        Platform::SEARCH_TRACK => self::API_ENDPOINT.'query',
        Platform::SEARCH_ALBUM => self::API_ENDPOINT.'query',
       // Platform::SEARCH_ARTIST => self::API_ENDPOINT . "query"
    ];
    protected $terms = [
        Platform::LOOKUP_TRACK => 'nid',
        Platform::LOOKUP_ALBUM => 'nid',
        Platform::LOOKUP_ARTIST => 'nid',
        Platform::SEARCH_TRACK => 'q',
        Platform::SEARCH_ALBUM => 'q',
       // Platform::SEARCH_ARTIST => "q"
    ];
    protected $options = [
        Platform::LOOKUP_TRACK => ['alt' => 'json'],
        Platform::LOOKUP_ALBUM => ['alt' => 'json'],
        Platform::LOOKUP_ARTIST => ['alt' => 'json'],
        Platform::SEARCH_TRACK => ['ct' => '1', 'max-results' => Platform::LIMIT],
        Platform::SEARCH_ALBUM => ['ct' => '3', 'max-results' => Platform::LIMIT],
       // Platform::SEARCH_ARTIST => Map { "contentType" => "json", "filters" => "artists", "maxItems" => Platform::LIMIT }
    ];

    // https://play.google.com/store/music/album?id=Btktawogfpi7yye5w3zxj2ykc6m
    const ALBUM_LINK = 'https://play.google.com/store/music/album?id=%s';
    const ARTIST_LINK = 'https://play.google.com/store/music/artist?id=A%s';
    const TRACK_LINK = 'https://play.google.com/store/music/album?id=%s&tid=song-%s';

    // https://play.google.com/store/music/album/James_McAlister_Planetarium?id=Bew3avws2eysvwcmkxwgu5s3rhm
    const REGEX_GOOGLE_PLAY_ALBUM = "/store\/music\/album\/".Platform::REGEX_FULLSTRING."\?id\=(?P<album_id>".Platform::REGEX_FULLSTRING.").*[\/]?$/";
    // https://play.google.com/store/music/album?id=Bbebqssprhgc27hq6xlqzrm45g4&tid=song-Ttbq3os2bblfjndnztz43sf2c2i
    const REGEX_GOOGLE_PLAY_TRACK = "/store\/music\/album\?id\=(?P<album_id>".Platform::REGEX_FULLSTRING.")\&tid\=song\-(?P<track_id>".Platform::REGEX_FULLSTRING.')$/';
    // https://play.google.com/store/music/artist/James_McAlister?id=Anop7xijqkhvkjc4q7mo6drwyu4
    const REGEX_GOOGLE_PLAY_ARTIST = "/store\/music\/artist\/".Platform::REGEX_FULLSTRING."\?id\=A(?P<artist_id>".Platform::REGEX_FULLSTRING.").*[\/]?$/";

    public function hasPermalink(string $permalink): bool
    {
        return strpos($permalink, 'play.google.com') !== false;
    }

    protected function addContextHeaders(): array
    {
        // From https://github.com/jamon/playmusic/blob/master/play.js
        $serviceauth = 'https://android.clients.google.com/auth';

        $requestData = [
            'accountType' => 'HOSTED_OR_GOOGLE',
            'has_permission' => 1,
            'service' => 'sj',
            'source' => 'android',
            'androidId' => random_bytes(8),
            'app' => 'com.google.android.music',
            'device_country' => 'us',
            'operatorCountry' => 'us',
            'lang' => 'en',
            'sdk_version' => '17',
            'Email' => $this->key,
            'Passwd' => $this->secret,
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [
          CURLOPT_URL => $serviceauth,
          CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
          CURLOPT_POST => 1,
          CURLOPT_RETURNTRANSFER => 1,
          CURLOPT_POSTFIELDS => http_build_query($requestData),
        ]);

        $result = curl_exec($ch);
        curl_close($ch);

        $params = explode("\n", $result);
        $token = array_reduce($params, function ($carry, $e) {
            if (substr($e, 0, 5) === 'Auth=') {
                return substr($e, 5);
            }

            return $carry;
        }, null);

        return ['Authorization: GoogleLogin auth='.$token];
    }

    public function expandPermalink(string $permalink, int $mode): PlatformResult
    {
        $musical_entity = null;
        $query_words = [$permalink];

        $match = [];

        if (preg_match(self::REGEX_GOOGLE_PLAY_TRACK, $permalink, $match)) {
            $response = $this->fetchSync(Platform::LOOKUP_TRACK, $match['track_id']);

            if ($response === null || !property_exists($response, 'data')) {
                throw new PlatformException();
            }

            $entity = $response->data;
            $musical_entity = new TrackEntity($entity->title, new AlbumEntity($entity->album, $entity->artist, $entity->albumArtRef[0]->url));
            $musical_entity->addLink(static::TAG, sprintf(self::TRACK_LINK, $match['album_id'], $match['track_id']));

            $query_words = [
                $musical_entity->getAlbum()->getArtist(),
                $musical_entity->getSafeTitle(),
            ];
        } elseif (preg_match(self::REGEX_GOOGLE_PLAY_ALBUM, $permalink, $match)) {
            $response = $this->fetchSync(Platform::LOOKUP_ALBUM, $match['album_id']);

            if ($response === null || !property_exists($response, 'data')) {
                throw new PlatformException();
            }

            $entity = $response->data;
            $musical_entity = new AlbumEntity($entity->name, $entity->artist, $entity->albumArtRef);
            $musical_entity->addLink(static::TAG, sprintf(self::ALBUM_LINK, $match['album_id']));

            $query_words = [
                $musical_entity->getArtist(),
                $musical_entity->getSafeTitle(),
            ];
        } elseif (preg_match(self::REGEX_GOOGLE_PLAY_ARTIST, $permalink, $match)) {
            $response = $this->fetchSync(Platform::LOOKUP_ARTIST, $match['artist_id']);

            if ($response === null || !property_exists($response, 'data')) {
                throw new PlatformException();
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

    public function search(int $type, string $query, int $limit, int $mode): array
    {
        $response = $this->fetchSync($type, $query);

        if ($response === null || property_exists($response->data, 'Error')) {
                throw new PlatformException();
        }
        $results = $response->data->entries;

        $length = min(count($results), $limit ? $limit : Platform::LIMIT);

        $musical_entities = [];

        // Normalizing each track found
        for ($i = 0; $i < $length; ++$i) {
            $current_item = $results[$i];

            if ($type === Platform::SEARCH_TRACK) {
                $musical_entity = new TrackEntity($current_item->track->title, new AlbumEntity($current_item->track->album, $current_item->track->artist, $current_item->track->albumArtRef[0]->url));
                $musical_entity->addLink(static::TAG, sprintf(self::TRACK_LINK, $current_item->track->albumId, $current_item->track->storeId));
            } else /*if ($type === Platform::SEARCH_ALBUM)*/ {
                $musical_entity = new AlbumEntity($current_item->album->name, $current_item->album->artist, $current_item->album->albumArtRef);
                $musical_entity->addLink(static::TAG, sprintf(self::ALBUM_LINK, $current_item->album->albumId));
          }

            $musical_entities[] = new PlatformResult(['score' => Utils::indexScore($i)], $musical_entity);
        }

        return $musical_entities;
    }
}
