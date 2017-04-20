<?php

namespace tuneefy\MusicalEntity;

interface MusicalEntityInterface
{
    public function toMap(): array;

    public function introspect(): MusicalEntityInterface;

    public function getHash(bool $aggressive): string;
}
