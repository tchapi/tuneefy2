<?hh // strict

namespace tuneefy;

use tuneefy\MusicalEntity\MusicalEntity,
    tuneefy\MusicalEntity\Entities\TrackEntity,
    tuneefy\MusicalEntity\Entities\AlbumEntity;

class PlatformEngine
{

  public function lookup(string $permalink): ?MusicalEntity
  {
    $result = new TrackEntity(); // or new AlbumEntity() TODO according to the result
    return $result;
  }

  public function search(string $query): ?Vector<MusicalEntity>
  {
    return null;
  }

  public function aggregate(string $query): ?Vector<MusicalEntity>
  {
    return null;
  }
}
