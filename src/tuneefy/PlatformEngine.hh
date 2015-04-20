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
    tuneefy\Platform\Platforms\GroovesharkPlatform,
    tuneefy\Platform\Platforms\QobuzPlatform,
    tuneefy\Platform\Platforms\ItunesPlatform,
    tuneefy\Platform\Platforms\SoundcloudPlatform,
    tuneefy\Platform\Platforms\MixcloudPlatform,
    tuneefy\Platform\Platforms\RdioPlatform,
    tuneefy\Platform\Platforms\TidalPlatform,
    tuneefy\Platform\Platforms\LastFMPlatform,
    tuneefy\Platform\Platforms\HypeMachinePlatform;

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
      "grooveshark" => GroovesharkPlatform::getInstance(),
      "qobuz" => QobuzPlatform::getInstance(),
      "soundcloud" => SoundcloudPlatform::getInstance(),
      "mixcloud" => MixcloudPlatform::getInstance(),
      "rdio" => RdioPlatform::getInstance(),
      "tidal" => TidalPlatform::getInstance(),
      "lastfm" => LastFMPlatform::getInstance(),
      "hypem" => HypeMachinePlatform::getInstance(),


      // Stores
      "itunes" => ItunesPlatform::getInstance(),
      // More to come here
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

    if ($platform === null) { return null; }

    // Initiate a lookup on this platform
    return $platform->expandPermalink($permalink, $mode);
  }

  public function search(Platform $platform, int $type, string $query, int $limit, int $mode): ?Vector<PlatformResult>
  {
    return $platform->search($type, $query, $limit, $mode)->getWaitHandle()->join();
  }

  // For TEST purposes
  public function aggregateSync(int $type, string $query, int $limit, int $mode, Vector<Platform> $platforms): ?Vector<PlatformResult>
  {
    $output = Vector {};
    foreach ($platforms as $p) {
      $output->add($p->search($type, $query, $limit, $mode)->getWaitHandle()->join());
    }

    // TODO : merge results
    return null;
  }

  public function aggregate(int $type, string $query, int $limit, int $mode, Vector<Platform> $platforms): ?Vector<PlatformResult>
  {
    $asyncs = Vector {};
    foreach ($platforms as $p) {
      $asyncs->add($p->search($type, $query, $limit, $mode)->getWaitHandle());
    }

    // Calling the functions
    $asyncCall = GenVectorWaitHandle::create($asyncs);

    // Now requesting that async finishes
    $output = $asyncCall->join();

    // TODO : merge results
    return null;
  }
}
