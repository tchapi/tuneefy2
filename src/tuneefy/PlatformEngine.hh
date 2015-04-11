<?hh // strict

namespace tuneefy;

use tuneefy\MusicalEntity\MusicalEntity,
    tuneefy\MusicalEntity\Entities\TrackEntity,
    tuneefy\MusicalEntity\Entities\AlbumEntity,
    tuneefy\Platform\WebStreamingPlatformInterface;

use tuneefy\Platform\Platform,
    tuneefy\Platform\PlatformResult,
    tuneefy\Platform\Platforms\DeezerPlatform,
    tuneefy\Platform\Platforms\SpotifyPlatform,
    tuneefy\Platform\Platforms\BeatsMusicPlatform;

class PlatformEngine
{

  private Map<string,Platform> $platforms;

  private ImmMap<string, int> $flags = ImmMap {
    "type/track" => Platform::SEARCH_TRACK,
    "type/album" => Platform::SEARCH_ALBUM,
    "mode/lazy" => Platform::MODE_LAZY,
    "mode/eager" => Platform::MODE_EAGER,
    "mode/*" => Platform::MODE_LAZY, // '*' indicates defautl
  };

  public function __construct()
  {
    $this->platforms = Map {
      // Keys must match class TAG (constant)
      "deezer" => DeezerPlatform::getInstance(),
      "spotify" => SpotifyPlatform::getInstance(),
      "beats" => BeatsMusicPlatform::getInstance(),
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
  
  private function translateFlag(string $namespace, string $flag): int
  {
    return $this->flags[$namespace."/".$flag];
  }

  private function translateMode(?string $mode): int
  {
    return $this->translateFlag('mode', ($mode===null)?"*":$mode);
  }
  private function translateType(string $type): int
  {
    return $this->translateFlag('type', $type);
  }

  public function lookup(string $permalink, ?string $mode): ?PlatformResult
  { 
    $mode = $this->translateMode($mode);

    // Which platform is this permalink from ?
    $platform = null;
    foreach ($this->platforms as $p) {
      if ($p instanceof WebStreamingPlatformInterface && $p->hasPermalink($permalink)){
        $platform = $p; break;
      }
    }

    if ($platform === null) { return null; }

    // Initiate a lookup on this platform
    return $platform->expandPermalink($permalink, $mode);
  }

  public function search(Platform $platform, string $type, string $query, int $limit, ?string $mode): ?Vector<PlatformResult>
  {
    $type = $this->translateType($type);
    $mode = $this->translateMode($mode);

    return $platform->search($type, $query, $limit, $mode)->getWaitHandle()->join();
  }

  // For TEST purposes
  public function aggregateSync(string $type, string $query, int $limit, ?string $mode, Vector<Platform> $platforms): ?Vector<PlatformResult>
  {
    $type = $this->translateType($type);
    $mode = $this->translateMode($mode);

    $output = Vector {};
    foreach ($platforms as $p) {
      $output->add($p->search($type, $query, $limit, $mode)->getWaitHandle()->join());
    }

    // TODO : merge results
    return null;
  }

  public function aggregate(string $type, string $query, int $limit, ?string $mode, Vector<Platform> $platforms): ?Vector<PlatformResult>
  {
    $type = $this->translateType($type);
    $mode = $this->translateMode($mode);

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
