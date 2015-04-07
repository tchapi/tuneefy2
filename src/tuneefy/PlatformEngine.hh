<?hh // strict

namespace tuneefy;

use tuneefy\MusicalEntity\MusicalEntity,
    tuneefy\MusicalEntity\Entities\TrackEntity,
    tuneefy\MusicalEntity\Entities\AlbumEntity,
    tuneefy\Platform\WebStreamingPlatformInterface;

use tuneefy\Platform\Platform,
    tuneefy\Platform\Platforms\DeezerPlatform;

class PlatformEngine
{

  private Map<string,Platform> $platforms;

  public function __construct()
  {
    $this->platforms = Map {
      // Keys must match class TAG (constant)
      "deezer" => DeezerPlatform::getInstance(),
      // More to come here
    };
  }

  public function getPlatformByTag(string $tag): ?Platform
  {
    return $this->platforms->get($tag);
  }
  
  public function lookup(string $permalink): ?Map<string,mixed>
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

  public function search(Platform $platform, string $type, string $query, int $limit): ?Vector<Map<string,mixed>>
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

  public function aggregate(string $query): ?Vector<MusicalEntity>
  {
    // TODO
    return null;
  }
}
