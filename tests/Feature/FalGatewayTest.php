<?php

use IanRodrigues\FalAi\Exceptions\FalRequestException;
use IanRodrigues\FalAi\Exceptions\MissingAttachmentsException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Files\Image;

it('submits, polls, fetches, and returns an ImageResponse with base64 images', function () {
    Http::fake([
        'queue.fal.run/fal-ai/nano-banana-2/edit' => Http::response([
            'request_id' => 'req-123',
            'status_url' => 'https://queue.fal.run/fal-ai/nano-banana-2/edit/requests/req-123/status',
            'response_url' => 'https://queue.fal.run/fal-ai/nano-banana-2/edit/requests/req-123',
        ]),
        'queue.fal.run/fal-ai/nano-banana-2/edit/requests/req-123/status' => Http::response(['status' => 'COMPLETED']),
        'queue.fal.run/fal-ai/nano-banana-2/edit/requests/req-123' => Http::response([
            'images' => [
                ['url' => 'https://fal.media/files/out.png', 'content_type' => 'image/png'],
            ],
        ]),
        'fal.media/files/out.png' => Http::response('PNG-BINARY-BYTES'),
    ]);

    $response = $this->falGateway()->generateImage(
        provider: $this->falProvider(),
        model: 'nano-banana-2/edit',
        prompt: 'turn the sky into a sunset',
        attachments: [Image::fromUrl('https://example.com/in.jpg')],
    );

    expect($response->images)->toHaveCount(1)
        ->and($response->images->first()->image)->toBe(base64_encode('PNG-BINARY-BYTES'))
        ->and($response->meta->provider)->toBe('fal')
        ->and($response->meta->model)->toBe('nano-banana-2/edit');

    Http::assertSent(function (Request $request) {
        return $request->method() === 'POST'
            && str_ends_with($request->url(), '/fal-ai/nano-banana-2/edit')
            && $request->header('Authorization') === ['Key test-fal-key']
            && $request['prompt'] === 'turn the sky into a sunset'
            && $request['image_urls'] === ['https://example.com/in.jpg'];
    });
});

it('polls until the job leaves IN_PROGRESS before fetching the result', function () {
    Http::fake([
        'queue.fal.run/fal-ai/nano-banana-2/edit' => Http::response([
            'request_id' => 'req-x',
            'status_url' => 'https://queue.fal.run/fal-ai/nano-banana-2/edit/requests/req-x/status',
            'response_url' => 'https://queue.fal.run/fal-ai/nano-banana-2/edit/requests/req-x',
        ]),
        'queue.fal.run/fal-ai/nano-banana-2/edit/requests/req-x/status' => Http::sequence([
            Http::response(['status' => 'IN_QUEUE']),
            Http::response(['status' => 'IN_PROGRESS']),
            Http::response(['status' => 'COMPLETED']),
        ]),
        'queue.fal.run/fal-ai/nano-banana-2/edit/requests/req-x' => Http::response([
            'images' => [['url' => 'https://fal.media/files/done.png']],
        ]),
        'fal.media/*' => Http::response('done'),
    ]);

    $response = $this->falGateway()->generateImage(
        provider: $this->falProvider(),
        model: 'nano-banana-2/edit',
        prompt: 'p',
        attachments: [Image::fromUrl('https://example.com/in.jpg')],
    );

    expect($response->images)->toHaveCount(1);

    Http::assertSentCount(3, fn (Request $r) => str_contains($r->url(), '/requests/req-x/status'));
});

it('throws when fal reports a FAILED status', function () {
    Http::fake([
        'queue.fal.run/fal-ai/nano-banana-2/edit' => Http::response([
            'request_id' => 'req-f',
            'status_url' => 'https://queue.fal.run/fal-ai/nano-banana-2/edit/requests/req-f/status',
            'response_url' => 'https://queue.fal.run/fal-ai/nano-banana-2/edit/requests/req-f',
        ]),
        'queue.fal.run/fal-ai/nano-banana-2/edit/requests/req-f/status' => Http::response([
            'status' => 'FAILED',
            'detail' => 'safety filter',
        ]),
    ]);

    $this->falGateway()->generateImage(
        provider: $this->falProvider(),
        model: 'nano-banana-2/edit',
        prompt: 'p',
        attachments: [Image::fromUrl('https://example.com/in.jpg')],
    );
})->throws(FalRequestException::class);

it('throws when a queue submit returns a 4xx', function () {
    Http::fake([
        'queue.fal.run/fal-ai/nano-banana-2/edit' => Http::response(['error' => 'bad'], 422),
    ]);

    $this->falGateway()->generateImage(
        provider: $this->falProvider(),
        model: 'nano-banana-2/edit',
        prompt: 'p',
        attachments: [Image::fromUrl('https://example.com/in.jpg')],
    );
})->throws(FalRequestException::class);

it('throws when nano-banana-2/edit is called without attachments', function () {
    $this->falGateway()->generateImage(
        provider: $this->falProvider(),
        model: 'nano-banana-2/edit',
        prompt: 'p',
    );
})->throws(MissingAttachmentsException::class);

it('rejects providers that are not a FalProvider', function () {
    $bogus = new class implements \Laravel\Ai\Contracts\Providers\ImageProvider
    {
        public function image(string $prompt, array $attachments = [], ?string $size = null, ?string $quality = null, ?string $model = null, ?int $timeout = null): \Laravel\Ai\Responses\ImageResponse
        {
            throw new \LogicException('not used');
        }

        public function imageGateway(): \Laravel\Ai\Contracts\Gateway\ImageGateway
        {
            throw new \LogicException('not used');
        }

        public function useImageGateway(\Laravel\Ai\Contracts\Gateway\ImageGateway $gateway): self
        {
            return $this;
        }

        public function defaultImageModel(): string
        {
            return 'x';
        }

        public function defaultImageOptions(?string $size = null, ?string $quality = null): array
        {
            return [];
        }
    };

    $this->falGateway()->generateImage(
        provider: $bogus,
        model: 'nano-banana-2/edit',
        prompt: 'p',
        attachments: [Image::fromUrl('https://example.com/in.jpg')],
    );
})->throws(InvalidArgumentException::class);

it('forwards provider request options into the fal payload', function () {
    Http::fake([
        'queue.fal.run/fal-ai/nano-banana-2/edit' => Http::response([
            'request_id' => 'r',
            'status_url' => 'https://queue.fal.run/status',
            'response_url' => 'https://queue.fal.run/result',
        ]),
        'queue.fal.run/status' => Http::response(['status' => 'COMPLETED']),
        'queue.fal.run/result' => Http::response(['images' => []]),
    ]);

    $provider = $this->falProvider();
    $provider->withRequestOptions(['seed' => 7, 'num_images' => 2]);

    $this->falGateway()->generateImage(
        provider: $provider,
        model: 'nano-banana-2/edit',
        prompt: 'p',
        attachments: [Image::fromUrl('https://example.com/in.jpg')],
    );

    Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/fal-ai/nano-banana-2/edit')
        && $r->method() === 'POST'
        && $r['seed'] === 7
        && $r['num_images'] === 2);
});

it('respects fetch_images=false by skipping image downloads', function () {
    config()->set('fal-ai.fetch_images', false);

    Http::fake([
        'queue.fal.run/fal-ai/nano-banana-2/edit' => Http::response([
            'request_id' => 'r',
            'status_url' => 'https://queue.fal.run/status',
            'response_url' => 'https://queue.fal.run/result',
        ]),
        'queue.fal.run/status' => Http::response(['status' => 'COMPLETED']),
        'queue.fal.run/result' => Http::response([
            'images' => [['url' => 'https://fal.media/files/out.png']],
        ]),
    ]);

    $response = $this->falGateway()->generateImage(
        provider: $this->falProvider(),
        model: 'nano-banana-2/edit',
        prompt: 'p',
        attachments: [Image::fromUrl('https://example.com/in.jpg')],
    );

    expect($response->images->first()->image)->toBe('https://fal.media/files/out.png');

    Http::assertNotSent(fn (Request $r) => $r->url() === 'https://fal.media/files/out.png');
});
