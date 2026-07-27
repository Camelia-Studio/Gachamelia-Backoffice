<?php

declare(strict_types=1);

namespace App\Backoffice\Csv;

final readonly class CatalogCsvImportResult
{
    /**
     * @param array{creates: int, updates: int, unchanged: int} $counts
     */
    public function __construct(private array $counts)
    {
    }

    /**
     * @return array{creates: int, updates: int, unchanged: int}
     */
    public function counts(): array
    {
        return $this->counts;
    }
}
