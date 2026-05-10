<?php

namespace Th3JK\Treasurer\Support;

use Closure;
use InvalidArgumentException;
use Th3JK\Treasurer\Contracts\Gateway;

final class GatewayRegistry
{
    /** @var array<string, Closure(array<string, mixed>): Gateway> */
    private array $factories = [];

    /**
     * Register a gateway factory under a name. The factory receives the
     * provider's config block (treasurer.providers.<name>) and must return
     * a fully-constructed driver.
     */
    public function extend(string $name, Closure $factory): void
    {
        $this->factories[$name] = $factory;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public function resolve(string $name, array $config): Gateway
    {
        if (! isset($this->factories[$name])) {
            throw new InvalidArgumentException(
                "Treasurer: unknown gateway [{$name}]. Registered: ".implode(', ', $this->names()).'.'
            );
        }

        return ($this->factories[$name])($config);
    }

    /** @return list<string> */
    public function names(): array
    {
        return array_keys($this->factories);
    }
}
