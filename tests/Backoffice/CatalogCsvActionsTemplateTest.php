<?php

declare(strict_types=1);

namespace App\Tests\Backoffice;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Twig\Environment;

final class CatalogCsvActionsTemplateTest extends KernelTestCase
{
    public function testProgressionSectionDoesNotGenerateCatalogCsvRoutes(): void
    {
        self::bootKernel();
        $twig = self::getContainer()->get(Environment::class);

        $html = $twig->render('backoffice/_catalog_csv_actions.html.twig', [
            'target_type' => 'server',
            'target_id' => '123456789',
            'active_section' => 'progression',
            'read_only' => false,
        ]);

        self::assertSame('', trim($html));
    }

    public function testRankSectionStillGeneratesCatalogCsvRoutes(): void
    {
        self::bootKernel();
        $twig = self::getContainer()->get(Environment::class);

        $html = $twig->render('backoffice/_catalog_csv_actions.html.twig', [
            'target_type' => 'server',
            'target_id' => '123456789',
            'active_section' => 'ranks',
            'read_only' => false,
        ]);

        self::assertStringContainsString('catalog-csv-actions', $html);
        self::assertStringContainsString('/configuration/ranks/csv/exporter', $html);
    }
}
