<?php

declare(strict_types=1);

namespace App\Backoffice\Csv;

use App\Entity\CatalogTemplate;
use App\Entity\DiscordServer;

final readonly class CatalogCsvExampleFactory
{
    public function __construct(private CatalogCsvImportService $catalogue, private CatalogCsvSampleGenerator $generator)
    {
    }

    public function generate(DiscordServer|CatalogTemplate $target, CatalogCsvSection $section): string
    {
        $rows = $this->catalogue->rows($target, $section);
        if ([] === $rows) {
            $rows = match ($section) {
                CatalogCsvSection::WelcomeMessages, CatalogCsvSection::ByeMessages => array_map(
                    static fn (array $rank): array => ['rang' => $rank['nom'], 'message' => CatalogCsvSection::WelcomeMessages === $section ? 'Bienvenue parmi nous, {user}.' : 'À bientôt, {user}.'],
                    $this->catalogue->rows($target, CatalogCsvSection::Ranks),
                ),
                CatalogCsvSection::RoleStats => $this->roleStats($target),
                default => $section->sampleRows(),
            };
        }
        if (\in_array($section, [CatalogCsvSection::Ranks, CatalogCsvSection::Roles, CatalogCsvSection::RoleStats], true)) {
            $groups = [];
            foreach ($rows as $index => $row) {
                $groups[CatalogCsvSection::RoleStats === $section ? (string) $row['role'] : 'all'][] = $index;
            }
            foreach ($groups as $indexes) {
                $total = array_sum(array_map(static fn (int $index): int => (int) $rows[$index]['pourcentage'], $indexes));
                if (100 === $total) {
                    continue;
                }
                $remaining = 100;
                foreach ($indexes as $position => $index) {
                    $value = $position === \count($indexes) - 1 ? $remaining : (0 === $total ? intdiv(100, \count($indexes)) : (int) floor(100 * (int) $rows[$index]['pourcentage'] / $total));
                    $rows[$index]['pourcentage'] = $value;
                    $remaining -= $value;
                }
            }
        }

        return $this->generator->generate($section, $rows);
    }

    /**
     * @return list<array<string, string|int|bool|null>>
     */
    private function roleStats(DiscordServer|CatalogTemplate $target): array
    {
        $stats = $this->catalogue->rows($target, CatalogCsvSection::Stats);
        if ([] === $stats) {
            return [];
        }

        return array_map(
            static fn (array $role): array => ['role' => $role['nom'], 'stat' => $stats[0]['nom'], 'pourcentage' => 100],
            $this->catalogue->rows($target, CatalogCsvSection::Roles),
        );
    }
}
