<?php

namespace IanRodrigues\FalAi;

use Laravel\Ai\Contracts\Providers\ImageProvider;
use Laravel\Ai\Providers\Concerns\GeneratesImages;
use Laravel\Ai\Providers\Concerns\HasImageGateway;
use Laravel\Ai\Providers\Provider;

class FalProvider extends Provider implements ImageProvider
{
    use GeneratesImages;
    use HasImageGateway;

    /** @var array<string, mixed> */
    protected array $requestOptions = [];

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
