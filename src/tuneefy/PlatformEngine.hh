<?hh // strict

namespace tuneefy;

use tuneefy\MusicalEntity\MusicalEntity,
    tuneefy\MusicalEntity\Entities\TrackEntity,
    tuneefy\MusicalEntity\Entities\AlbumEntity,
    tuneefy\Platform\WebStreamingPlatformInterface;

use tuneefy\Platform\Platform,
    tuneefy\Platform\PlatformResult,
    tuneefy\Platform\Platforms\DeezerPlatform,
    tuneefy\Platform\Platforms\SpotifyPlatform;

class PlatformEngine
{

  private Map<string,Platform> $platforms;

  public function __construct()
  {
    $this->platforms = Map {
      // Keys must match class TAG (constant)
      "deezer" => DeezerPlatform::getInstance(),
      "spotify" => SpotifyPlatform::getInstance(),
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
  
  public function lookup(string $permalink): ?PlatformResult
  { 
    // Which platform is this permalink from ?
    $platform = null;
    foreach ($this->platforms as $p) {
      if ($p instanceof WebStreamingPlatformInterface && $p->hasPermalink($permalink)){
        $platform = $p; break;
      }
    }

    if ($platform === null) { return null; }

    // Initiate a lookup on this platform
    return $platform->expandPermalink($permalink);
  }

  public function search(Platform $platform, string $type, string $query, int $limit): ?Vector<PlatformResult>
  {
    switch ($type) {
      case 'album':
        $search_type = Platform::SEARCH_ALBUM;
        break;
      case 'track':
      default:
        $search_type = Platform::SEARCH_TRACK;
        break;
    }

    return $platform->search($search_type, $query, $limit)->getWaitHandle()->join();
  }

  // For TEST purposes
  public function aggregateSync(string $type, string $query, int $limit, Vector<Platform> $platforms): ?Vector<PlatformResult>
  {
    switch ($type) {
      case 'album':
        $search_type = Platform::SEARCH_ALBUM;
        break;
      case 'track':
      default:
        $search_type = Platform::SEARCH_TRACK;
        break;
    }

    $output = Vector {};
    foreach ($platforms as $p) {
      $output->add($p->search($search_type, $query, $limit)->getWaitHandle()->join());
    }

    // TODO : merge results
    return null;
  }

  public function aggregate(string $type, string $query, int $limit, Vector<Platform> $platforms): ?Vector<PlatformResult>
  {
    switch ($type) {
      case 'album':
        $search_type = Platform::SEARCH_ALBUM;
        break;
      case 'track':
      default:
        $search_type = Platform::SEARCH_TRACK;
        break;
    }

    $asyncs = Vector {};
    foreach ($platforms as $p) {
      $asyncs->add($p->search($search_type, $query, $limit)->getWaitHandle());
    }

    // Calling the functions
    $asyncCall = GenVectorWaitHandle::create($asyncs);

    // Now requesting that async finishes
    $output = $asyncCall->join();

    // TODO : merge results
    return null;
  }
}
