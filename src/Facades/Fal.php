<?php

namespace IanRodrigues\FalAi\Facades;

use IanRodrigues\FalAi\Fal as FalManager;
use IanRodrigues\FalAi\PendingFalImage;
use Illuminate\Support\Facades\Facade;

/**
 * @method static PendingFalImage image(string $prompt)
 *
 * @see FalManager
 */
class Fal extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return FalManager::class;
    }
}
