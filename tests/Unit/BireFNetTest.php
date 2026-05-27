<?php

use IanRodrigues\FalAi\Image\BireFNet;

beforeEach(function () {
    $this->handler = new BireFNet;
});

it('supports short and full birefnet model names across versions', function () {
    expect($this->handler->supports('birefnet'))->toBeTrue();
    expect($this->handler->supports('fal-ai/birefnet'))->toBeTrue();
    expect($this->handler->supports('birefnet/v1'))->toBeTrue();
    expect($this->handler->supports('fal-ai/birefnet/v1'))->toBeTrue();
    expect($this->handler->supports('birefnet/v2'))->toBeTrue();
    expect($this->handler->supports('fal-ai/birefnet/v2'))->toBeTrue();
    expect($this->handler->supports('nano-banana-2/edit'))->toBeFalse();
});

it('routes short and v2 aliases to the v2 endpoint', function () {
    expect($this->handler->endpoint('birefnet'))->toBe('fal-ai/birefnet/v2');
    expect($this->handler->endpoint('fal-ai/birefnet'))->toBe('fal-ai/birefnet/v2');
    expect($this->handler->endpoint('birefnet/v2'))->toBe('fal-ai/birefnet/v2');
    expect($this->handler->endpoint('fal-ai/birefnet/v2'))->toBe('fal-ai/birefnet/v2');
});

it('routes v1 aliases to the legacy birefnet endpoint', function () {
    expect($this->handler->endpoint('birefnet/v1'))->toBe('fal-ai/birefnet');
    expect($this->handler->endpoint('fal-ai/birefnet/v1'))->toBe('fal-ai/birefnet');
});

it('builds a payload with the first attached image url', function () {
    $payload = $this->handler->buildPayload(
        prompt: 'ignored',
        imageUrls: ['https://example.com/in.png', 'https://example.com/extra.png'],
        size: null,
        quality: null,
        requestOptions: [],
        providerConfig: [],
    );

    expect($payload)->toBe(['image_url' => 'https://example.com/in.png']);
});

it('forwards passthrough fal options into the payload', function () {
    $payload = $this->handler->buildPayload(
        prompt: 'p',
        imageUrls: ['https://example.com/in.png'],
        size: null,
        quality: null,
        requestOptions: [
            'model' => 'General Use (Light)',
            'output_format' => 'webp',
            'sync_mode' => true,
            'unsupported' => 'ignored',
        ],
        providerConfig: [],
    );

    expect($payload['model'])->toBe('General Use (Light)');
    expect($payload['output_format'])->toBe('webp');
    expect($payload['sync_mode'])->toBeTrue();
    expect($payload)->not->toHaveKey('unsupported');
});

it('parses a fal response into an ImageResponse with a single base64 image', function () {
    $response = $this->handler->parseResponse([
        'image' => [
            '_b64' => base64_encode('cutout-bytes'),
            'content_type' => 'image/png',
        ],
    ], 'birefnet');

    expect($response->images)->toHaveCount(1);
    expect($response->images->first()->image)->toBe(base64_encode('cutout-bytes'));
    expect($response->images->first()->mime)->toBe('image/png');
    expect($response->meta->provider)->toBe('fal');
    expect($response->meta->model)->toBe('birefnet');
});

it('returns an empty image collection when fal omits the image key', function () {
    $response = $this->handler->parseResponse([], 'birefnet/v2');

    expect($response->images)->toHaveCount(0);
    expect($response->meta->model)->toBe('birefnet/v2');
});

it('declares attachments are required', function () {
    expect($this->handler->requiresAttachments())->toBeTrue();
});
