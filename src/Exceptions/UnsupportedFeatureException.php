<?php

namespace Th3JK\Treasurer\Exceptions;

use LogicException;

class UnsupportedFeatureException extends LogicException
{
    public static function forCapability(string $provider, string $capability): self
    {
        return new self("Gateway [{$provider}] does not support capability [{$capability}].");
    }

    public static function forValue(string $provider, string $value, string $kind): self
    {
        return new self("Gateway [{$provider}] does not support {$kind} [{$value}].");
    }
}
