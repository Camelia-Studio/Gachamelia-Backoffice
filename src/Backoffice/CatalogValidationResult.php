<?php

declare(strict_types=1);

namespace App\Backoffice;

final readonly class CatalogValidationResult
{
    /**
     * @param list<string> $errors
     * @param list<string> $warnings
     */
    public function __construct(
        private array $errors,
        private array $warnings,
    ) {
    }

    public function ready(): bool
    {
        return [] === $this->errors;
    }

    /**
     * @return array{ready: bool, errors: list<string>, warnings: list<string>}
     */
    public function toArray(): array
    {
        return [
            'ready' => $this->ready(),
            'errors' => $this->errors,
            'warnings' => $this->warnings,
        ];
    }
}
