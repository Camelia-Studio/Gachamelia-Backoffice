<?php

declare(strict_types=1);

namespace App\Backoffice;

final class CatalogBatchValidationException extends \InvalidArgumentException
{
    /**
     * @param list<array{line: ?int, column: ?string, message: string, value: ?string}> $errors
     */
    public function __construct(public readonly array $errors)
    {
        parent::__construct('invalid_catalogue_batch');
    }
}
