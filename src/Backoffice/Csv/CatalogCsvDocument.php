<?php

declare(strict_types=1);

namespace App\Backoffice\Csv;

final readonly class CatalogCsvDocument
{
    /**
     * @param list<array{line: int, key: string, values: array<string, string|int|bool|null>}> $rows
     * @param list<array{line: ?int, column: ?string, message: string, value: ?string}>         $errors
     */
    public function __construct(
        private array $rows,
        private array $errors,
    ) {
    }

    /**
     * @return list<array{line: int, key: string, values: array<string, string|int|bool|null>}>
     */
    public function rows(): array
    {
        return $this->rows;
    }

    /**
     * @return list<array{line: ?int, column: ?string, message: string, value: ?string}>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    public function valid(): bool
    {
        return [] === $this->errors;
    }

    /**
     * @return array{
     *     rows: list<array{line: int, key: string, values: array<string, string|int|bool|null>}>,
     *     errors: list<array{line: ?int, column: ?string, message: string, value: ?string}>
     * }
     */
    public function toArray(): array
    {
        return ['rows' => $this->rows, 'errors' => $this->errors];
    }

    /**
     * @param array{
     *     rows: list<array{line: int, key: string, values: array<string, string|int|bool|null>}>,
     *     errors: list<array{line: ?int, column: ?string, message: string, value: ?string}>
     * } $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self($payload['rows'], $payload['errors']);
    }
}
