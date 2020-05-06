<?php

namespace tuneefy;

use tuneefy\Controller\ApiController;
use tuneefy\Platform\Platform;
use tuneefy\Platform\PlatformException;
use tuneefy\Platform\PlatformResult;
use tuneefy\Platform\Platforms\AmazonMusicPlatform;
use tuneefy\Platform\Platforms\DeezerPlatform;
use tuneefy\Platform\Platforms\GooglePlayMusicPlatform;
use tuneefy\Platform\Platforms\ItunesPlatform;
use tuneefy\Platform\Platforms\LastFMPlatform;
use tuneefy\Platform\Platforms\MixcloudPlatform;
use tuneefy\Platform\Platforms\NapsterPlatform;
use tuneefy\Platform\Platforms\QobuzPlatform;
use tuneefy\Platform\Platforms\SoundcloudPlatform;
use tuneefy\Platform\Platforms\SpotifyPlatform;
use tuneefy\Platform\Platforms\TidalPlatform;
use tuneefy\Platform\Platforms\YoutubePlatform;
use tuneefy\Platform\ScrobblingPlatformInterface;
use tuneefy\Platform\WebStoreInterface;
use tuneefy\Platform\WebStreamingPlatformInterface;

class PlatformEngine
{
    private $platforms;

    private $flags = [
      'type/track' => Platform::SEARCH_TRACK,
      'type/album' => Platform::SEARCH_ALBUM,
      'mode/lazy' => Platform::MODE_LAZY,
      'mode/eager' => Platform::MODE_EAGER,
      'mode/*' => Platform::MODE_LAZY, // '*' indicates default
    ];

    public function __construct()
    {
        $this->platforms = [
          // Keys must match class TAG (constant)

          // Streaming platforms
          'deezer' => DeezerPlatform::getInstance(),
          'spotify' => SpotifyPlatform::getInstance(),
          'qobuz' => QobuzPlatform::getInstance(),
          'soundcloud' => SoundcloudPlatform::getInstance(),
          'mixcloud' => MixcloudPlatform::getInstance(),
          'tidal' => TidalPlatform::getInstance(),
          'youtube' => YoutubePlatform::getInstance(),
          'napster' => NapsterPlatform::getInstance(),
          'googleplay' => GooglePlayMusicPlatform::getInstance(),

          // Blogs / Scrobbling
          'lastfm' => LastFMPlatform::getInstance(),

          // Stores
          'itunes' => ItunesPlatform::getInstance(),
          'amazon' => AmazonMusicPlatform::getInstance(),
        ];
    }

    public function setCurrentToken(array $token = null)
    {
        $this->token = $token;

        return $this;
    }

    public function getAllPlatforms(): array
    {
        return array_values($this->platforms);
    }

    public function getPlatformByTag(string $tag)//: Platform
    {
        return isset($this->platforms[$tag]) ? $this->platforms[$tag] : null;
    }

    public function getPlatformsByTags(array $tags): array
    {
        return array_intersect_key($this->platforms, array_flip($tags));
    }

    public function translateFlag(string $namespace, string $flag = null): int
    {
        if (null === $flag || '' === $flag) {
            $flag = '*';
        }

        $path = $namespace.'/'.$flag;

        if (!isset($this->flags[$path]) && 'type' === $namespace) {
            throw new \Exception('BAD_MUSICAL_TYPE');
        } elseif (!isset($this->flags[$path]) && 'mode' === $namespace) {
            throw new \Exception('BAD_MODE');
        }

        return $this->flags[$path];
    }

    public function lookup(string $permalink, int $mode): array
    {
        // Which platform is this permalink from ?
        $platform = null;
        foreach ($this->platforms as $p) {
            if ($p->isCapableOfLookingUp()) {
                if ($p instanceof WebStreamingPlatformInterface && $p->hasPermalink($permalink)) {
                    $platform = $p;
                    break;
                }
                if ($p instanceof WebStoreInterface && $p->hasPermalink($permalink)) {
                    $platform = $p;
                    break;
                }
                if ($p instanceof ScrobblingPlatformInterface && $p->hasPermalink($permalink)) {
                    $platform = $p;
                    break;
                }
            }
        }

        if (null === $platform) {
            return ['errors' => [ApiController::ERRORS['PERMALINK_UNKNOWN']]];
        }

        // Initiate a lookup on this platform
        return ['result' => $platform->expandPermalink($permalink, $mode)->store($this->token)];
    }

    public function search(Platform $platform, int $type, string $query, int $limit, int $mode, string $countryCode = null): array
    {
        try {
            $lookup = $this->lookup($query, $mode);

            if (array_key_exists('result', $lookup) && $lookup['result'] instanceof PlatformResult) {
                $metadata = $lookup['result']->getMetadata();
                $query = implode(' ', $metadata['query_words']);
            }
        } catch (PlatformException $e) {
            // Do nothing ?
        }

        if (($platform->isCapableOfSearchingTracks() && Platform::SEARCH_TRACK === $type)
         || ($platform->isCapableOfSearchingAlbums() && Platform::SEARCH_ALBUM === $type)) {
            $results = Platform::search($platform, $type, $query, $limit, $mode, $countryCode);
            array_map(function ($e) { return $e->store($this->token); }, $results);

            return ['results' => $results];
        } elseif (Platform::SEARCH_TRACK === $type) {
            return ['errors' => [ApiController::ERRORS['NOT_CAPABLE_TRACKS']]];
        } elseif (Platform::SEARCH_ALBUM === $type) {
            return ['errors' => [ApiController::ERRORS['NOT_CAPABLE_ALBUMS']]];
        }
    }

    public function aggregate(array $platforms, int $type, string $query, int $limit, int $mode, bool $aggressive, string $countryCode = null): array
    {
        try {
            $lookup = $this->lookup($query, $mode);

            if (array_key_exists('result', $lookup) && $lookup['result'] instanceof PlatformResult) {
                $metadata = $lookup['result']->getMetadata();
                $query = implode(' ', $metadata['query_words']);
            }
        } catch (PlatformException $e) {
            // Do nothing ?
        }

        $result = [];

        $filtered_platforms = array_filter($platforms, function ($p) use ($type) {
            return ($p->isCapableOfSearchingTracks() && Platform::SEARCH_TRACK === $type)
             || ($p->isCapableOfSearchingAlbums() && Platform::SEARCH_ALBUM === $type);
        });

        $resultArray = Platform::aggregate($filtered_platforms, $type, $query, Platform::AGGREGATE_LIMIT, $mode, $countryCode);

        return ['results' => $this->mergeResults($resultArray['results'], $aggressive, $limit), 'errors' => $resultArray['errors']];
    }

    public function mergeResults(array $results, bool $aggressive, int $limit): array
    {
        $merged_results = [];

        foreach ($results as $result) {
            $current_entity = $result->getMusicalEntity();

            if (null === $current_entity) {
                continue;
            }

            // Run introspection and get hash
            $key = $current_entity->introspect()->getHash($aggressive);

            // Then merges with the actual array we already have
            if (!array_key_exists($key, $merged_results)) {
                $merged_results[$key] = $result;
            } else {
                $merged_results[$key]->mergeWith($result);
            }
        }

        // Sorts by score
        usort($merged_results, function ($a, $b) {
            $am = $a->getMetadata();
            $bm = $b->getMetadata();
            if ($am['score'] == $bm['score']) {
                return 0;
            }

            return ($am['score'] > $bm['score']) ? -1 : 1;
        });

        // Resizes to keep only the wanted number of elements
        array_splice($merged_results, $limit);

        // Gives each element a last chance of doing something useful on its data
        array_map(function ($e) { return $e->finalizeMerge()->store($this->token); }, $merged_results);

        // Discards the key (hash) that we don't need anymore
        return array_values($merged_results);
    }
}
