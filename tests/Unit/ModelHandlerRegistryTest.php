<?php

use IanRodrigues\FalAi\Exceptions\UnknownModelException;
use IanRodrigues\FalAi\Image\ModelHandler;
use IanRodrigues\FalAi\Image\ModelHandlerRegistry;
use IanRodrigues\FalAi\Image\NanoBananaTwoEdit;

it('resolves a registered handler by model name', function () {
    $registry = new ModelHandlerRegistry;
    $registry->register(new NanoBananaTwoEdit);

    expect($registry->resolve('nano-banana-2/edit'))
        ->toBeInstanceOf(NanoBananaTwoEdit::class);
});

it('throws when no handler supports the given model', function () {
    $registry = new ModelHandlerRegistry;
    $registry->register(new NanoBananaTwoEdit);

    $registry->resolve('imagen-4');
})->throws(UnknownModelException::class);

it('lets later-registered handlers take precedence', function () {
    $custom = new class implements ModelHandler
    {
        public function supports(string $model): bool
        {
            return $model === 'nano-banana-2/edit';
        }

        public function endpoint(string $model): string
        {
            return 'custom/endpoint';
        }

        public function buildPayload(string $prompt, array $imageUrls, ?string $size, ?string $quality, array $requestOptions, array $providerConfig): array
        {
            return [];
        }

        public function parseResponse(array $json, string $model): \Laravel\Ai\Responses\ImageResponse
        {
            return new \Laravel\Ai\Responses\ImageResponse(
                collect(),
                new \Laravel\Ai\Responses\Data\Usage,
                new \Laravel\Ai\Responses\Data\Meta('fal', $model),
            );
        }

        public function requiresAttachments(): bool
        {
            return false;
        }
    };

    $registry = new ModelHandlerRegistry;
    $registry->register(new NanoBananaTwoEdit);
    $registry->register($custom);

    expect($registry->resolve('nano-banana-2/edit'))->toBe($custom);
});
