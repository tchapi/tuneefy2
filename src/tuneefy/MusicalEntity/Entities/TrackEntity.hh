<?hh // strict

namespace tuneefy\MusicalEntity\Entities;

use tuneefy\MusicalEntity\MusicalEntity,
    tuneefy\MusicalEntity\Entities\AlbumEntity;

class TrackEntity extends MusicalEntity
{

  const string TYPE = "track";

  private string $track_title;
  private AlbumEntity $album;

  public function __construct(string $track_title, AlbumEntity $album)
  {
    parent::__construct();
    $this->track_title = $track_title;
    $this->album = $album;
  }

  // Getters and setters
  public function getArtist(): string
  {
    return $this->album->getArtist();
  }

  public function getTitle(): string
  {
    return $this->track_title;
  }

  public function getAlbumTitle(): string
  {
    return $this->album->getTitle();
  }

  public function getCover(): string
  {
    return $this->album->getCover();
  }

  public function toMap(): Map<string,mixed>
  {
    $result = Map {};
    $result->add(Pair {"type", self::TYPE});
    $result->add(Pair {"title", $this->track_title});
    $result->add(Pair {"album", $this->album->toMap()->remove("type")}); // Do not type the subresult
    if ($this->countLinks() !== 0) {
      $result->add(Pair {"links", $this->links});
    }

    return $result;
  }

}
