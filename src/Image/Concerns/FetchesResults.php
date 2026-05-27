<?php

namespace IanRodrigues\FalAi\Image\Concerns;

use IanRodrigues\FalAi\Exceptions\FalRequestException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

trait FetchesResults
{
    /** @return array<string, mixed> */
    protected function fetchResult(string $responseUrl, string $apiKey): array
    {
        $response = $this->client($apiKey)->get($responseUrl);

        if ($response->failed()) {
            throw FalRequestException::from($response, 'queue result');
        }

        $json = $response->json();

        if (! is_array($json)) {
            throw new FalRequestException('fal queue result returned a non-JSON body.');
        }

        if (config('fal-ai.fetch_images', true)) {
            if (isset($json['images']) && is_array($json['images'])) {
                $json['images'] = array_map(fn (array $image) => $this->inlineImage($image), $json['images']);
            }

            if (isset($json['image']) && is_array($json['image'])) {
                $json['image'] = $this->inlineImage($json['image']);
            }
        }

        return $json;
    }

    /**
     * @param  array<string, mixed>  $image
     * @return array<string, mixed>
     */
    protected function inlineImage(array $image): array
    {
        if (isset($image['_b64'])) {
            return $image;
        }

        $url = $image['url'] ?? null;

        if (! is_string($url) || $url === '') {
            return $image;
        }

        $timeout = (int) config('fal-ai.image_download_timeout', 30);
        $response = Http::timeout($timeout)->get($url);

        if ($response->failed()) {
            throw FalRequestException::from($response, "image download {$url}");
        }

        $image['_b64'] = base64_encode($response->body());

        return $image;
    }

    abstract protected function client(string $apiKey): PendingRequest;
}
