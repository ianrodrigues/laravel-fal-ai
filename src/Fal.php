<?php

namespace IanRodrigues\FalAi;

class Fal
{
    public static function image(string $prompt): PendingFalImage
    {
        return new PendingFalImage($prompt);
    }
}
