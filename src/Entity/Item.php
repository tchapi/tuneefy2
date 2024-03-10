<?php

namespace App\Entity;

use App\Dataclass\MusicalEntity\MusicalEntity;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: "App\Repository\ItemRepository")]
#[ORM\Table(name: 'items')]
class Item
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\Column(type: 'string', length: 170, nullable: true)]
    private $intent;

    #[ORM\Column(type: 'blob', nullable: false)]
    private $object;

    #[ORM\Column(type: 'string', length: 170, nullable: true)]
    private $track;

    #[ORM\Column(type: 'string', length: 170, nullable: true)]
    private $album;

    #[ORM\Column(type: 'string', length: 170, nullable: true)]
    private $artist;

    #[ORM\Column(type: 'datetime', nullable: false)]
    private $createdAt;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private $expiresAt;

    #[ORM\Column(type: 'string', length: 170, nullable: false)]
    private $signature;

    #[ORM\Column(type: 'string', length: 80, nullable: true)]
    private $clientId;

    public function setIntent(string $intent): self
    {
        $this->intent = $intent;

        return $this;
    }

    public function getMusicalEntity(): MusicalEntity|false
    {
        // Migrating from the previous namespace
        // It works because the class names have the same length (with the namespace)
        $migratedObject = str_replace(
            ['tuneefy\MusicalEntity\Entities\AlbumEntity', 'tuneefy\MusicalEntity\Entities\TrackEntity'],
            ['App\Dataclass\MusicalEntity\Entities\Album', 'App\Dataclass\MusicalEntity\Entities\Track'],
            $this->getObject()
        );

        $objectAsMusicalEntity = unserialize($migratedObject);

        return $objectAsMusicalEntity;
    }

    public function getObject(): string
    {
        if (is_resource($this->object)) {
            $this->object = stream_get_contents($this->object);
        }

        return $this->object;
    }

    public function setMusicalEntity(MusicalEntity $entity): self
    {
        $entityAsString = serialize($entity);
        $this->object = $entityAsString;

        return $this;
    }

    public function setObject(string $object): self
    {
        $this->object = $object;

        return $this;
    }

    public function setTrack(string $track): self
    {
        $this->track = $track;

        return $this;
    }

    public function setArtist(string $artist): self
    {
        $this->artist = $artist;

        return $this;
    }

    public function setAlbum(string $album): self
    {
        $this->album = $album;

        return $this;
    }

    public function setCreatedAt(\DateTime $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function setExpiresAt(\DateTime $expiresAt): self
    {
        $this->expiresAt = $expiresAt;

        return $this;
    }

    public function getSignature(): string
    {
        return $this->signature;
    }

    public function setSignature(string $signature): self
    {
        $this->signature = $signature;

        return $this;
    }

    public function setClientId(?string $clientId): self
    {
        $this->clientId = $clientId;

        return $this;
    }
}
