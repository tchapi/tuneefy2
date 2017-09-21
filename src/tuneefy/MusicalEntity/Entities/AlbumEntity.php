<?php

namespace tuneefy\MusicalEntity\Entities;

use tuneefy\MusicalEntity\MusicalEntity;
use tuneefy\MusicalEntity\MusicalEntityInterface;
use tuneefy\MusicalEntity\MusicalEntityMergeException;
use tuneefy\Utils\Utils;

class AlbumEntity extends MusicalEntity
{
    const TYPE = 'album';

    private $title;
    private $artist;
    private $picture;

    // Introspection
    private $safe_title;

    public function __construct(string $title = '', string $artist = '', string $picture = '')
    {
        parent::__construct();
        $this->title = $title;
        $this->artist = $artist;
        $this->picture = $picture;

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

        if ($this->countLinkedPlatforms() !== 0) {
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
            // https://secure.php.net/manual/en/function.extract.php
            extract(parent::parse($this->title));
            $this->safe_title = $safe_title;
            $this->extra_info = $extra_info;

            $this->introspected = true;
        }

        return $this;
    }

    public function setSafeTitle(string $safe_title): AlbumEntity
    {
        $this->safe_title = $safe_title;

        return $this;
    }

    public function getHash(bool $aggressive = false): string
    {
        if ($aggressive === true) {
            return Utils::flatten([$this->getExtraInfoHash(), $this->safe_title]);
        } else {
            return Utils::flatten([$this->getExtraInfoHash(), $this->artist, $this->safe_title]);
        }
    }

    public static function merge(AlbumEntity $a, AlbumEntity $b, bool $force = false): AlbumEntity
    {
        // We should only merge tracks that are both covers, acoustic or remixes together
        if (!$force && ($a->isCover() !== $b->isCover() ||
            $a->isRemix() !== $b->isRemix() ||
            $a->isAcoustic() !== $b->isAcoustic())) {
            throw new MusicalEntityMergeException('Impossible to merge album - not forced.');
        }

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

        foreach ($b->getLinks() as $platform => $links) {
            foreach ($links as $link) {
                $a->addLink($platform, $link);
            }
        }

        $c->setLinks($a->getLinks());
        $c->setExtraInfo([
            'is_cover' => $a->isCover() || $b->isCover(),
            'is_remix' => $a->isRemix() || $b->isRemix(),
            'acoustic' => $a->isAcoustic() || $b->isAcoustic(),
            'context' => array_unique(array_merge(
                    $a->getExtraInfo()['context'],
                    $b->getExtraInfo()['context']
                ), SORT_REGULAR),
        ]);
        $c->setSafeTitle($safe_title);

        return $c;
    }
}
