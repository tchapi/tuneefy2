<?php

namespace tuneefy\MusicalEntity;

class RedirectEntity
{
    public const TYPE = 'redirect';

    private $originalItemUid;

    public function __construct()
    {
        $this->originalItemUid = null;
    }

    public function toArray(): array
    {
        return [
          'type' => static::TYPE,
          'originalItemUid' => $this->getOriginalItemUid(),
        ];
    }

    public function getType(): string
    {
        return static::TYPE;
    }

    public function getOriginalItemUid(): string
    {
        return $this->originalItemUid;
    }
}
