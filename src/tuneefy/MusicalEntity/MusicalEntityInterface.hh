<?hh // strict

namespace tuneefy\MusicalEntity;

interface MusicalEntityInterface
{

  public function toMap(): Map<string,mixed>;
  public function introspect(): this;
  public function getPrimaryHash(): string;
  public function getSecondaryHash(): string;
  
}
