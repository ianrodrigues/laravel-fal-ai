<?php

namespace IanRodrigues\FalAi;

use IanRodrigues\FalAi\Gateway\FalGateway;
use IanRodrigues\FalAi\Image\ModelHandler;
use IanRodrigues\FalAi\Image\ModelHandlerRegistry;
use IanRodrigues\FalAi\Image\NanoBananaTwoEdit;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\ServiceProvider;
use Laravel\Ai\AiManager;

class FalServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/fal-ai.php', 'fal-ai');

        $this->app->singleton(ModelHandlerRegistry::class, function ($app) {
            $registry = new ModelHandlerRegistry;

            $handlers = (array) $app['config']->get('fal-ai.image.models', [NanoBananaTwoEdit::class]);

            foreach ($handlers as $handler) {
                $instance = $handler instanceof ModelHandler ? $handler : $app->make($handler);
                $registry->register($instance);
            }

            return $registry;
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/fal-ai.php' => config_path('fal-ai.php'),
        ], 'fal-ai-config');

        $this->app->afterResolving(AiManager::class, function (AiManager $manager, $app) {
            $manager->extend('fal', function ($app, array $config) {
                return new FalProvider(
                    new FalGateway(
                        $app->make(Dispatcher::class),
                        $app->make(ModelHandlerRegistry::class),
                    ),
                    $config,
                    $app->make(Dispatcher::class),
                );
            });
        });
    }
}
