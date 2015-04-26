<?hh // strict

namespace tuneefy\MusicalEntity\Entities;

use tuneefy\MusicalEntity\MusicalEntity;
use tuneefy\Utils\Utils;

class AlbumEntity extends MusicalEntity
{

  const string TYPE = "album";

  private string $title;
  private string $artist;
  private string $picture;

  // Introspection
  private bool $introspected = false;
  private string $safe_title;
  private Map<string,string> $metadata;

  public function __construct(string $title, string $artist, string $picture)
  {
    parent::__construct();
    $this->title = $title;
    $this->artist = $artist;
    $this->picture = $picture;

    // Blank meta for now
    $this->introspected = false;
    $this->safe_title = $title;
    $this->metadata = Map{};
  }

  // Getters and setters
  public function getArtist(): string
  {
    return $this->artist;
  }

  public function getTitle(): string
  {
    return $this->title;
  }

  public function getSafeTitle(): string
  {
    return $this->safe_title;
  }

  public function getPicture(): string
  {
    return $this->picture;
  }

  public function toMap(): Map<string,mixed>
  {
    $result = Map {};
    $result->add(Pair {"type", self::TYPE});
    $result->add(Pair {"title", $this->title});
    $result->add(Pair {"artist", $this->artist});
    $result->add(Pair {"picture", $this->picture});

    if ($this->countLinks() !== 0) {
      $result->add(Pair {"links", $this->links});
    }

    return $result;
  }

}
