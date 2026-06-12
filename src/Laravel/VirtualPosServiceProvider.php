<?php

declare(strict_types=1);

namespace TexHub\VirtualPosAndoz\Laravel;

use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;
use TexHub\VirtualPosAndoz\Config;
use TexHub\VirtualPosAndoz\VirtualPos as VirtualPosClient;

class VirtualPosServiceProvider extends ServiceProvider implements DeferrableProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/virtual-pos-andoz.php', 'virtual-pos-andoz');

        $this->app->singleton(Config::class, function ($app): Config {
            return Config::fromArray((array) $app['config']->get('virtual-pos-andoz', []));
        });

        $this->app->singleton(VirtualPosClient::class, function ($app): VirtualPosClient {
            return new VirtualPosClient($app->make(Config::class));
        });

        $this->app->alias(VirtualPosClient::class, 'virtual-pos-andoz');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../../config/virtual-pos-andoz.php' => $this->app->configPath('virtual-pos-andoz.php'),
            ], 'virtual-pos-andoz-config');
        }
    }

    /**
     * @return array<int, string>
     */
    public function provides(): array
    {
        return [Config::class, VirtualPosClient::class, 'virtual-pos-andoz'];
    }
}
