<?php

namespace tuneefy\MusicalEntity;

interface MusicalEntityInterface
{
    public function toArray(): array;

    public function introspect(): MusicalEntityInterface;

    public function getHash(bool $aggressive): string;
}
