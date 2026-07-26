<?php

namespace App\Tests\Backoffice\Csv;

use App\Backoffice\Csv\CatalogCsvSampleGenerator;
use App\Backoffice\Csv\CatalogCsvSection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CatalogCsvSampleGeneratorTest extends TestCase
{
    /**
     * @param list<string> $headers
     */
    #[DataProvider('sections')]
    public function testGeneratesFrenchExcelFriendlyExample(CatalogCsvSection $section, array $headers, string $filename): void
    {
        $csv = (new CatalogCsvSampleGenerator())->generate($section);

        self::assertStringStartsWith("\xEF\xBB\xBF".implode(';', $headers)."\n", $csv);
        self::assertSame(CatalogCsvSection::RankStats === $section ? 4 : 3, substr_count($csv, "\n"));
        self::assertSame($filename, $section->exampleFilename());
    }

    public function testRankStatExampleCompletesTheStaffRankFromTheRankExample(): void
    {
        $csv = (new CatalogCsvSampleGenerator())->generate(CatalogCsvSection::RankStats);

        self::assertStringContainsString("Gardien;Force;100\n", $csv);
    }

    /**
     * @return iterable<string, array{CatalogCsvSection, list<string>, string}>
     */
    public static function sections(): iterable
    {
        yield 'rangs' => [CatalogCsvSection::Ranks, ['nom', 'pourcentage', 'titre_depart', 'est_staff'], 'exemple-rangs.csv'];
        yield 'probabilités' => [CatalogCsvSection::RankStats, ['rang', 'stat', 'pourcentage'], 'exemple-probabilites-rang-stat.csv'];
        yield 'arrivées' => [CatalogCsvSection::WelcomeMessages, ['rang', 'message'], 'exemple-messages-arrivee.csv'];
        yield 'départs' => [CatalogCsvSection::ByeMessages, ['rang', 'message'], 'exemple-messages-depart.csv'];
        yield 'rôles' => [CatalogCsvSection::Roles, ['nom', 'pourcentage', 'emoji'], 'exemple-roles.csv'];
        yield 'stats' => [CatalogCsvSection::Stats, ['nom'], 'exemple-stats.csv'];
        yield 'éléments' => [CatalogCsvSection::Elements, ['nom', 'emoji'], 'exemple-elements.csv'];
    }
}
