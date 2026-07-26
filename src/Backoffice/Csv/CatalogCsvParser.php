<?php

declare(strict_types=1);

namespace App\Backoffice\Csv;

use Symfony\Component\String\UnicodeString;

final class CatalogCsvParser
{
    public const int MAX_FILE_SIZE = 5 * 1024 * 1024;
    public const int MAX_DATA_ROWS = 1000;

    public function parse(string $path, CatalogCsvSection $section): CatalogCsvDocument
    {
        if (!is_file($path) || !is_readable($path)) {
            return $this->invalid('unreadable_file');
        }

        $size = filesize($path);
        if (false === $size || $size > self::MAX_FILE_SIZE) {
            return $this->invalid('file_too_large');
        }

        $contents = file_get_contents($path);
        if (false === $contents) {
            return $this->invalid('unreadable_file');
        }
        if (1 !== preg_match('//u', $contents)) {
            return $this->invalid('invalid_utf8');
        }

        $delimiter = $this->detectDelimiter($contents);
        $file = new \SplFileObject($path);
        $file->setFlags(\SplFileObject::READ_CSV | \SplFileObject::DROP_NEW_LINE);
        $file->setCsvControl($delimiter, '"', '');

        $headers = null;
        $headerIndexes = [];
        $errors = [];
        $rows = [];
        $keys = [];
        $dataRowCount = 0;

        foreach ($file as $record) {
            if (!\is_array($record) || $this->blankRecord($record)) {
                continue;
            }

            $line = $file->key() + 1;
            if (null === $headers) {
                $headers = array_map(
                    static fn (mixed $value): string => trim((string) $value),
                    $record,
                );
                if (isset($headers[0])) {
                    $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]) ?? $headers[0];
                }
                [$headerIndexes, $headerErrors] = $this->validateHeaders($headers, $section);
                $errors = [...$errors, ...$headerErrors];
                if ([] !== $headerErrors) {
                    break;
                }

                continue;
            }

            ++$dataRowCount;
            if ($dataRowCount > self::MAX_DATA_ROWS) {
                return $this->invalid('too_many_rows');
            }

            [$values, $rowErrors] = $this->normalizeRecord($record, $line, $headerIndexes, $section);
            $errors = [...$errors, ...$rowErrors];
            if ([] !== $rowErrors) {
                continue;
            }

            $key = $section->naturalKey($values);
            if (isset($keys[$key])) {
                $column = $section->naturalKeyColumns()[0];
                $errors[] = [
                    'line' => $line,
                    'column' => $column,
                    'message' => 'duplicate_natural_key',
                    'value' => (string) ($values[$column] ?? ''),
                ];
                continue;
            }

            $keys[$key] = true;
            $rows[] = ['line' => $line, 'key' => $key, 'values' => $values];
        }

        if (null === $headers) {
            $errors[] = ['line' => null, 'column' => null, 'message' => 'missing_header', 'value' => null];
        }

        return new CatalogCsvDocument($rows, $errors);
    }

    private function detectDelimiter(string $contents): string
    {
        $firstLine = strtok(preg_replace('/^\xEF\xBB\xBF/', '', $contents) ?? $contents, "\r\n");
        if (false === $firstLine) {
            return ';';
        }

        return \count(str_getcsv($firstLine, ';', '"', '')) >= \count(str_getcsv($firstLine, ',', '"', ''))
            ? ';'
            : ',';
    }

    /**
     * @param list<mixed> $record
     */
    private function blankRecord(array $record): bool
    {
        foreach ($record as $value) {
            if ('' !== trim((string) $value)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<string> $headers
     *
     * @return array{
     *     array<string, int>,
     *     list<array{line: ?int, column: ?string, message: string, value: ?string}>
     * }
     */
    private function validateHeaders(array $headers, CatalogCsvSection $section): array
    {
        $indexes = [];
        $errors = [];
        foreach ($headers as $index => $header) {
            if (isset($indexes[$header])) {
                $errors[] = ['line' => 1, 'column' => $header, 'message' => 'duplicate_header', 'value' => $header];
                continue;
            }
            if (!\in_array($header, $section->headers(), true)) {
                $errors[] = ['line' => 1, 'column' => $header, 'message' => 'unknown_header', 'value' => $header];
                continue;
            }
            $indexes[$header] = $index;
        }

        foreach ($section->requiredHeaders() as $requiredHeader) {
            if (!isset($indexes[$requiredHeader])) {
                $errors[] = ['line' => 1, 'column' => $requiredHeader, 'message' => 'missing_required_header', 'value' => null];
            }
        }

        return [$indexes, $errors];
    }

    /**
     * @param list<mixed>        $record
     * @param array<string, int> $headerIndexes
     *
     * @return array{
     *     array<string, string|int|bool|null>,
     *     list<array{line: ?int, column: ?string, message: string, value: ?string}>
     * }
     */
    private function normalizeRecord(array $record, int $line, array $headerIndexes, CatalogCsvSection $section): array
    {
        $values = [];
        $errors = [];
        foreach ($section->columnTypes() as $column => $type) {
            $raw = isset($headerIndexes[$column]) ? trim((string) ($record[$headerIndexes[$column]] ?? '')) : '';
            if ('' === $raw) {
                if (\in_array($column, $section->requiredHeaders(), true)) {
                    $errors[] = ['line' => $line, 'column' => $column, 'message' => 'required_value', 'value' => null];
                }
                $values[$column] = 'boolean' === $type ? false : null;
                continue;
            }

            if ('percentage' === $type) {
                if (!preg_match('/^-?\d+$/', $raw)) {
                    $errors[] = ['line' => $line, 'column' => $column, 'message' => 'invalid_integer', 'value' => $raw];
                    $values[$column] = null;
                    continue;
                }
                $percentage = (int) $raw;
                if ($percentage < 0 || $percentage > 100) {
                    $errors[] = ['line' => $line, 'column' => $column, 'message' => 'percentage_out_of_range', 'value' => $raw];
                }
                $values[$column] = $percentage;
                continue;
            }

            if ('boolean' === $type) {
                $boolean = $this->parseBoolean($raw);
                if (null === $boolean) {
                    $errors[] = ['line' => $line, 'column' => $column, 'message' => 'invalid_boolean', 'value' => $raw];
                }
                $values[$column] = $boolean;
                continue;
            }

            $maximumLength = 'emoji' === $column ? 64 : 255;
            if ((new UnicodeString($raw))->length() > $maximumLength) {
                $errors[] = ['line' => $line, 'column' => $column, 'message' => 'value_too_long', 'value' => $raw];
            }
            $values[$column] = $raw;
        }

        return [$values, $errors];
    }

    private function parseBoolean(string $value): ?bool
    {
        return match ((new UnicodeString($value))->lower()->toString()) {
            'oui', 'true', '1' => true,
            'non', 'false', '0' => false,
            default => null,
        };
    }

    private function invalid(string $message): CatalogCsvDocument
    {
        return new CatalogCsvDocument([], [[
            'line' => null,
            'column' => null,
            'message' => $message,
            'value' => null,
        ]]);
    }
}
