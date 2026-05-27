<?php

namespace IanRodrigues\FalAi\Gateway\Concerns;

use IanRodrigues\FalAi\Exceptions\FalQueueTimeoutException;
use IanRodrigues\FalAi\Exceptions\FalRequestException;
use Illuminate\Http\Client\PendingRequest;

trait PollsQueueStatus
{
    protected function pollUntilComplete(string $statusUrl, string $apiKey, ?int $timeoutSeconds): void
    {
        $timeout = $timeoutSeconds ?? (int) config('fal-ai.queue.timeout', 120);
        $delay = (int) config('fal-ai.queue.initial_delay_ms', 1000);
        $maxDelay = (int) config('fal-ai.queue.max_delay_ms', 5000);
        $deadline = $this->now() + $timeout;

        while ($this->now() < $deadline) {
            $response = $this->client($apiKey)->get($statusUrl);

            if ($response->failed()) {
                throw FalRequestException::from($response, 'queue status');
            }

            $status = (string) ($response->json('status') ?? '');

            if ($status === 'COMPLETED') {
                return;
            }

            if (in_array($status, ['FAILED', 'CANCELLED', 'ERROR'], true)) {
                throw new FalRequestException(
                    "fal job ended with status [{$status}]: ".$response->body(),
                );
            }

            $this->sleepMs($delay);
            $delay = min((int) ($delay * 1.5), $maxDelay);
        }

        throw new FalQueueTimeoutException(
            "fal job did not complete within {$timeout} seconds.",
        );
    }

    protected function now(): int
    {
        return time();
    }

    protected function sleepMs(int $ms): void
    {
        usleep($ms * 1000);
    }

    abstract protected function client(string $apiKey): PendingRequest;
}
