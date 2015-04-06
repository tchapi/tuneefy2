<?hh // strict

namespace tuneefy\MusicalEntity\Entities;

use tuneefy\MusicalEntity\MusicalEntity;

class AlbumEntity extends MusicalEntity
{

  const string TYPE = "album";

  private string $title;
  private string $artist;
  private string $cover;

  public function __construct(string $title, string $artist, string $cover)
  {
    parent::__construct();
    $this->title = $title;
    $this->artist = $artist;
    $this->cover = $cover;
  }

  public function toMap(): Map<string,mixed>
  {
    $result = Map {};
    $result->add(Pair {"type", self::TYPE});
    $result->add(Pair {"title", $this->title});
    $result->add(Pair {"artist", $this->artist});
    $result->add(Pair {"cover", $this->cover});
    $result->add(Pair {"links", $this->links});

    return $result;
  }

}
