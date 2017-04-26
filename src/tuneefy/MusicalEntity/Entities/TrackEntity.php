<?php

namespace tuneefy\MusicalEntity\Entities;

use tuneefy\MusicalEntity\MusicalEntity;
use tuneefy\MusicalEntity\MusicalEntityInterface;
use tuneefy\Utils\Utils;

class TrackEntity extends MusicalEntity
{
    const TYPE = 'track';

    private $track_title;
    private $album;

    // Introspection
    private $is_cover;
    private $safe_track_title;

    public function __construct(string $track_title, AlbumEntity $album)
    {
        parent::__construct();
        $this->track_title = $track_title;
        $this->album = $album;

        $this->is_cover = false;
        $this->safe_track_title = $track_title;

        $this->introspect();
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

    public function toArray(): array
    {
        $album = $this->album->toArray();
        unset($album['type']); // Do not type the subresult

        $result = [
          'type' => self::TYPE,
          'title' => $this->track_title,
          'album' => $album,
        ];

        if ($this->countLinks() !== 0) {
            $result['links'] = $this->links;
        }

        if ($this->introspected === true) {
            $result['cover'] = $this->is_cover;
            $result['safe_title'] = $this->safe_track_title;
            $result['extra_info'] = $this->extra_info;
        }

        return $result;
    }

    /*
      Strips unnecessary words from a track title
      And extracts extra_info
    */
    public function introspect(): MusicalEntityInterface
    {
        if ($this->introspected === false) {
            
            // https://secure.php.net/manual/en/function.extract.php
            extract(parent::parse($this->title));
            $this->safe_track_title = $safe_title;
            $this->extra_info = $extra_info;

            $this->album->introspect();

            $this->introspected = true;
        }

        return $this;
    }

    public function setSafeTitle(string $safe_title): TrackEntity
    {
        $this->safe_track_title = $safe_title;

        return $this;
    }

    public function getHash(bool $aggressive): string
    {
        if ($aggressive === true) {
            return Utils::flatten([$this->album->getArtist(), $this->safe_track_title]);
        } else {
            return Utils::flatten([$this->album->getArtist(), $this->album->getSafeTitle(), $this->safe_track_title]);
        }
    }

    public static function merge(TrackEntity $a, TrackEntity $b): TrackEntity
    {
        // $a has precedence
        if ($a->getTitle() === '') {
            $title = $b->getTitle();
            $safe_title = $b->getSafeTitle();
        } else {
            $title = $a->getTitle();
            $safe_title = $a->getSafeTitle();
        }

        // "Recurse" to album entity
        $album = AlbumEntity::merge($a->getAlbum(), $b->getAlbum());

        // Create the result
        $c = new self($title, $album);
        $c->addLinks(array_merge($a->getLinks(), $b->getLinks()));

        if ($a->isIntrospected() === true && $b->isIntrospected() === true) {
            $c->setIntrospected(array_merge($a->getExtraInfo(), $b->getExtraInfo()));
            $c->setSafeTitle($safe_title);
        } // But do not force introspection

        return $c;
    }
}
