<?hh // strict

namespace tuneefy;

use tuneefy\MusicalEntity\MusicalEntity,
    tuneefy\MusicalEntity\Entities\TrackEntity,
    tuneefy\MusicalEntity\Entities\AlbumEntity,
    tuneefy\Platform\WebStreamingPlatformInterface,
    tuneefy\Platform\WebStoreInterface,
    tuneefy\Platform\ScrobblingPlatformInterface;

use tuneefy\Platform\Platform,
    tuneefy\Platform\PlatformResult,
    tuneefy\Platform\Platforms\DeezerPlatform,
    tuneefy\Platform\Platforms\SpotifyPlatform,
    tuneefy\Platform\Platforms\BeatsMusicPlatform,
    tuneefy\Platform\Platforms\XboxMusicPlatform,
    tuneefy\Platform\Platforms\QobuzPlatform,
    tuneefy\Platform\Platforms\ItunesPlatform,
    tuneefy\Platform\Platforms\AmazonMP3Platform,
    tuneefy\Platform\Platforms\SoundcloudPlatform,
    tuneefy\Platform\Platforms\MixcloudPlatform,
    tuneefy\Platform\Platforms\RdioPlatform,
    tuneefy\Platform\Platforms\TidalPlatform,
    tuneefy\Platform\Platforms\LastFMPlatform,
    tuneefy\Platform\Platforms\HypeMachinePlatform,
    tuneefy\Platform\Platforms\YoutubePlatform;

class PlatformEngine
{

  private Map<string,Platform> $platforms;

  private ImmMap<string, int> $flags = ImmMap {
    "type/track" => Platform::SEARCH_TRACK,
    "type/album" => Platform::SEARCH_ALBUM,
    "mode/lazy" => Platform::MODE_LAZY,
    "mode/eager" => Platform::MODE_EAGER,
    "mode/*" => Platform::MODE_LAZY, // '*' indicates default
  };

  public function __construct()
  {
    $this->platforms = Map {
      // Keys must match class TAG (constant)

      // Streaming platforms
      "deezer" => DeezerPlatform::getInstance(),
      "spotify" => SpotifyPlatform::getInstance(),
      "beats" => BeatsMusicPlatform::getInstance(),
      "xbox" => XboxMusicPlatform::getInstance(),
      "qobuz" => QobuzPlatform::getInstance(),
      "soundcloud" => SoundcloudPlatform::getInstance(),
      "mixcloud" => MixcloudPlatform::getInstance(),
      "rdio" => RdioPlatform::getInstance(),
      "tidal" => TidalPlatform::getInstance(),
      "youtube" => YoutubePlatform::getInstance(),

      // Blogs / Scrobbling
      "lastfm" => LastFMPlatform::getInstance(),
      "hypem" => HypeMachinePlatform::getInstance(),

      // Stores
      "itunes" => ItunesPlatform::getInstance(),
      "amazon" => AmazonMP3Platform::getInstance(),

    };
  }

  public function getAllPlatforms(): Vector<Platform>
  {
    return $this->platforms->values();
  }

  public function getPlatformByTag(string $tag): ?Platform
  {
    return $this->platforms->get($tag);
  }

  public function getPlatformsByTags(array<string> $tags): ?Vector<Platform>
  {
    return $this->platforms->filterWithKey(function($key, $val) use ($tags) { return in_array($key, $tags); })
                           ->values();
  }
  
  public function translateFlag(string $namespace, ?string $flag): ?int
  {
    if ($flag === null) {
      $flag = '*';
    }
    return $this->flags->get($namespace."/".$flag);
  }

  public function lookup(string $permalink, int $mode): ?PlatformResult
  { 
    // Which platform is this permalink from ?
    $platform = null;
    foreach ($this->platforms as $p) {
      if ($p->isCapableOfLookingUp()) {
        if ($p instanceof WebStreamingPlatformInterface && $p->hasPermalink($permalink)) {
          $platform = $p; break;
        }
        if ($p instanceof WebStoreInterface && $p->hasPermalink($permalink)) {
          $platform = $p; break;
        }
        if ($p instanceof ScrobblingPlatformInterface && $p->hasPermalink($permalink)) {
          $platform = $p; break;
        }
      }
    }

    if ($platform === null) { return null; }

    // Initiate a lookup on this platform
    return $platform->expandPermalink($permalink, $mode);
  }

  public function search(Platform $platform, int $type, string $query, int $limit, int $mode): ?Vector<PlatformResult>
  {
    if ( ($platform->isCapableOfSearchingTracks() && $type === Platform::SEARCH_TRACK)
      || ($platform->isCapableOfSearchingAlbums() && $type === Platform::SEARCH_ALBUM) ) {
      return $platform->search($type, $query, $limit, $mode)->getWaitHandle()->join();
    } else {
      return null;
    }
  }

  // For TEST purposes
  public function aggregateSync(int $type, string $query, int $limit, int $mode, bool $aggressive, Vector<Platform> $platforms): ?Vector<PlatformResult>
  {
    $output = Vector {};
    foreach ($platforms as $p) {
      if ( ($p->isCapableOfSearchingTracks() && $type === Platform::SEARCH_TRACK)
      || ($p->isCapableOfSearchingAlbums() && $type === Platform::SEARCH_ALBUM) ) {
        $output->add($p->search($type, $query, Platform::AGGREGATE_LIMIT, $mode)->getWaitHandle()->join());
      }
    }

    // Let's flatten it out first
    $result = Vector {};
    foreach ($output as $o) {
      $result->addAll($o);
    }

    return $this->mergeResults($result, $aggressive, $limit);

  }

  public function aggregate(int $type, string $query, int $limit, int $mode, bool $aggressive, Vector<Platform> $platforms): ?Vector<PlatformResult>
  {
    $asyncs = Vector {};
    foreach ($platforms as $p) {
      $asyncs->add($p->search($type, $query, Platform::AGGREGATE_LIMIT, $mode)->getWaitHandle());
    }

    // Calling the functions
    $asyncCall = GenVectorWaitHandle::create($asyncs);

    // Now requesting that async finishes
    $output = $asyncCall->join();

    // We now have a Vector of 15 (or more) Vectors containing each 10 (or more) PlatformResult results, we now want to merge that
    // Let's flatten it out first
    $result = Vector {};
    foreach ($output as $o) {
      $result->addAll($o);
    }

    return $this->mergeResults($result, $aggressive, $limit);

  }

  public function mergeResults(Vector<PlatformResult> $results, bool $aggressive, int $limit): ?Vector<PlatformResult>
  {

    $merged_results = Map {};

    foreach ($results as $result) {

      $current_entity = $result->getMusicalEntity();
      if ($current_entity === null) { continue; }

      // Run introspection and get hash
      $key = $current_entity->introspect()->getHash($aggressive);

      // Then merges with the actual Map we already have
      if (!$merged_results->containsKey($key)){
        $merged_results[$key] = $result;
      } else {
        $merged_results[$key]->mergeWith($result);
      }

    }

    // Gives each element a last chance of doing something useful on its data
    $merged_results->map($e ==> {$e->finalizeMerge();});

    // Discards the key (hash) that we don't need anymore
    $result = $merged_results->values();

    // Sorts by score
    usort($result, ($a, $b) ==> { 
      $am = $a->getMetadata();
      $bm = $b->getMetadata();
      if ($am['score'] == $bm['score']) { return 0; }
      return ($am['score'] > $bm['score'])? -1 : 1 ;
    });

    // Resizes to keep only the wanted number of elements
    $result->splice(0, $limit);

    return $result;
  }

  public function share(string $intent): ?string
  {
    return null;
  }

}
