<?php

declare(strict_types=1);

namespace App\Backoffice\Csv;

use App\Entity\CatalogTemplate;
use App\Entity\DiscordServer;
use Doctrine\ORM\EntityManagerInterface;

final readonly class CatalogCsvImportService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ServerCatalogCsvTargetAdapter $serverAdapter,
        private TemplateCatalogCsvTargetAdapter $templateAdapter,
    ) {
    }

    public function preview(
        DiscordServer|CatalogTemplate $target,
        CatalogCsvSection $section,
        CatalogCsvDocument $document,
    ): CatalogCsvPreview {
        return $this->adapter($target)->preview($target, $section, $document);
    }

    /**
     * @param array<int, string> $discordRoleIdsByLine
     */
    public function apply(
        DiscordServer|CatalogTemplate $target,
        CatalogCsvSection $section,
        CatalogCsvDocument $document,
        string $expectedFingerprint,
        array $discordRoleIdsByLine = [],
    ): CatalogCsvImportResult {
        $adapter = $this->adapter($target);
        $preview = $adapter->preview($target, $section, $document);
        if (!$preview->valid()) {
            throw new \InvalidArgumentException('invalid_csv_import');
        }
        if (!hash_equals($expectedFingerprint, $preview->fingerprint())) {
            throw new \InvalidArgumentException('catalog_changed');
        }
        $adapter->validateApply($target, $section, $preview, $discordRoleIdsByLine);

        return $this->entityManager->wrapInTransaction(
            fn (): CatalogCsvImportResult => $adapter->apply($target, $section, $preview, $discordRoleIdsByLine),
        );
    }

    private function adapter(DiscordServer|CatalogTemplate $target): CatalogCsvTargetAdapterInterface
    {
        if ($this->serverAdapter->supports($target)) {
            return $this->serverAdapter;
        }

        return $this->templateAdapter;
    }
}
