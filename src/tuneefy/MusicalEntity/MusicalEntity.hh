<?hh // strict

namespace tuneefy\MusicalEntity;

use tuneefy\MusicalEntity\MusicalEntityInterface;

abstract class MusicalEntity implements MusicalEntityInterface
{

  const string TYPE = "musical_entity";

  protected Vector<string> $links;

  public function __construct()
  {
    $this->links = Vector {};
  }

  public function toMap(): Map<string,mixed>
  {
    $result = Map {};
    $result->add(Pair {"type", self::TYPE});
    return  $result;
  }

  /* Links getter and setter
  */
  public function addLink(string $link): this
  {
    $this->links->add($link);
    return $this;
  }

  public function getLinks(): Vector<string>
  {
    return $this->links;
  }

  public function countLinks(): int
  {
    return count($this->links);
  }
}
