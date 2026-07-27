<?php

declare(strict_types=1);

namespace App\Tests\Backoffice\Csv;

use App\Backoffice\Csv\CatalogCsvParser;
use App\Backoffice\Csv\CatalogCsvSection;
use PHPUnit\Framework\TestCase;

final class CatalogCsvParserTest extends TestCase
{
    /** @var list<string> */
    private array $temporaryFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $path) {
            @unlink($path);
        }
    }

    public function testParsesBomSemicolonArbitraryHeadersAndFrenchBoolean(): void
    {
        $document = (new CatalogCsvParser())->parse($this->csv(
            "\xEF\xBB\xBFest_staff;nom;titre_depart;pourcentage\noui; Gardien ;Au revoir;100\n",
        ), CatalogCsvSection::Ranks);

        self::assertTrue($document->valid());
        self::assertSame([[
            'line' => 2,
            'key' => 'gardien',
            'values' => [
                'nom' => 'Gardien',
                'pourcentage' => 100,
                'titre_depart' => 'Au revoir',
                'est_staff' => true,
            ],
        ]], $document->rows());
    }

    public function testParsesCommaAndQuotedSeparator(): void
    {
        $document = (new CatalogCsvParser())->parse($this->csv(
            "rang,message\nNovice,\"Bienvenue, parmi nous\"\n",
        ), CatalogCsvSection::WelcomeMessages);

        self::assertTrue($document->valid());
        self::assertSame('Bienvenue, parmi nous', $document->rows()[0]['values']['message']);
    }

    public function testReportsUnknownMissingAndDuplicateHeaders(): void
    {
        $document = (new CatalogCsvParser())->parse($this->csv(
            "nom;nom;inconnue\nForce;Force;x\n",
        ), CatalogCsvSection::Stats);

        self::assertFalse($document->valid());
        self::assertSame(
            ['duplicate_header', 'unknown_header'],
            array_column($document->errors(), 'message'),
        );
    }

    public function testRejectsDuplicateNaturalKeysIgnoringCaseAccentsAndSpaces(): void
    {
        $document = (new CatalogCsvParser())->parse($this->csv(
            "nom\nÉther\n éTHER \n",
        ), CatalogCsvSection::Stats);

        self::assertFalse($document->valid());
        self::assertSame([[
            'line' => 3,
            'column' => 'nom',
            'message' => 'duplicate_natural_key',
            'value' => 'éTHER',
        ]], $document->errors());
    }

    public function testReportsInvalidTypesAndRequiredValuesOnEveryLine(): void
    {
        $document = (new CatalogCsvParser())->parse($this->csv(
            "nom;pourcentage;titre_depart;est_staff\n;101;;peut-etre\nOracle;abc;;non\n",
        ), CatalogCsvSection::Ranks);

        self::assertFalse($document->valid());
        self::assertSame(
            ['required_value', 'percentage_out_of_range', 'invalid_boolean', 'invalid_integer'],
            array_column($document->errors(), 'message'),
        );
    }

    public function testRejectsInvalidUtf8AndFileLargerThanFiveMebibytes(): void
    {
        $parser = new CatalogCsvParser();
        $invalidUtf8 = $parser->parse($this->csv("nom\n\xFF\n"), CatalogCsvSection::Stats);
        $oversized = $parser->parse(
            $this->csv(str_repeat('x', CatalogCsvParser::MAX_FILE_SIZE + 1)),
            CatalogCsvSection::Stats,
        );

        self::assertSame('invalid_utf8', $invalidUtf8->errors()[0]['message']);
        self::assertSame('file_too_large', $oversized->errors()[0]['message']);
    }

    public function testRejectsOversizedHeadersAndTooManyColumnsBeforeCsvParsing(): void
    {
        $parser = new CatalogCsvParser();
        $oversizedHeader = $parser->parse(
            $this->csv(str_repeat('n', 8193)."\n"),
            CatalogCsvSection::Stats,
        );
        $tooManyColumns = $parser->parse(
            $this->csv(implode(';', array_fill(0, 65, 'nom'))."\n"),
            CatalogCsvSection::Stats,
        );

        self::assertSame('header_too_long', $oversizedHeader->errors()[0]['message']);
        self::assertSame('too_many_columns', $tooManyColumns->errors()[0]['message']);
    }

    public function testRejectsMoreThanOneThousandDataRowsButIgnoresBlankRows(): void
    {
        $rows = "nom\n".implode("\n", array_map(static fn (int $index): string => 'Stat '.$index, range(1, 1001)))."\n\n";
        $document = (new CatalogCsvParser())->parse($this->csv($rows), CatalogCsvSection::Stats);

        self::assertFalse($document->valid());
        self::assertSame('too_many_rows', $document->errors()[0]['message']);
    }

    public function testDocumentCanRoundTripThroughSessionPayload(): void
    {
        $document = (new CatalogCsvParser())->parse($this->csv("nom\nForce\n"), CatalogCsvSection::Stats);

        self::assertSame($document->toArray(), $document::fromArray($document->toArray())->toArray());
    }

    private function csv(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'catalog-csv-');
        self::assertIsString($path);
        file_put_contents($path, $contents);
        $this->temporaryFiles[] = $path;

        return $path;
    }
}
