<?php

namespace IanRodrigues\FalAi\Gateway\Concerns;

use IanRodrigues\FalAi\Exceptions\FalRequestException;
use Illuminate\Http\Client\PendingRequest;

trait SubmitsToQueue
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array{request_id: string, status_url: string, response_url: string, cancel_url?: string, queue_position?: int}
     */
    protected function submitToQueue(string $endpoint, array $payload, string $apiKey): array
    {
        $baseUrl = rtrim((string) config('fal-ai.base_url'), '/');
        $url = "{$baseUrl}/{$endpoint}";

        $response = $this->client($apiKey)->post($url, $payload);

        if ($response->failed()) {
            throw FalRequestException::from($response, "queue submit {$endpoint}");
        }

        $data = $response->json();

        if (! is_array($data) || ! isset($data['status_url'], $data['response_url'])) {
            throw new FalRequestException("fal queue submit returned an unexpected payload for [{$endpoint}].");
        }

        return $data;
    }

    abstract protected function client(string $apiKey): PendingRequest;
}
