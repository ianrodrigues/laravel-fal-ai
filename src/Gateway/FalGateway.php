<?php

namespace IanRodrigues\FalAi\Gateway;

use IanRodrigues\FalAi\Exceptions\MissingAttachmentsException;
use IanRodrigues\FalAi\FalProvider;
use IanRodrigues\FalAi\Gateway\Concerns\FetchesResults;
use IanRodrigues\FalAi\Gateway\Concerns\MapsAttachments;
use IanRodrigues\FalAi\Gateway\Concerns\PollsQueueStatus;
use IanRodrigues\FalAi\Gateway\Concerns\SubmitsToQueue;
use IanRodrigues\FalAi\Image\ModelHandlerRegistry;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Laravel\Ai\Contracts\Gateway\Gateway;
use Laravel\Ai\Contracts\Gateway\ImageGateway;
use Laravel\Ai\Contracts\Providers\ImageProvider;
use Laravel\Ai\Responses\ImageResponse;
use RuntimeException;

class FalGateway implements Gateway, ImageGateway
{
    use FetchesResults;
    use MapsAttachments;
    use PollsQueueStatus;
    use SubmitsToQueue;

    public function __construct(
        protected Dispatcher $events,
        protected ModelHandlerRegistry $registry,
    ) {}

    public function generateImage(
        ImageProvider $provider,
        string $model,
        string $prompt,
        array $attachments = [],
        ?string $size = null,
        ?string $quality = null,
        ?int $timeout = null,
    ): ImageResponse {
        if (! $provider instanceof FalProvider) {
            throw new InvalidArgumentException(
                'FalGateway requires a FalProvider; got ['.$provider::class.'].',
            );
        }

        $handler = $this->registry->resolve($model);

        if ($handler->requiresAttachments() && empty($attachments)) {
            throw new MissingAttachmentsException(
                "fal model [{$model}] requires at least one input image attachment.",
            );
        }

        $apiKey = $this->apiKey($provider);
        $imageUrls = $this->mapAttachments($attachments, $apiKey);

        $payload = $handler->buildPayload(
            prompt: $prompt,
            imageUrls: $imageUrls,
            size: $size,
            quality: $quality,
            requestOptions: $provider->pullRequestOptions(),
            providerConfig: $provider->additionalConfiguration(),
        );

        $submitted = $this->submitToQueue($handler->endpoint($model), $payload, $apiKey);

        $this->pollUntilComplete($submitted['status_url'], $apiKey, $timeout);

        $result = $this->fetchResult($submitted['response_url'], $apiKey);

        return $handler->parseResponse($result, $model);
    }

    protected function client(string $apiKey): PendingRequest
    {
        return Http::withToken($apiKey, 'Key')
            ->acceptJson()
            ->timeout((int) config('fal-ai.request_timeout', 30))
            ->connectTimeout((int) config('fal-ai.connect_timeout', 10));
    }

    protected function apiKey(FalProvider $provider): string
    {
        $key = (string) ($provider->providerCredentials()['key'] ?? '')
            ?: (string) config('fal-ai.key');

        if ($key === '') {
            throw new RuntimeException(
                'Missing fal API key. Set FAL_KEY in your environment or configure ai.providers.fal.key.',
            );
        }

        return $key;
    }
}
