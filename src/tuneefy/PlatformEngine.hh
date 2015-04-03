<?hh // strict

namespace tuneefy;

use tuneefy\MusicalEntity\MusicalEntity;

class PlatformEngine
{

  public function lookup(string $permalink): ?MusicalEntity
  {
    return null;
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
