<?hh // strict

namespace tuneefy\Platform;

use tuneefy\MusicalEntity\MusicalEntity,
    tuneefy\MusicalEntity\Entities\TrackEntity,
    tuneefy\MusicalEntity\Entities\AlbumEntity,
    tuneefy\DB\DatabaseHandler;

class PlatformResult
{

  private string $intent;
  
  // Some arguments are promoted : 
  // http://docs.hhvm.com/manual/en/hack.constructorargumentpromotion.php
  public function __construct(private Map<string,mixed> $metadata, private ?MusicalEntity $musical_entity) {
    $this->intent = uniqid(); // We create it now for later use
  }

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
        "metadata" => $this->metadata,
        "share" => Map {
          "intent" => $this->intent
        }
      };
    }
  }

  public function mergeWith(PlatformResult $that): this
  {

    $thatMusicalEntity = $that->getMusicalEntity();

    if ($this->musical_entity !== null && $thatMusicalEntity !== null) {

      // Merge musical entities
      if ($this->musical_entity instanceof TrackEntity) {
        invariant($thatMusicalEntity instanceof TrackEntity, "must be a TrackEntity");
        $this->musical_entity = TrackEntity::merge($this->musical_entity, $thatMusicalEntity);
      } else if ($this->musical_entity instanceof AlbumEntity) {
        invariant($thatMusicalEntity instanceof AlbumEntity, "must be a AlbumEntity");
        $this->musical_entity = AlbumEntity::merge($this->musical_entity, $thatMusicalEntity);
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

    }
    
    return $this;
    
  }

  public function addIntent(): this
  {
    // Store guid + serialized platformResult in db
    $db = DatabaseHandler::getInstance(null);
    $db->addIntent($this->intent, $this);

    return $this;
  }

  public function finalizeMerge(): this
  {
    // Compute a final score
    if (array_key_exists("merges", $this->metadata) && array_key_exists("score", $this->metadata)) {
      // The more merges, the better the result must be
      $merge_quantifier_offset = floatval($this->metadata['merges']) / 2.25; // Completely heuristic number
      $this->metadata['score'] = $merge_quantifier_offset + floatval($this->metadata['score']) / (floatval($this->metadata['merges']) + 1);
    } else if (array_key_exists("score", $this->metadata)){
      // has not been merged, ever. Lower score
      $this->metadata['score'] = floatval($this->metadata['score']) / 2;
    } else {
      $this->metadata['score'] = 0.0;
    }

    $this->metadata['score'] = round($this->metadata['score'], 3);

    return $this;
  }
}
