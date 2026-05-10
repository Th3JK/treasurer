<?php

namespace Th3JK\Treasurer;

use Illuminate\Support\ServiceProvider;
use RuntimeException;
use Th3JK\Treasurer\Contracts\Gateway;
use Th3JK\Treasurer\Drivers\Comgate\ComgateGateway;
use Th3JK\Treasurer\Drivers\GoPay\GoPayGateway;
use Th3JK\Treasurer\Support\GatewayRegistry;

class TreasurerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/treasurer.php', 'treasurer');

        $this->app->singleton(GatewayRegistry::class, function () {
            $registry = new GatewayRegistry;
            $registry->extend(GoPayGateway::NAME, fn (array $config) => new GoPayGateway($config));
            $registry->extend(ComgateGateway::NAME, fn (array $config) => new ComgateGateway($config));

            return $registry;
        });

        $this->app->singleton(Gateway::class, function ($app) {
            $name = $app['config']->get('treasurer.default');

            if (! is_string($name) || $name === '') {
                throw new RuntimeException(
                    'Treasurer: no default gateway configured. Set TREASURER_PROVIDER or treasurer.default.'
                );
            }

            $config = (array) $app['config']->get("treasurer.providers.{$name}", []);

            return $app[GatewayRegistry::class]->resolve($name, $config);
        });

        $this->app->singleton(Treasurer::class);
        $this->app->alias(Treasurer::class, 'treasurer');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/treasurer.php' => config_path('treasurer.php'),
            ], 'treasurer-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'treasurer-migrations');
        }

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
