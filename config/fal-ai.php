<?php

use IanRodrigues\FalAi\Image\BireFNet;
use IanRodrigues\FalAi\Image\NanoBananaTwoEdit;

return [

    /*
    |--------------------------------------------------------------------------
    | API Credentials
    |--------------------------------------------------------------------------
    |
    | Your fal.ai API key. fal uses the literal "Key" auth scheme, e.g.
    | "Authorization: Key {key}". Grab one from https://fal.ai/dashboard/keys.
    |
    */

    'key' => env('FAL_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Endpoints
    |--------------------------------------------------------------------------
    |
    | Default fal endpoints. The queue URL is used for asynchronous inference
    | (the default), the sync URL for short-running models that can block, and
    | the storage URL for uploading local file attachments before inference.
    |
    */

    'base_url' => env('FAL_QUEUE_URL', 'https://queue.fal.run'),
    'sync_base_url' => env('FAL_SYNC_URL', 'https://fal.run'),
    'storage_upload_url' => env('FAL_STORAGE_URL', 'https://rest.alpha.fal.ai/storage/upload/initiate'),

    /*
    |--------------------------------------------------------------------------
    | HTTP Client Timeouts
    |--------------------------------------------------------------------------
    |
    | Per-request timeouts (seconds) applied to every fal HTTP call: submit,
    | status, and result fetch. These are independent of the queue.timeout
    | below, which caps the *total* polling duration of a single job.
    |
    */

    'request_timeout' => 30,
    'connect_timeout' => 10,
    'image_download_timeout' => 30,

    /*
    |--------------------------------------------------------------------------
    | Queue Polling
    |--------------------------------------------------------------------------
    |
    | fal's queue API is asynchronous: submit returns a request id, then we
    | poll a status URL until the job completes. These knobs control the
    | exponential backoff between polls and the overall timeout (seconds).
    |
    */

    'queue' => [
        'initial_delay_ms' => 1000,
        'max_delay_ms' => 5000,
        'timeout' => 120,
    ],

    /*
    |--------------------------------------------------------------------------
    | Image Result Handling
    |--------------------------------------------------------------------------
    |
    | fal returns hosted URLs for generated images by default. Laravel AI's
    | GeneratedImage value object carries base64 data, so by default we fetch
    | each result URL and inline it (matching the bundled providers). Set
    | false to skip the fetch when you only need URLs and will hand off to a
    | storage layer (S3, Cloudflare, etc.) yourself.
    |
    | Applies only to image generation. Future capabilities (audio, video,
    | transcription) will introduce their own result-handling configuration.
    |
    */

    'fetch_images' => true,

    /*
    |--------------------------------------------------------------------------
    | Image Model Handlers
    |--------------------------------------------------------------------------
    |
    | Each handler maps a fal image model (e.g. "nano-banana-2/edit") to its
    | endpoint and request/response shape. Register additional handlers here
    | to support more fal image models without touching the gateway.
    |
    | When this package gains audio, video, or transcription support, parallel
    | configuration keys ('audio.models', 'video.models', etc.) will live
    | alongside this one.
    |
    */

    'image' => [
        'models' => [
            NanoBananaTwoEdit::class,
            BireFNet::class,
        ],
    ],

];
