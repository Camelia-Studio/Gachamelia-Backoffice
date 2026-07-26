<?php

declare(strict_types=1);

namespace App\Backoffice\Csv;

final readonly class CatalogCsvPreview
{
    private string $fingerprint;

    /**
     * @param list<array{line: int, key: string, action: 'create'|'update'|'unchanged', current: ?array<string, mixed>, incoming: array<string, mixed>}> $operations
     * @param list<array{line: ?int, column: ?string, message: string, value: ?string}>                                                                  $errors
     * @param list<array{label: string, current: int, projected: int, valid: bool}>                                                                      $totals
     * @param list<int>                                                                                                                                  $discordRoleLines
     * @param list<array<string, mixed>>                                                                                                                 $state
     */
    public function __construct(
        private array $operations,
        private array $errors,
        private array $totals,
        private array $discordRoleLines,
        array $state,
    ) {
        $this->fingerprint = hash('sha256', json_encode(
            [$this->operations, $this->errors, $this->totals, $this->discordRoleLines, $state],
            JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION,
        ));
    }

    public function valid(): bool
    {
        return [] === $this->errors;
    }

    /**
     * @return array{creates: int, updates: int, unchanged: int}
     */
    public function counts(): array
    {
        $counts = ['creates' => 0, 'updates' => 0, 'unchanged' => 0];
        foreach ($this->operations as $operation) {
            ++$counts[match ($operation['action']) {
                'create' => 'creates',
                'update' => 'updates',
                'unchanged' => 'unchanged',
            }];
        }

        return $counts;
    }

    /**
     * @return list<array{line: int, key: string, action: 'create'|'update'|'unchanged', current: ?array<string, mixed>, incoming: array<string, mixed>}>
     */
    public function operations(): array
    {
        return $this->operations;
    }

    /**
     * @return list<array{line: ?int, column: ?string, message: string, value: ?string}>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * @return list<array{label: string, current: int, projected: int, valid: bool}>
     */
    public function totals(): array
    {
        return $this->totals;
    }

    /**
     * @return list<int>
     */
    public function discordRoleLines(): array
    {
        return $this->discordRoleLines;
    }

    public function fingerprint(): string
    {
        return $this->fingerprint;
    }
}
