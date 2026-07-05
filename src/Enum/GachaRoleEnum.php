<?php

namespace App\Enum;


// Temp : Le temps de setup toute la DB
use Random\RandomException;

enum GachaRoleEnum: string
{
    case PROTECTEUR_ADAMANT = "Protecteur d'Adamant";
    case GARDIEN_OBSIDIENNE = "Gardien d'Obsidienne";
    case COMETE_AUBE = "Comète de l'Aube";
    case ECLIPSE_CREPUSCULE = "Éclipse du Crépuscule";
    case ARCHIMAGE_CELESTE = "Archimage Céleste";
    case LAME_STELLAIRE = "Lame Stellaire";

    /**
     * @throws RandomException
     */
    public static function random(): self
    {
        $cases = self::cases();

        return $cases[random_int(0, count($cases) - 1)];
    }
}
