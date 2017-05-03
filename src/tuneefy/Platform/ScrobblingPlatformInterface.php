<?php

namespace tuneefy\Platform;

interface ScrobblingPlatformInterface
{
    public function hasPermalink(string $permalink): bool;

    public function expandPermalink(string $permalink, int $mode);
}
