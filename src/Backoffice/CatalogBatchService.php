<?php

declare(strict_types=1);

namespace App\Backoffice;

use App\Backoffice\Csv\CatalogCsvDocument;
use App\Backoffice\Csv\CatalogCsvParser;
use App\Backoffice\Csv\CatalogCsvPreview;
use App\Backoffice\Csv\CatalogCsvSection;
use App\Backoffice\Csv\CatalogCsvTargetAdapterInterface;
use App\Backoffice\Csv\ServerCatalogCsvTargetAdapter;
use App\Backoffice\Csv\TemplateCatalogCsvTargetAdapter;
use App\Entity\CatalogTemplate;
use App\Entity\CatalogTemplateRank;
use App\Entity\DiscordServer;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\String\UnicodeString;

final readonly class CatalogBatchService
{
    public const int MAX_ROWS = 100;

    public function __construct(private EntityManagerInterface $entityManager, private CatalogCsvParser $parser, private ServerCatalogCsvTargetAdapter $serverAdapter, private TemplateCatalogCsvTargetAdapter $templateAdapter)
    {
    }

    /**
     * @param list<mixed>  $records
     * @param list<string> $allowedDiscordRoleIds
     */
    public function create(DiscordServer|CatalogTemplate $target, CatalogCsvSection $section, array $records, array $allowedDiscordRoleIds = []): int
    {
        if ([] === $records || \count($records) > self::MAX_ROWS) {
            throw new CatalogBatchValidationException([$this->error(null, null, 'batch_size')]);
        }
        $errors = [];
        $rows = [];
        $mappings = [];
        foreach ($records as $index => $record) {
            if (!\is_array($record)) {
                $errors[] = $this->error($index + 1, null, 'invalid_value');
                $rows[] = [];
                continue;
            }
            $row = [];
            foreach ($record as $column => $value) {
                if (\is_string($column)) {
                    $row[$column] = $value;
                }
            }
            $rows[] = $row;
            if ($target instanceof DiscordServer && CatalogCsvSection::Ranks === $section) {
                $id = \is_string($record['discord_id'] ?? null) ? trim($record['discord_id']) : '';
                if (!\in_array($id, $allowedDiscordRoleIds, true)) {
                    $errors[] = $this->error($index + 1, 'discord_id', 'discord_role_required');
                }
                $mappings[$index + 1] = $id;
            }
            if ($target instanceof CatalogTemplate && CatalogCsvSection::Ranks === $section) {
                $key = \is_string($record['role_key'] ?? null) ? trim($record['role_key']) : '';
                if ('' === $key || (new UnicodeString($key))->length() > 255) {
                    $errors[] = $this->error($index + 1, 'role_key', 'role_key_required');
                }
            }
            if (isset($record['emoji_source']) && !\in_array($record['emoji_source'], ['unicode', 'bot', 'server'], true)) {
                $errors[] = $this->error($index + 1, 'emoji_source', 'invalid_value');
            }
        }
        $document = $this->parser->parseRows($rows, $section);
        if ([] !== $errors || !$document->valid()) {
            throw new CatalogBatchValidationException([...$errors, ...$document->errors()]);
        }
        $adapter = $target instanceof DiscordServer ? $this->serverAdapter : $this->templateAdapter;
        $this->validate($target, $section, $document, $adapter, $mappings);

        return $this->entityManager->wrapInTransaction(function () use ($target, $section, $document, $adapter, $mappings): int {
            $this->entityManager->lock($target, LockMode::PESSIMISTIC_WRITE);
            $preview = $this->validate($target, $section, $document, $adapter, $mappings);
            $adapter->apply($target, $section, $preview, $mappings);

            return $preview->counts()['creates'];
        });
    }

    /**
     * @param array<int, string> $mappings
     */
    private function validate(DiscordServer|CatalogTemplate $target, CatalogCsvSection $section, CatalogCsvDocument $document, CatalogCsvTargetAdapterInterface $adapter, array $mappings): CatalogCsvPreview
    {
        $preview = $adapter->preview($target, $section, $document);
        // L'édition manuelle permet un catalogue en préparation ; les totaux restent affichés.
        $errors = array_values(array_filter($preview->errors(), static fn (array $error): bool => !\in_array($error['message'], ['invalid_rank_percentage_total', 'invalid_role_percentage_total', 'invalid_role_stat_percentage_total'], true)));
        if (\in_array('multiple_staff_ranks', array_column($errors, 'message'), true)) {
            $errors = array_values(array_filter($errors, static fn (array $error): bool => 'multiple_staff_ranks' !== $error['message']));
            foreach ($document->rows() as $row) {
                if (true === ($row['values']['est_staff'] ?? false)) {
                    $errors[] = $this->error($row['line'], 'est_staff', 'multiple_staff_ranks');
                }
            }
        }
        foreach ($preview->operations() as $operation) {
            if ('create' !== $operation['action']) {
                $errors[] = $this->error($operation['line'], $section->naturalKeyColumns()[0], 'entry_exists');
            }
        }
        if ($target instanceof CatalogTemplate && CatalogCsvSection::Ranks === $section) {
            $keys = [];
            foreach ($document->rows() as $row) {
                $key = (string) ($row['values']['role_key'] ?? '');
                $normalized = (new UnicodeString($key))->ascii()->lower()->toString();
                if (isset($keys[$normalized]) || null !== $this->entityManager->getRepository(CatalogTemplateRank::class)->findOneBy(['template' => $target, 'roleKey' => $key])) {
                    $errors[] = $this->error($row['line'], 'role_key', 'role_key_exists');
                }
                $keys[$normalized] = true;
            }
        }
        $validPreview = new CatalogCsvPreview($preview->operations(), [], $preview->totals(), $preview->discordRoleLines(), []);
        try {
            $adapter->validateApply($target, $section, $validPreview, $mappings);
        } catch (\InvalidArgumentException $exception) {
            $parts = explode(':', $exception->getMessage());
            $errors[] = $this->error(isset($parts[1]) ? (int) $parts[1] : null, 'discord_id', 'discord_role_exists');
        }
        if ([] !== $errors) {
            throw new CatalogBatchValidationException($errors);
        }

        return $validPreview;
    }

    /**
     * @return array{line: ?int, column: ?string, message: string, value: ?string}
     */
    private function error(?int $line, ?string $column, string $message): array
    {
        return ['line' => $line, 'column' => $column, 'message' => $message, 'value' => null];
    }
}
