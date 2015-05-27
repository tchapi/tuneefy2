<?hh // strict

namespace tuneefy\MusicalEntity;

interface MusicalEntityInterface
{

  public function toMap(): Map<string,mixed>;
  public function introspect(): this;
  public function getHash(bool $aggressive): string;
  
}
