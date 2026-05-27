<?php

namespace IanRodrigues\FalAi\Gateway\Concerns;

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
            $json['images'] = $this->inlineImages($json['images'] ?? []);
        }

        return $json;
    }

    /**
     * @param  array<int, array<string, mixed>>  $images
     * @return array<int, array<string, mixed>>
     */
    protected function inlineImages(array $images): array
    {
        $timeout = (int) config('fal-ai.image_download_timeout', 30);

        return array_map(function (array $image) use ($timeout): array {
            if (isset($image['_b64'])) {
                return $image;
            }

            $url = $image['url'] ?? null;

            if (! is_string($url) || $url === '') {
                return $image;
            }

            $response = Http::timeout($timeout)->get($url);

            if ($response->failed()) {
                throw FalRequestException::from($response, "image download {$url}");
            }

            $image['_b64'] = base64_encode($response->body());

            return $image;
        }, $images);
    }

    abstract protected function client(string $apiKey): PendingRequest;
}
