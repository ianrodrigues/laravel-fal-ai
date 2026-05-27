<?php

use IanRodrigues\FalAi\Image\NanoBananaTwoEdit;

beforeEach(function () {
    $this->handler = new NanoBananaTwoEdit;
});

it('supports both short and full model names', function () {
    expect($this->handler->supports('nano-banana-2/edit'))->toBeTrue();
    expect($this->handler->supports('fal-ai/nano-banana-2/edit'))->toBeTrue();
    expect($this->handler->supports('flux-pro'))->toBeFalse();
});

it('builds a payload with prompt and image urls', function () {
    $payload = $this->handler->buildPayload(
        prompt: 'make the sky a sunset',
        imageUrls: ['https://example.com/in.jpg'],
        size: null,
        quality: null,
        requestOptions: [],
        providerConfig: [],
    );

    expect($payload['prompt'])->toBe('make the sky a sunset');
    expect($payload['image_urls'])->toBe(['https://example.com/in.jpg']);
});

it('maps Laravel AI size strings to fal aspect_ratio', function () {
    $payload = $this->handler->buildPayload(
        prompt: 'p', imageUrls: [], size: '1:1', quality: null,
        requestOptions: [], providerConfig: [],
    );

    expect($payload['aspect_ratio'])->toBe('1:1');
});

it('maps Laravel AI quality to fal resolution', function () {
    expect($this->handler->buildPayload('p', [], null, 'low', [], [])['resolution'])->toBe('0.5K');
    expect($this->handler->buildPayload('p', [], null, 'medium', [], [])['resolution'])->toBe('1K');
    expect($this->handler->buildPayload('p', [], null, 'high', [], [])['resolution'])->toBe('2K');
});

it('lets explicit request options override size/quality mapping', function () {
    $payload = $this->handler->buildPayload(
        prompt: 'p', imageUrls: [], size: '1:1', quality: 'low',
        requestOptions: ['aspect_ratio' => '21:9', 'resolution' => '4K'],
        providerConfig: [],
    );

    expect($payload['aspect_ratio'])->toBe('21:9');
    expect($payload['resolution'])->toBe('4K');
});

it('forwards passthrough fal options into the payload', function () {
    $payload = $this->handler->buildPayload(
        prompt: 'p', imageUrls: [], size: null, quality: null,
        requestOptions: [
            'seed' => 42,
            'num_images' => 3,
            'safety_tolerance' => 4,
            'output_format' => 'webp',
            'unsupported' => 'ignored',
        ],
        providerConfig: [],
    );

    expect($payload['seed'])->toBe(42);
    expect($payload['num_images'])->toBe(3);
    expect($payload['safety_tolerance'])->toBe(4);
    expect($payload['output_format'])->toBe('webp');
    expect($payload)->not->toHaveKey('unsupported');
});

it('parses a fal response into an ImageResponse with base64 images', function () {
    $response = $this->handler->parseResponse([
        'images' => [
            ['_b64' => base64_encode('binary-data'), 'content_type' => 'image/png'],
            ['_b64' => base64_encode('other-binary'), 'content_type' => 'image/jpeg'],
        ],
    ], 'nano-banana-2/edit');

    expect($response->images)->toHaveCount(2);
    expect($response->images->first()->image)->toBe(base64_encode('binary-data'));
    expect($response->images->first()->mime)->toBe('image/png');
    expect($response->meta->provider)->toBe('fal');
    expect($response->meta->model)->toBe('nano-banana-2/edit');
});

it('declares attachments are required', function () {
    expect($this->handler->requiresAttachments())->toBeTrue();
});
