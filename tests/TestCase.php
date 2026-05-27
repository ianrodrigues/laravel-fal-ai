<?php

namespace IanRodrigues\FalAi\Tests;

use IanRodrigues\FalAi\FalProvider;
use IanRodrigues\FalAi\FalServiceProvider;
use IanRodrigues\FalAi\Gateway\FalGateway;
use IanRodrigues\FalAi\Image\ModelHandlerRegistry;
use IanRodrigues\FalAi\Image\NanoBananaTwoEdit;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\AiServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            AiServiceProvider::class,
            FalServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('ai.providers.fal', [
            'driver' => 'fal',
            'key' => 'test-fal-key',
        ]);

        $app['config']->set('fal-ai.key', 'test-fal-key');
        $app['config']->set('fal-ai.queue.initial_delay_ms', 0);
        $app['config']->set('fal-ai.queue.max_delay_ms', 0);
        $app['config']->set('fal-ai.queue.timeout', 5);
    }

    protected function falGateway(): FalGateway
    {
        $registry = new ModelHandlerRegistry;
        $registry->register(new NanoBananaTwoEdit);

        return new FalGateway($this->app->make(Dispatcher::class), $registry);
    }

    protected function falProvider(array $extraConfig = []): FalProvider
    {
        return new FalProvider(
            $this->falGateway(),
            ['driver' => 'fal', 'name' => 'fal', 'key' => 'test-fal-key', ...$extraConfig],
            $this->app->make(Dispatcher::class),
        );
    }

    protected function fakeFalQueueRoundtrip(array $resultImages = []): void
    {
        Http::fake([
            'queue.fal.run/fal-ai/nano-banana-2/edit' => Http::response([
                'request_id' => 'req-test',
                'status_url' => 'https://queue.fal.run/status',
                'response_url' => 'https://queue.fal.run/result',
            ]),
            'queue.fal.run/status' => Http::response(['status' => 'COMPLETED']),
            'queue.fal.run/result' => Http::response(['images' => $resultImages]),
            'rest.alpha.fal.ai/storage/upload' => Http::response([
                'access_url' => 'https://fal.media/uploads/fake-uploaded.png',
            ]),
        ]);
    }
}
