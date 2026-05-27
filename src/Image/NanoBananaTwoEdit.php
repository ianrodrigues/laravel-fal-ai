<?php

namespace IanRodrigues\FalAi\Image;

use Illuminate\Support\Collection;
use Laravel\Ai\Responses\Data\GeneratedImage;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Usage;
use Laravel\Ai\Responses\ImageResponse;

class NanoBananaTwoEdit implements ModelHandler
{
    protected const ENDPOINT = 'fal-ai/nano-banana-2/edit';

    /** @var array<int, string> */
    protected const SUPPORTED = [
        'nano-banana-2/edit',
        'fal-ai/nano-banana-2/edit',
    ];

    /** @var array<int, string> */
    protected const PASSTHROUGH_OPTIONS = [
        'num_images',
        'seed',
        'safety_tolerance',
        'output_format',
        'system_prompt',
        'enable_web_search',
        'thinking_level',
        'limit_generations',
        'sync_mode',
    ];

    public function supports(string $model): bool
    {
        return in_array($model, self::SUPPORTED, true);
    }

    public function endpoint(string $model): string
    {
        return self::ENDPOINT;
    }

    public function buildPayload(
        string $prompt,
        array $imageUrls,
        ?string $size,
        ?string $quality,
        array $requestOptions,
        array $providerConfig,
    ): array {
        $payload = [
            'prompt' => $prompt,
            'image_urls' => array_values($imageUrls),
        ];

        if ($aspect = $this->aspectRatio($size, $requestOptions)) {
            $payload['aspect_ratio'] = $aspect;
        }

        if ($resolution = $this->resolution($quality, $requestOptions)) {
            $payload['resolution'] = $resolution;
        }

        foreach (self::PASSTHROUGH_OPTIONS as $key) {
            if (array_key_exists($key, $requestOptions)) {
                $payload[$key] = $requestOptions[$key];
            }
        }

        return $payload;
    }

    public function parseResponse(array $json, string $model): ImageResponse
    {
        $images = Collection::make($json['images'] ?? [])
            ->map(fn (array $image) => new GeneratedImage(
                $image['_b64'] ?? $image['url'] ?? '',
                $image['content_type'] ?? 'image/png',
            ));

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

    protected function aspectRatio(?string $size, array $requestOptions): ?string
    {
        return $requestOptions['aspect_ratio'] ?? $size;
    }

    protected function resolution(?string $quality, array $requestOptions): ?string
    {
        return $requestOptions['resolution'] ?? match ($quality) {
            'low' => '0.5K',
            'medium' => '1K',
            'high' => '2K',
            default => null,
        };
    }
}
