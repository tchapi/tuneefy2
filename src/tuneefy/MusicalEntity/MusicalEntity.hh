<?hh // strict

namespace tuneefy\MusicalEntity;

use tuneefy\MusicalEntity\MusicalEntityInterface;

abstract class MusicalEntity implements MusicalEntityInterface
{

  const string TYPE = "musical_entity";

  protected Vector<string> $links;

  // Introspection
  protected bool $introspected = false;
  protected Map<string,string> $extra_info;

  public function __construct()
  {
    $this->links = Vector {};
    $this->introspected = false;
    $this->extra_info = Map{};
  }

  public function toMap(): Map<string,mixed>
  {
    $result = Map {};
    $result->add(Pair {"type", self::TYPE});
    return  $result;
  }

  /*
    Links getter and setter
  */
  public function addLink(string $link): this
  {
    $this->links->add($link);
    return $this;
  }

  public function addLinks(Vector<string> $links): this
  {
    $this->links->addAll($links);
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
  
  public function isIntrospected(): bool
  {
    return $this->introspected;
  }

  public function setIntrospected(?Map $extra_info = null): this
  {
    $this->introspected = true;
    if ($extra_info !== null){
      $this->extra_info = $extra_info;
    }
    return $this;
  }

  public function getExtraInfo(): Map<string,string>
  {
    return $this->extra_info;
  }

}
