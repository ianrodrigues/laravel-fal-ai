<?php

namespace IanRodrigues\FalAi\Image;

use Illuminate\Support\Collection;
use Laravel\Ai\Responses\Data\GeneratedImage;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Usage;
use Laravel\Ai\Responses\ImageResponse;

class BireFNet implements ModelHandler
{
    /** @var array<int, string> */
    protected const SUPPORTED = [
        'birefnet',
        'fal-ai/birefnet',
        'birefnet/v1',
        'fal-ai/birefnet/v1',
        'birefnet/v2',
        'fal-ai/birefnet/v2',
    ];

    /** @var array<int, string> */
    protected const PASSTHROUGH_OPTIONS = [
        'model',
        'output_format',
        'sync_mode',
    ];

    public function supports(string $model): bool
    {
        return in_array($model, self::SUPPORTED, true);
    }

    public function endpoint(string $model): string
    {
        $normalized = str_starts_with($model, 'fal-ai/') ? $model : 'fal-ai/'.$model;

        return str_ends_with($normalized, '/v1')
            ? 'fal-ai/birefnet'
            : 'fal-ai/birefnet/v2';
    }

    public function buildPayload(
        string $prompt,
        array $imageUrls,
        ?string $size,
        ?string $quality,
        array $requestOptions,
        array $providerConfig,
    ): array {
        $payload = ['image_url' => $imageUrls[0]];

        foreach (self::PASSTHROUGH_OPTIONS as $key) {
            if (array_key_exists($key, $requestOptions)) {
                $payload[$key] = $requestOptions[$key];
            }
        }

        return $payload;
    }

    public function parseResponse(array $json, string $model): ImageResponse
    {
        $image = $json['image'] ?? null;

        $images = is_array($image)
            ? Collection::make([
                new GeneratedImage(
                    $image['_b64'] ?? $image['url'] ?? '',
                    $image['content_type'] ?? 'image/png',
                ),
            ])
            : Collection::make();

        return new ImageResponse(
            $images,
            new Usage,
            new Meta('fal', $model),
        );
    }

    public function requiresAttachments(): bool
    {
        return true;
    }
}
