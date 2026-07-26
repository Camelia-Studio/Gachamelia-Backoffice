<?php

declare(strict_types=1);

namespace App\Backoffice\Csv;

final readonly class CatalogCsvSampleGenerator
{
    public function generate(CatalogCsvSection $section): string
    {
        $stream = fopen('php://temp', 'w+');
        if (false === $stream) {
            throw new \RuntimeException('Unable to generate CSV example.');
        }

        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, $section->headers(), ';', '"', '', "\n");
        foreach ($section->sampleRows() as $row) {
            fputcsv(
                $stream,
                array_map(static fn (string $header): string => $row[$header] ?? '', $section->headers()),
                ';',
                '"',
                '',
                "\n",
            );
        }
        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        if (false === $csv) {
            throw new \RuntimeException('Unable to read generated CSV example.');
        }

        return $csv;
    }
}
