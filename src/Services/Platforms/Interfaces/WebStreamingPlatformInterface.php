<?php

namespace App\Services\Platforms\Interfaces;

use App\Services\Platforms\PlatformResult;

interface WebStreamingPlatformInterface
{
    public function hasPermalink(string $permalink): bool;

    public function expandPermalink(string $permalink, int $mode): PlatformResult;
}
