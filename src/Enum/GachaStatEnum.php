<?php

namespace App\Enum;

use Random\RandomException;

enum GachaStatEnum: string
{
    case ETHER = "Éther";
    case ASTRAL = "Astral";
    case IMPACT = "Impact";
    case AURA = "Aura";
    case EGIDE = "Égide";
    case ORACLE = "Oracle";

    /**
     * @throws RandomException
     */
    public static function random(): self
    {
        $cases = self::cases();

        return $cases[random_int(0, count($cases) - 1)];
    }
}
