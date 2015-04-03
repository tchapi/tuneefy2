<?hh // strict

namespace tuneefy\MusicalEntity\Entity;

use tuneefy\MusicalEntity\MusicalEntity;

class AlbumEntity extends MusicalEntity
{

  const string TYPE = "album";
  
  private string $name;
  private string $artist;
  private string $cover;

  public function __construct()
  {
    $this->name = "";
    $this->artist = "";
    $this->cover = "";
  }

  public function toMap(): Map<string,mixed>
  {

    $result = Map {};
    $result->add(Pair { "type", self::TYPE});
    $result->add(Pair { "name", $this->name});
    $result->add(Pair { "artist", $this->artist});
    $result->add(Pair { "cover", $this->cover});

    return $result;
  }

}
