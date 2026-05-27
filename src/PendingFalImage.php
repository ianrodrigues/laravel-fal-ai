<?php

namespace IanRodrigues\FalAi;

use Laravel\Ai\AiManager;
use Laravel\Ai\Image;
use Laravel\Ai\PendingResponses\PendingImageGeneration;
use Laravel\Ai\Responses\ImageResponse;

class PendingFalImage
{
    protected PendingImageGeneration $pending;

    /** @var array<string, mixed> */
    protected array $options = [];

    public function __construct(string $prompt)
    {
        $this->pending = Image::of($prompt);
    }

    /** @param  array<int, \Laravel\Ai\Files\Image>  $attachments */
    public function attachments(array $attachments): self
    {
        $this->pending->attachments($attachments);

        return $this;
    }

    public function size(string $size): self
    {
        $this->pending->size($size);

        return $this;
    }

    public function square(): self
    {
        $this->pending->square();

        return $this;
    }

    public function portrait(): self
    {
        $this->pending->portrait();

        return $this;
    }

    public function landscape(): self
    {
        $this->pending->landscape();

        return $this;
    }

    public function quality(string $quality): self
    {
        $this->pending->quality($quality);

        return $this;
    }

    public function timeout(int $seconds): self
    {
        $this->pending->timeout($seconds);

        return $this;
    }

    public function numImages(int $count): self
    {
        return $this->option('num_images', $count);
    }

    public function seed(int $seed): self
    {
        return $this->option('seed', $seed);
    }

    public function aspectRatio(string $ratio): self
    {
        return $this->option('aspect_ratio', $ratio);
    }

    public function resolution(string $resolution): self
    {
        return $this->option('resolution', $resolution);
    }

    public function safetyTolerance(int $tolerance): self
    {
        return $this->option('safety_tolerance', $tolerance);
    }

    public function outputFormat(string $format): self
    {
        return $this->option('output_format', $format);
    }

    public function systemPrompt(string $prompt): self
    {
        return $this->option('system_prompt', $prompt);
    }

    public function thinkingLevel(string $level): self
    {
        return $this->option('thinking_level', $level);
    }

    public function enableWebSearch(bool $enabled = true): self
    {
        return $this->option('enable_web_search', $enabled);
    }

    public function limitGenerations(int $limit): self
    {
        return $this->option('limit_generations', $limit);
    }

    public function option(string $key, mixed $value): self
    {
        $this->options[$key] = $value;

        return $this;
    }

    /** @param  array<string, mixed>  $options */
    public function options(array $options): self
    {
        $this->options = array_replace($this->options, $options);

        return $this;
    }

    public function generate(?string $model = null): ImageResponse
    {
        $provider = app(AiManager::class)->driver('fal');

        if (! $provider instanceof FalProvider || empty($this->options)) {
            return $this->pending->generate(provider: 'fal', model: $model);
        }

        $provider->withRequestOptions($this->options);

        try {
            return $this->pending->generate(provider: 'fal', model: $model);
        } finally {
            $provider->pullRequestOptions();
        }
    }
}
