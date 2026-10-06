<?php

declare(strict_types=1);

namespace App\Backoffice;

use App\Entity\CatalogTemplateRank;
use App\Entity\Rank;
use Symfony\Component\String\UnicodeString;

final class CatalogDisplayOrder
{
    /**
     * @template T of Rank|CatalogTemplateRank
     *
     * @param list<T> $ranks
     *
     * @return list<T>
     */
    public static function ranks(array $ranks): array
    {
        usort($ranks, static fn (Rank|CatalogTemplateRank $left, Rank|CatalogTemplateRank $right): int => strnatcmp(
            (new UnicodeString($left->name()))->lower()->toString(),
            (new UnicodeString($right->name()))->lower()->toString(),
        ));

        return $ranks;
    }
}
