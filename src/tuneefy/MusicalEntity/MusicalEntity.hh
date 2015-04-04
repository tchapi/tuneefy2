<?hh // strict

namespace tuneefy\MusicalEntity;

use tuneefy\MusicalEntity\MusicalEntityInterface;

abstract class MusicalEntity implements MusicalEntityInterface
{

  const string TYPE = "musical_entity";

  public function toMap(): Map<string,mixed>
  {
    $result = Map {};
    $result->add(Pair {"type", self::TYPE});
    return  $result;
  }

}
