<?php

namespace App\Enum;

use Random\RandomException;

enum GachaElementEnum: string
{
    case SEVE = "Sève";
    case BRUME = "Brume";
    case ECHO = "Écho";
    case VIDE = "Vide";
    case SEL = "Sel";
    case FLUX = "Flux";
    case AMBRE = "Ambre";
    case POUSSIERE = "Poussière";
    /**
     * @throws RandomException
     */
    public static function random(): self
    {
        $cases = self::cases();

        return $cases[random_int(0, count($cases) - 1)];
    }
}
