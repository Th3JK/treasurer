<?php

namespace Th3JK\Treasurer\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Th3JK\Treasurer\TreasurerServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    protected function getPackageProviders($app): array
    {
        return [
            TreasurerServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);

        $app['config']->set('treasurer.default', 'gopay');
        $app['config']->set('treasurer.providers.gopay', [
            'environment' => 'sandbox',
            'credentials' => [
                'goid' => '123456',
                'client_id' => 'test-client',
                'client_secret' => 'test-secret',
            ],
            'urls' => [
                'return' => 'https://example.test/return',
                'notification' => 'https://example.test/notify',
            ],
            'options' => [
                'currency' => 'CZK',
                'language' => 'CS',
                'timeout' => 30,
            ],
        ]);
    }
}
