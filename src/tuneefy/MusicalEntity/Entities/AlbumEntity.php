<?php

namespace tuneefy\MusicalEntity\Entities;

use tuneefy\MusicalEntity\MusicalEntity;
use tuneefy\MusicalEntity\MusicalEntityInterface;
use tuneefy\Utils\Utils;

class AlbumEntity extends MusicalEntity
{
    const TYPE = 'album';

    private $title;
    private $artist;
    private $picture;

    // Introspection
    private $safe_title;

    public function __construct(string $title, string $artist, string $picture)
    {
        parent::__construct();
        $this->title = $title;
        $this->artist = $artist;
        $this->picture = $picture;

        $this->extra_info = null;
        $this->safe_title = $title;

        $this->introspect();
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

    public function toArray(): array
    {
        $result = [
      'type' => self::TYPE,
      'title' => $this->title,
      'artist' => $this->artist,
      'picture' => $this->picture,
    ];

        if ($this->countLinks() !== 0) {
            $result['links'] = $this->links;
        }

        if ($this->introspected === true) {
            $result['safe_title'] = $this->safe_title;
            $result['extra_info'] = $this->extra_info;
        }

        return $result;
    }

  /*
    Strips unnecessary words from an album title
    And extracts extra info
  */
  public function introspect(): MusicalEntityInterface
  {
      if ($this->introspected === false) {
          // What about we strip all dirty addons strings from the title
      $matches = [];
          if (preg_match("/(?P<title>.*?)\s?[\(\[\-\—]\s*(?P<meta>.*)/i", $this->title, $matches)) {
              $this->safe_title = trim($matches['title']);
              if (array_key_exists('meta', $matches)) {
                  $this->extra_info['context'] = str_replace("/\(\[\-\—/g", ' ', $matches['meta']);
              }
          }

          $this->introspected = true;
      }

      return $this;
  }

    public function setSafeTitle(string $safe_title): AlbumEntity
    {
        $this->safe_title = $safe_title;

        return $this;
    }

    public function getHash(bool $aggressive): string
    {
        if ($aggressive === true) {
            return Utils::flatten([$this->safe_title]);
        } else {
            return Utils::flatten([$this->artist, $this->safe_title]);
        }
    }

    public static function merge(AlbumEntity $a, AlbumEntity $b): AlbumEntity
    {
        // $a has precedence

    if ($a->getTitle() === '') {
        $title = $b->getTitle();
        $safe_title = $b->getSafeTitle();
    } else {
        $title = $a->getTitle();
        $safe_title = $a->getSafeTitle();
    }

        if ($a->getArtist() === '') {
            $artist = $b->getArtist();
        } else {
            $artist = $a->getArtist();
        }

        if ($a->getPicture() === '') {
            $picture = $b->getPicture();
        } else {
            $picture = $a->getPicture();
        }

    // Create the result
    $c = new self($title, $artist, $picture);
        $c->addLinks($a->getLinks()->addAll($b->getLinks()));

        if ($a->isIntrospected() === true && $b->isIntrospected() === true) {
            $c->setIntrospected($a->getExtraInfo()->setAll($b->getExtraInfo()));
            $c->setSafeTitle($safe_title);
        } // But do not force introspection

    return $c;
    }
}
