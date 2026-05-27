<?php

namespace IanRodrigues\FalAi\Image\Concerns;

use IanRodrigues\FalAi\Exceptions\FalRequestException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Files\Base64Image;
use Laravel\Ai\Files\Image;
use Laravel\Ai\Files\LocalImage;
use Laravel\Ai\Files\RemoteImage;
use Laravel\Ai\Files\StoredImage;

trait MapsAttachments
{
    /**
     * @param  array<int, Image>  $attachments
     * @return array<int, string>
     */
    protected function mapAttachments(array $attachments, string $apiKey): array
    {
        return array_map(
            fn (Image $image): string => $this->toFalUrl($image, $apiKey),
            $attachments,
        );
    }

    protected function toFalUrl(Image $image, string $apiKey): string
    {
        return match (true) {
            $image instanceof RemoteImage => $image->url,
            $image instanceof LocalImage => $this->uploadBinary(
                contents: (string) file_get_contents($image->path),
                filename: basename($image->path),
                mime: $image->mime ?? 'application/octet-stream',
                apiKey: $apiKey,
            ),
            $image instanceof StoredImage => $this->uploadBinary(
                contents: (string) Storage::disk($image->disk)->get($image->path),
                filename: basename($image->path),
                mime: $image->mime ?? 'application/octet-stream',
                apiKey: $apiKey,
            ),
            $image instanceof Base64Image => $this->uploadBinary(
                contents: base64_decode($this->stripDataUri($image->base64)),
                filename: 'attachment.'.$this->extensionForMime($image->mime),
                mime: $image->mime ?? 'image/png',
                apiKey: $apiKey,
            ),
            default => throw new \InvalidArgumentException(
                'Unsupported attachment type ['.$image::class.'] for fal provider.',
            ),
        };
    }

    protected function uploadBinary(string $contents, string $filename, string $mime, string $apiKey): string
    {
        $uploadUrl = (string) config('fal-ai.storage_upload_url');

        $response = $this->client($apiKey)
            ->attach('file', $contents, $filename, ['Content-Type' => $mime])
            ->post($uploadUrl);

        if ($response->failed()) {
            throw FalRequestException::from($response, 'storage upload');
        }

        $url = $response->json('access_url') ?? $response->json('url');

        if (! is_string($url) || $url === '') {
            throw new FalRequestException('fal storage upload returned no usable URL.');
        }

        return $url;
    }

    abstract protected function client(string $apiKey): PendingRequest;

    protected function stripDataUri(string $base64): string
    {
        if (str_starts_with($base64, 'data:')) {
            $comma = strpos($base64, ',');

            return $comma === false ? $base64 : substr($base64, $comma + 1);
        }

        return $base64;
    }

    protected function extensionForMime(?string $mime): string
    {
        return match ($mime) {
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            null, '' => 'bin',
            default => str_contains($mime, '/') ? substr($mime, strpos($mime, '/') + 1) : 'bin',
        };
    }
}
