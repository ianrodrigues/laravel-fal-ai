<?php

namespace IanRodrigues\FalAi\Image;

use Laravel\Ai\Responses\ImageResponse;

interface ModelHandler
{
    public function supports(string $model): bool;

    public function endpoint(string $model): string;

    /**
     * @param  array<int, string>  $imageUrls
     * @param  array<string, mixed>  $requestOptions
     * @param  array<string, mixed>  $providerConfig
     * @return array<string, mixed>
     */
    public function buildPayload(
        string $prompt,
        array $imageUrls,
        ?string $size,
        ?string $quality,
        array $requestOptions,
        array $providerConfig,
    ): array;

    /**
     * Parse fal's response into an ImageResponse.
     *
     * When `fal-ai.fetch_images` is enabled, each image entry has an
     * additional `_b64` key with the downloaded base64 payload. Prefer it
     * over `url` when present.
     *
     * @param  array<string, mixed>  $json
     */
    public function parseResponse(array $json, string $model): ImageResponse;

    public function requiresAttachments(): bool;
}
