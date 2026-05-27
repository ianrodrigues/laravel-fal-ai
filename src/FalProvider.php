<?php

namespace IanRodrigues\FalAi;

use Illuminate\Contracts\Events\Dispatcher;
use Laravel\Ai\Contracts\Gateway\ImageGateway;
use Laravel\Ai\Contracts\Providers\ImageProvider;
use Laravel\Ai\Providers\Concerns\GeneratesImages;
use Laravel\Ai\Providers\Provider;
use LogicException;

class FalProvider extends Provider implements ImageProvider
{
    use GeneratesImages;

    protected ?ImageGateway $imageGateway = null;

    /** @var array<string, mixed> */
    protected array $requestOptions = [];

    public function __construct(array $config, Dispatcher $events)
    {
        $this->config = $config;
        $this->events = $events;
    }

    public function imageGateway(): ImageGateway
    {
        return $this->imageGateway ?? throw new LogicException(
            'No image gateway is wired up on the fal driver. Call FalProvider::useImageGateway() before requesting images.',
        );
    }

    public function useImageGateway(ImageGateway $gateway): self
    {
        $this->imageGateway = $gateway;

        return $this;
    }

    public function defaultImageModel(): string
    {
        return $this->config['models']['image']['default'] ?? 'nano-banana-2/edit';
    }

    public function defaultImageOptions(?string $size = null, ?string $quality = null): array
    {
        return array_filter([
            'size' => $size,
            'quality' => $quality,
        ]);
    }

    /** @param  array<string, mixed>  $options */
    public function withRequestOptions(array $options): self
    {
        $this->requestOptions = array_replace($this->requestOptions, $options);

        return $this;
    }

    /** @return array<string, mixed> */
    public function pullRequestOptions(): array
    {
        $options = $this->requestOptions;
        $this->requestOptions = [];

        return $options;
    }
}
