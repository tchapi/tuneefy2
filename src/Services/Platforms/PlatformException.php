<?php

namespace App\Services\Platforms;

class PlatformException extends \Exception
{
    public function __construct(Platform $platform, ?string $message = null)
    {
        parent::__construct('The '.$platform->getName().' platform did not respond correctly'.($message ? ': '.$message : '').'.');
    }
}
