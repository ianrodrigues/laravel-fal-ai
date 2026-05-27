# Laravel fal.ai

## Introduction

`laravel-fal-ai` is a third-party [fal.ai](https://fal.ai) driver for the official [Laravel AI SDK](https://github.com/laravel/ai). It plugs a `fal` provider into the SDK's manager so you may generate content with fal models through the same fluent API you use for OpenAI, Gemini, and the other bundled providers.

The package ships image generation today, starting with `nano-banana-2/edit`. Its architecture is capability-scoped, so support for audio, video, and transcription can be added under parallel namespaces as fal expands.

## Installation

You may install the package via Composer:

```bash
composer require ianrodrigues/laravel-fal-ai
```

The service provider is registered automatically through Laravel's package discovery. No manual registration is required.

### Requirements

- PHP 8.3 or higher
- Laravel 12
- `laravel/ai` ^0.7

> Laravel 13 will be supported once `pestphp/pest-plugin-laravel` adds a release that targets `laravel/framework ^13`.

## Configuration

Set your fal API key in your application's `.env` file. You may grab a key from the [fal dashboard](https://fal.ai/dashboard/keys):

```env
FAL_KEY=your-fal-api-key
```

Next, register the driver in your `config/ai.php` providers array:

```php
'providers' => [

    // ...

    'fal' => [
        'driver' => 'fal',
        'key' => env('FAL_KEY'),
    ],

],
```

If you would like to customize the package's defaults — timeouts, polling cadence, model handler registration — you may publish the configuration file:

```bash
php artisan vendor:publish --tag=fal-ai-config
```

This will place a `fal-ai.php` file in your `config` directory. The available options are documented in the [Configuration Reference](#configuration-reference) section.

## Image Generation

The driver supports two usage surfaces. The first matches the upstream Laravel AI builder and is recommended when the SDK's standard options are all you need. The second is a fluent builder that exposes fal-specific knobs the upstream SDK does not yet surface for image generation.

### Standard Laravel AI Builder

```php
use Laravel\Ai\Files\Image as ImageFile;
use Laravel\Ai\Image;

$response = Image::of('change the sky to a sunset')
    ->attachments([ImageFile::fromUrl('https://example.com/photo.jpg')])
    ->square()
    ->quality('high')
    ->generate(provider: 'fal', model: 'nano-banana-2/edit');

file_put_contents('out.png', base64_decode($response->images->first()->image));
```

The SDK's `size` and `quality` arguments are translated to fal's vocabulary:

| SDK value                | fal field      | fal value |
| ------------------------ | -------------- | --------- |
| `->square()` / `1:1`     | `aspect_ratio` | `1:1`     |
| `->portrait()` / `2:3`   | `aspect_ratio` | `2:3`     |
| `->landscape()` / `3:2`  | `aspect_ratio` | `3:2`     |
| `quality('low')`         | `resolution`   | `0.5K`    |
| `quality('medium')`      | `resolution`   | `1K`      |
| `quality('high')`        | `resolution`   | `2K`      |

### Fluent fal Builder

When you need a fal-specific option (`seed`, `num_images`, `safety_tolerance`, and so on), use the `Fal` facade. It wraps the standard `Image::of(...)` builder and accepts every method the SDK exposes, plus methods for each fal parameter:

```php
use IanRodrigues\FalAi\Facades\Fal;
use Laravel\Ai\Files\Image as ImageFile;

$response = Fal::image('change the sky to a sunset')
    ->attachments([ImageFile::fromUrl('https://example.com/photo.jpg')])
    ->seed(42)
    ->numImages(2)
    ->aspectRatio('16:9')
    ->safetyTolerance(4)
    ->outputFormat('webp')
    ->generate(model: 'nano-banana-2/edit');
```

The following fal-only methods are available:

| Method                         | fal parameter        |
| ------------------------------ | -------------------- |
| `numImages(int)`               | `num_images`         |
| `seed(int)`                    | `seed`               |
| `aspectRatio(string)`          | `aspect_ratio`       |
| `resolution(string)`           | `resolution`         |
| `safetyTolerance(int)`         | `safety_tolerance`   |
| `outputFormat(string)`         | `output_format`      |
| `systemPrompt(string)`         | `system_prompt`      |
| `thinkingLevel(string)`        | `thinking_level`     |
| `enableWebSearch(bool)`        | `enable_web_search`  |
| `limitGenerations(int)`        | `limit_generations`  |

For any option not covered by a dedicated method, you may use `option(string, mixed)` or pass an array via `options(array)`.

### Attachments

The driver accepts every attachment type the Laravel AI SDK exposes:

| Source                                  | Behavior                                                   |
| --------------------------------------- | ---------------------------------------------------------- |
| `ImageFile::fromUrl($url)`              | URL is forwarded to fal verbatim.                          |
| `ImageFile::fromPath($path)`            | Bytes are read from disk and uploaded to fal storage.      |
| `ImageFile::fromBase64($data, $mime)`   | Decoded and uploaded to fal storage.                       |
| `ImageFile::fromStorage($path, $disk)`  | Pulled from the disk and uploaded to fal storage.          |
| `ImageFile::fromUpload($uploadedFile)`  | Read from the request and uploaded to fal storage.         |

When an upload is required, the package uses fal's storage upload endpoint and substitutes the returned URL into the request.

## Extending With New Models

To support a fal model the package does not ship a handler for, implement the `IanRodrigues\FalAi\Image\ModelHandler` interface:

```php
namespace App\AiModels;

use IanRodrigues\FalAi\Image\ModelHandler;
use Illuminate\Support\Collection;
use Laravel\Ai\Responses\Data\GeneratedImage;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Usage;
use Laravel\Ai\Responses\ImageResponse;

class FluxPro implements ModelHandler
{
    public function supports(string $model): bool
    {
        return $model === 'flux-pro' || $model === 'fal-ai/flux-pro';
    }

    public function endpoint(string $model): string
    {
        return 'fal-ai/flux-pro';
    }

    public function buildPayload(string $prompt, array $imageUrls, ?string $size, ?string $quality, array $requestOptions, array $providerConfig): array
    {
        return array_filter([
            'prompt' => $prompt,
            'image_size' => $size,
            ...$requestOptions,
        ]);
    }

    public function parseResponse(array $json, string $model): ImageResponse
    {
        $images = Collection::make($json['images'] ?? [])
            ->map(fn (array $image) => new GeneratedImage(
                $image['_b64'] ?? $image['url'] ?? '',
                $image['content_type'] ?? 'image/png',
            ));

        return new ImageResponse($images, new Usage, new Meta('fal', $model));
    }

    public function requiresAttachments(): bool
    {
        return false;
    }
}
```

Register the handler in `config/fal-ai.php`:

```php
'image' => [
    'models' => [
        \IanRodrigues\FalAi\Image\NanoBananaTwoEdit::class,
        \App\AiModels\FluxPro::class,
    ],
],
```

Handlers are resolved in reverse registration order, so application-level handlers take precedence over the defaults the package ships.

When `fal-ai.fetch_images` is enabled, the gateway downloads each result URL before invoking `parseResponse`. The downloaded bytes are attached to each image entry under a `_b64` key alongside the original `url` and `content_type` fields. Your handler should prefer `_b64` when present, as shown above.

## Events

The driver dispatches the SDK's own image events, so `Event::fake` and listener registration behave exactly as they do for the bundled providers:

```php
use Laravel\Ai\Events\GeneratingImage;
use Laravel\Ai\Events\ImageGenerated;

Event::fake([GeneratingImage::class, ImageGenerated::class]);

// ... perform a generation ...

Event::assertDispatched(ImageGenerated::class);
```

## Exceptions

All exceptions raised by the driver extend `IanRodrigues\FalAi\Exceptions\FalException`:

| Exception                       | Raised when                                                       |
| ------------------------------- | ----------------------------------------------------------------- |
| `FalRequestException`           | fal returned a non-2xx response, or a terminal failure status.    |
| `FalQueueTimeoutException`      | A queued job did not complete before `queue.timeout` elapsed.     |
| `UnknownModelException`         | No handler is registered for the requested model.                 |
| `MissingAttachmentsException`   | A model that requires attachments was invoked without any.        |

You may catch `FalException` to handle all driver-originated failures uniformly.

## Configuration Reference

The full list of options available in `config/fal-ai.php`:

| Key                       | Type   | Default                                       | Description                                                                                       |
| ------------------------- | ------ | --------------------------------------------- | ------------------------------------------------------------------------------------------------- |
| `key`                     | string | `env('FAL_KEY')`                              | Your fal API key.                                                                                 |
| `base_url`                | string | `https://queue.fal.run`                       | fal's queue endpoint.                                                                             |
| `sync_base_url`           | string | `https://fal.run`                             | fal's synchronous endpoint. Reserved for future use.                                              |
| `storage_upload_url`      | string | `https://rest.alpha.fal.ai/storage/upload`    | Where local file attachments are uploaded.                                                        |
| `request_timeout`         | int    | `30`                                          | Per-request timeout in seconds.                                                                   |
| `connect_timeout`         | int    | `10`                                          | Connection timeout in seconds.                                                                    |
| `image_download_timeout`  | int    | `30`                                          | Timeout for downloading result images when `fetch_images` is enabled.                             |
| `queue.initial_delay_ms`  | int    | `1000`                                        | Delay before the first status poll.                                                               |
| `queue.max_delay_ms`      | int    | `5000`                                        | Upper bound on the exponential polling backoff.                                                   |
| `queue.timeout`           | int    | `120`                                         | Maximum total seconds to wait for a job to complete.                                              |
| `fetch_images`            | bool   | `true`                                        | When `true`, result image URLs are downloaded and inlined as base64. Set `false` to keep URLs.    |
| `image.models`            | array  | `[NanoBananaTwoEdit::class]`                  | Ordered list of registered `ModelHandler` classes.                                                |

## Roadmap

The package's gateway implements fal's queue, polling, and storage-upload primitives in a capability-agnostic way. Audio, video, and transcription support will be added as fal expands its catalog under parallel namespaces (`IanRodrigues\FalAi\Audio`, `IanRodrigues\FalAi\Video`, and so on) following the same `ModelHandler` registry pattern documented above.

## Testing

The package ships with a Pest test suite:

```bash
composer test
```

To run [Pint](https://github.com/laravel/pint) against the codebase:

```bash
composer lint
```

## License

`laravel-fal-ai` is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
