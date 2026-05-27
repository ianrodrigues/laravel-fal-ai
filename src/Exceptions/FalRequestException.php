<?php

namespace IanRodrigues\FalAi\Exceptions;

use Illuminate\Http\Client\Response;

class FalRequestException extends FalException
{
    protected const MAX_BODY_LENGTH = 1000;

    public static function from(Response $response, string $context = 'fal request'): self
    {
        $body = mb_substr($response->body(), 0, self::MAX_BODY_LENGTH);

        return new self("[{$context}] fal returned HTTP {$response->status()}: {$body}");
    }
}
