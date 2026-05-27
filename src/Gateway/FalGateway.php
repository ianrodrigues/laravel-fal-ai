<?php

namespace IanRodrigues\FalAi\Gateway;

use BadMethodCallException;
use Closure;
use Generator;
use Laravel\Ai\Contracts\Files\TranscribableAudio;
use Laravel\Ai\Contracts\Gateway\Gateway;
use Laravel\Ai\Contracts\Providers\AudioProvider;
use Laravel\Ai\Contracts\Providers\EmbeddingProvider;
use Laravel\Ai\Contracts\Providers\ImageProvider;
use Laravel\Ai\Contracts\Providers\TextProvider;
use Laravel\Ai\Contracts\Providers\TranscriptionProvider;
use Laravel\Ai\Gateway\TextGenerationOptions;
use Laravel\Ai\Responses\AudioResponse;
use Laravel\Ai\Responses\EmbeddingsResponse;
use Laravel\Ai\Responses\ImageResponse;
use Laravel\Ai\Responses\TextResponse;
use Laravel\Ai\Responses\TranscriptionResponse;

class FalGateway implements Gateway
{
    public function generateImage(ImageProvider $provider, string $model, string $prompt, array $attachments = [], ?string $size = null, ?string $quality = null, ?int $timeout = null): ImageResponse
    {
        throw $this->unsupported('image');
    }

    public function generateAudio(AudioProvider $provider, string $model, string $text, string $voice, ?string $instructions = null, int $timeout = 30): AudioResponse
    {
        throw $this->unsupported('audio');
    }

    public function generateEmbeddings(EmbeddingProvider $provider, string $model, array $inputs, int $dimensions, int $timeout = 30, array $providerOptions = []): EmbeddingsResponse
    {
        throw $this->unsupported('embeddings');
    }

    public function generateText(TextProvider $provider, string $model, ?string $instructions, array $messages = [], array $tools = [], ?array $schema = null, ?TextGenerationOptions $options = null, ?int $timeout = null): TextResponse
    {
        throw $this->unsupported('text');
    }

    public function streamText(string $invocationId, TextProvider $provider, string $model, ?string $instructions, array $messages = [], array $tools = [], ?array $schema = null, ?TextGenerationOptions $options = null, ?int $timeout = null): Generator
    {
        throw $this->unsupported('text streaming');
    }

    public function onToolInvocation(Closure $invoking, Closure $invoked): self
    {
        return $this;
    }

    public function generateTranscription(TranscriptionProvider $provider, string $model, TranscribableAudio $audio, ?string $language = null, bool $diarize = false, int $timeout = 30, array $providerOptions = []): TranscriptionResponse
    {
        throw $this->unsupported('transcription');
    }

    protected function unsupported(string $capability): BadMethodCallException
    {
        return new BadMethodCallException(
            "The fal driver has no [{$capability}] gateway wired up. Inject one with FalProvider::use{$capability}Gateway()."
        );
    }
}
