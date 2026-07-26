<?php

declare(strict_types=1);

namespace App\Backoffice\Csv;

use App\Entity\CatalogTemplate;
use App\Entity\DiscordServer;

interface CatalogCsvTargetAdapterInterface
{
    public function supports(DiscordServer|CatalogTemplate $target): bool;

    public function preview(
        DiscordServer|CatalogTemplate $target,
        CatalogCsvSection $section,
        CatalogCsvDocument $document,
    ): CatalogCsvPreview;

    /**
     * @param array<int, string> $discordRoleIdsByLine
     */
    public function validateApply(
        DiscordServer|CatalogTemplate $target,
        CatalogCsvSection $section,
        CatalogCsvPreview $preview,
        array $discordRoleIdsByLine,
    ): void;

    /**
     * @param array<int, string> $discordRoleIdsByLine
     */
    public function apply(
        DiscordServer|CatalogTemplate $target,
        CatalogCsvSection $section,
        CatalogCsvPreview $preview,
        array $discordRoleIdsByLine,
    ): CatalogCsvImportResult;
}
