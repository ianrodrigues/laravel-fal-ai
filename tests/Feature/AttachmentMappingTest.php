<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Files\Image;

it('passes through RemoteImage URLs verbatim (no upload)', function () {
    $this->fakeFalQueueRoundtrip();

    $this->falGateway()->generateImage(
        provider: $this->falProvider(),
        model: 'nano-banana-2/edit',
        prompt: 'p',
        attachments: [Image::fromUrl('https://example.com/already-public.jpg')],
    );

    Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/fal-ai/nano-banana-2/edit')
        && $r['image_urls'] === ['https://example.com/already-public.jpg']);

    Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'storage/upload'));
});

it('uploads LocalImage bytes from disk to fal storage', function () {
    $tmp = tempnam(sys_get_temp_dir(), 'fal-').'.png';
    file_put_contents($tmp, 'png-bytes');

    $this->fakeFalQueueRoundtrip();

    try {
        $this->falGateway()->generateImage(
            provider: $this->falProvider(),
            model: 'nano-banana-2/edit',
            prompt: 'p',
            attachments: [Image::fromPath($tmp)],
        );
    } finally {
        @unlink($tmp);
    }

    Http::assertSent(fn (Request $r) => str_contains($r->url(), 'rest.alpha.fal.ai/storage/upload'));

    Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/fal-ai/nano-banana-2/edit')
        && $r['image_urls'] === ['https://fal.media/uploads/fake-uploaded.png']);
});

it('uploads StoredImage bytes to fal storage and uses the returned URL', function () {
    Storage::fake('local');
    Storage::disk('local')->put('photos/in.png', 'png-bytes');

    $this->fakeFalQueueRoundtrip();

    $this->falGateway()->generateImage(
        provider: $this->falProvider(),
        model: 'nano-banana-2/edit',
        prompt: 'p',
        attachments: [Image::fromStorage('photos/in.png', 'local')],
    );

    Http::assertSent(fn (Request $r) => str_contains($r->url(), 'rest.alpha.fal.ai/storage/upload'));

    Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/fal-ai/nano-banana-2/edit')
        && $r['image_urls'] === ['https://fal.media/uploads/fake-uploaded.png']);
});

it('uploads Base64Image bytes after stripping the data URI prefix', function () {
    $this->fakeFalQueueRoundtrip();

    $payload = 'data:image/png;base64,'.base64_encode('png-bytes');

    $this->falGateway()->generateImage(
        provider: $this->falProvider(),
        model: 'nano-banana-2/edit',
        prompt: 'p',
        attachments: [Image::fromBase64($payload, 'image/png')],
    );

    Http::assertSent(fn (Request $r) => str_contains($r->url(), 'rest.alpha.fal.ai/storage/upload'));
});
