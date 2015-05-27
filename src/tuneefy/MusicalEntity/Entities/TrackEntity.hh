<?hh // strict

namespace tuneefy\MusicalEntity\Entities;

use tuneefy\MusicalEntity\MusicalEntity,
    tuneefy\MusicalEntity\Entities\AlbumEntity;

use tuneefy\Utils\Utils;

class TrackEntity extends MusicalEntity
{

  const string TYPE = "track";

  private string $track_title;
  private AlbumEntity $album;

  // Introspection
  private bool $is_cover = false;
  private string $safe_track_title;

  public function __construct(string $track_title, AlbumEntity $album)
  {
    parent::__construct();
    $this->track_title = $track_title;
    $this->album = $album;

    $this->is_cover = false;
    $this->safe_track_title = $track_title;
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

  public function getSafeTitle(): string
  {
    return $this->safe_track_title;
  }

  public function getAlbum(): AlbumEntity
  {
    return $this->album;
  }

  public function getAlbumTitle(): string
  {
    return $this->album->getTitle();
  }

  public function getAlbumSafeTitle(): string
  {
    return $this->album->getSafeTitle();
  }

  public function getAlbumPicture(): string
  {
    return $this->album->getPicture();
  }

  public function isCover(): bool
  {
    return $this->is_cover;
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

    if ($this->introspected === true) {
      $result->add(Pair {"cover", $this->is_cover});
      $result->add(Pair {"safe_title", $this->safe_track_title});
      $result->add(Pair {"extra_info", $this->extra_info});
    }

    return $result;
  }

  /*
    Strips unnecessary words from a track title
    And extracts extra_info
  */
  public function introspect(): this
  {
    if ($this->introspected === false) {

      // Is this a cover or karaoke version ?
      // the strlen part prevents from matching a track named "cover" or "karaoke" 
      $this->is_cover = (preg_match('/[\(\[\-].*(originally\sperformed|cover|tribute|karaoke)/i', $this->track_title) === 1 && strlen($this->track_title) > 8);

      // What about we strip all dirty addons strings from the title
      $matches = Map{};
      if (preg_match("/(?P<title>[^\[\(]*)(?:\s?[\(\[](?P<meta>.*)[\)\]]\s?)?/i", $this->track_title, $matches)) {
        $this->safe_track_title = trim($matches['title']);
        if (array_key_exists("meta", $matches)) {
          $this->extra_info->add(Pair{"context", str_replace("/\(\[\-\—/g", ' ', $matches['meta'])});
        }
      }
      $matches_feat = Map{};
      if (preg_match("/.*f(?:ea)?t(?:uring)?\.?\s?(?P<artist>[^\(\)\[\]\-]*)/i", $this->track_title, $matches_feat)) {
        $this->extra_info->add(Pair{"featuring", trim($matches_feat['artist'])});
      }
  
      // The underlying album should be introspected too
      $this->album->introspect();

      $this->introspected = true;
    }

    return $this;
  }

  public function getHash(bool $aggressive): string
  {
    if ($aggressive === true){
      return Utils::flatten(Vector {$this->album->getArtist(), $this->safe_track_title});
    } else {
      return Utils::flatten(Vector {$this->album->getArtist(), $this->album->getSafeTitle(), $this->safe_track_title});
    }
  }

  public static function merge(TrackEntity $a, TrackEntity $b): TrackEntity
  {
    // $a has precedence

    if ($a->getSafeTitle() === "") {
      $title = $b->getSafeTitle();
    } else {
      $title = $a->getSafeTitle();
    }

    // "Recurse" to album entity
    $album = AlbumEntity::merge($a->getAlbum(), $b->getAlbum());

    // Create the result
    $c = new TrackEntity($title, $album);
    $c->addLinks($a->getLinks()->addAll($b->getLinks()));

    if ($a->isIntrospected() === true && $b->isIntrospected() === true) {
      $c->setIntrospected($a->getExtraInfo()->setAll($b->getExtraInfo()));
    } // But do not force introspection

    return $c;
  }
}
