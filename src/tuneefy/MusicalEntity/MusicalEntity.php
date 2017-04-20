<?php

namespace tuneefy\MusicalEntity;

abstract class MusicalEntity implements MusicalEntityInterface
{
    const TYPE = 'musical_entity';

    protected $links;

    // Introspection
    protected $introspected;
    protected $extra_info;

    public function __construct()
    {
        $this->links = [];
        $this->introspected = false;
        $this->extra_info = [];
    }

    public function toArray(): array
    {
        return [
          'type' => self::TYPE,
        ];
    }

    /*
    Links getter and setter
    */
    public function addLink(string $platform, string $link): MusicalEntity
    {
        $this->links[] = ['platform' => $platform, 'link' => $link];
        return $this;
    }

    public function addLinks(array $links): MusicalEntity
    {
        $this->links = array_merge($this->links, $links);
        return $this;
    }

    public function getLinks(): array
    {
        return $this->links;
    }

    public function countLinks(): int
    {
        return count($this->links);
    }

    public function isIntrospected(): bool
    {
        return $this->introspected;
    }

    public function setIntrospected(array $extra_info = null): MusicalEntity
    {
        $this->introspected = true;
        if ($extra_info !== null) {
            $this->extra_info = $extra_info;
        }
        return $this;
    }

    public function getExtraInfo(): array
    {
        return $this->extra_info;
    }
}
