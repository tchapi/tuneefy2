<?hh // strict

namespace tuneefy\Platform;

use tuneefy\MusicalEntity\MusicalEntity;

class PlatformResult
{
  // Arguments are promoted : 
  // http://docs.hhvm.com/manual/en/hack.constructorargumentpromotion.php
  public function __construct(private Map<string,mixed> $metadata, private ?MusicalEntity $musical_entity) {}

  public function getMetadata(): Map<string,mixed>
  {
    return $this->metadata;
  }

  public function getMusicalEntity(): ?MusicalEntity
  {
    return $this->musical_entity;
  }

  public function toMap(): Map<string,mixed>
  {
    if ($this->musical_entity === null) {
      return Map {
        "metadata" => $this->metadata
      };
    } else {
      return Map {
        "musical_entity" => $this->musical_entity->toMap(),
        "metadata" => $this->metadata
      };
    }
  }
}
