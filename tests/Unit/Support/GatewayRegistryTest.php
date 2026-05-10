<?php

namespace Th3JK\Treasurer\Tests\Unit\Support;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Th3JK\Treasurer\Contracts\Gateway;
use Th3JK\Treasurer\Support\GatewayRegistry;

class GatewayRegistryTest extends TestCase
{
    #[Test]
    public function it_resolves_a_registered_factory(): void
    {
        $registry = new GatewayRegistry;
        $registry->extend('fake', fn (array $config) => $this->stubGateway($config));

        $gateway = $registry->resolve('fake', ['answer' => 42]);

        $this->assertSame('fake', $gateway->getName());
        $this->assertSame(42, $gateway->config['answer']);
    }

    #[Test]
    public function it_lists_registered_gateway_names(): void
    {
        $registry = new GatewayRegistry;
        $registry->extend('a', fn () => $this->stubGateway([]));
        $registry->extend('b', fn () => $this->stubGateway([]));

        $this->assertSame(['a', 'b'], $registry->names());
    }

    #[Test]
    public function it_throws_when_resolving_an_unregistered_name(): void
    {
        $registry = new GatewayRegistry;
        $registry->extend('gopay', fn () => $this->stubGateway([]));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('unknown gateway [stripe]');

        $registry->resolve('stripe', []);
    }

    private function stubGateway(array $config): Gateway
    {
        return new class($config) implements Gateway
        {
            public function __construct(public array $config) {}

            public function getName(): string
            {
                return 'fake';
            }
        };
    }
}
