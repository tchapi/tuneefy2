<?hh // strict

namespace tuneefy\Platform;

use tuneefy\MusicalEntity\MusicalEntity,
    tuneefy\MusicalEntity\Entities\TrackEntity,
    tuneefy\MusicalEntity\Entities\AlbumEntity;

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

  public function mergeWith(PlatformResult $that): this
  {

    if ($this->musical_entity === null || $that->getMusicalEntity() === null) {
      return $this;
    }

    // Merge musical entities
    if ($this->musical_entity instanceof TrackEntity) {
      $this->musical_entity = TrackEntity::merge($this->musical_entity, $that->getMusicalEntity());
    } else if ($this->musical_entity instanceof AlbumEntity) {
      $this->musical_entity = AlbumEntity::merge($this->musical_entity, $that->getMusicalEntity());
    }

    // Merge score
    $thatMetadata = $that->getMetadata();
    if (array_key_exists("score", $this->metadata) && array_key_exists("score", $thatMetadata)) {
      $this->metadata['score'] = floatval($this->metadata['score']) + floatval($thatMetadata['score']);
    }

    // Merge other metadata ?
    // TODO

    if (array_key_exists("merges", $this->metadata)) {
      $this->metadata['merges'] = intval($this->metadata['merges']) + 1;
    } else {
      $this->metadata['merges'] = 1;
    }

    return $this;
  }

  public function finalizeMerge(): this
  {
    // Compute a final score
    if (array_key_exists("merges", $this->metadata) && array_key_exists("score", $this->metadata)) {
      // The more merges, the better the result must be
      // so we only divide by 'merges' and not 'merges+1'
      $this->metadata['score'] = floatval($this->metadata['score']) / (floatval($this->metadata['merges']));
    } else if (array_key_exists("score", $this->metadata)){
      // has not been merged, ever. Lower score
      $this->metadata['score'] = floatval($this->metadata['score']) / 2;
    }

    return $this;
  }
}
