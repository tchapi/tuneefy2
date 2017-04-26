<?php

namespace tuneefy;

use tuneefy\Platform\Platform;
use tuneefy\Platform\PlatformResult;
use tuneefy\Platform\Platforms\AmazonMP3Platform;
use tuneefy\Platform\Platforms\DeezerPlatform;
use tuneefy\Platform\Platforms\GooglePlayMusicPlatform;
use tuneefy\Platform\Platforms\GrooveMusicPlatform;
use tuneefy\Platform\Platforms\HypeMachinePlatform;
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
          'groove' => GrooveMusicPlatform::getInstance(),
          'qobuz' => QobuzPlatform::getInstance(),
          'soundcloud' => SoundcloudPlatform::getInstance(),
          'mixcloud' => MixcloudPlatform::getInstance(),
          'tidal' => TidalPlatform::getInstance(),
          'youtube' => YoutubePlatform::getInstance(),
          'napster' => NapsterPlatform::getInstance(),
          'googleplay' => GooglePlayMusicPlatform::getInstance(),

          // Blogs / Scrobbling
          'lastfm' => LastFMPlatform::getInstance(),
          'hypem' => HypeMachinePlatform::getInstance(),

          // Stores
          'itunes' => ItunesPlatform::getInstance(),
          'amazon' => AmazonMP3Platform::getInstance(),
        ];
    }

    public function getAllPlatforms(): array
    {
        return array_values($this->platforms);
    }

    public function getPlatformByTag(string $tag)//: Platform
    {
        return isset($this->platforms[$tag]) ? $this->platforms[$tag] : null;
    }

    public function getPlatformsByTags(array $tags) //: ?array
    {
        return array_values($this->platforms->filterWithKey(function ($key, $val) use ($tags) { return in_array($key, $tags); }));
    }

    public function translateFlag(string $namespace, string $flag = null): int
    {
        if ($flag === null) {
            $flag = '*';
        }
        // FIX ME enforce flags
        return $this->flags[$namespace.'/'.$flag];
    }

    public function lookup(string $permalink, int $mode) //: ?PlatformResult
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

        if ($platform === null) {
            return null;
        }

        // Initiate a lookup on this platform
        return $platform->expandPermalink($permalink, $mode);
    }

    public function search(Platform $platform, int $type, string $query, int $limit, int $mode) //: ?array
    {
        if (($platform->isCapableOfSearchingTracks() && $type === Platform::SEARCH_TRACK)
         || ($platform->isCapableOfSearchingAlbums() && $type === Platform::SEARCH_ALBUM)) {
            return $platform->search($type, $query, $limit, $mode);
        } else {
            return null;
        }
    }

    public function aggregate(int $type, string $query, int $limit, int $mode, bool $aggressive, array $platforms) //: ?array
    {
        $result = [];
        foreach ($platforms as $p) {
            if (($p->isCapableOfSearchingTracks() && $type === Platform::SEARCH_TRACK)
             || ($p->isCapableOfSearchingAlbums() && $type === Platform::SEARCH_ALBUM)) {
                $result = array_merge($result, $p->search($type, $query, Platform::AGGREGATE_LIMIT, $mode));
            }
        }

        return $this->mergeResults($result, $aggressive, $limit);
    }

    public function mergeResults(array $results, bool $aggressive, int $limit) //: ?array
    {
        $merged_results = [];

        foreach ($results as $result) {
            $current_entity = $result->getMusicalEntity();

            if ($current_entity === null) {
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

        // Gives each element a last chance of doing something useful on its data
        //$merged_results->map($e ==> {$e->finalizeMerge()->addIntent();});

        // Discards the key (hash) that we don't need anymore
        $result = array_values($merged_results);

        // Sorts by score
        usort($result, function ($a, $b) {
            $am = $a->getMetadata();
            $bm = $b->getMetadata();
            if ($am['score'] == $bm['score']) {
                return 0;
            }

            return ($am['score'] > $bm['score']) ? -1 : 1;
        });

        // Resizes to keep only the wanted number of elements
        return array_splice($result, $limit);
    }

    public function share(string $intent) //: ?string
    {
        return null;
    }
}
