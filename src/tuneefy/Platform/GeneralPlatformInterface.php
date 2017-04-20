<?php

namespace tuneefy\Platform;

interface GeneralPlatformInterface
{
    // Basics
    public function getName(): string;

    public function getTag(): string;

    public function getColor(): string;

    // Enabled & default
    public function isDefault(): bool;

    public function isEnabledForApi(): bool;

    public function isEnabledForWebsite(): bool;

    // Capabilities
    public function isCapableOfSearchingTracks(): bool;

    public function isCapableOfSearchingAlbums(): bool;

    public function isCapableOfLookingUp(): bool;

    // IMPLEMENTED IN CHILD CLASSES
    //abstract public function getNormalizedResults($itemType, $result, $limit);
}
