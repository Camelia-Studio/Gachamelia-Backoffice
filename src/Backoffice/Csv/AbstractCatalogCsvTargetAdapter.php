<?php

declare(strict_types=1);

namespace App\Backoffice\Csv;

use App\Entity\ByeMessage;
use App\Entity\CatalogTemplate;
use App\Entity\CatalogTemplateByeMessage;
use App\Entity\CatalogTemplateElement;
use App\Entity\CatalogTemplateRank;
use App\Entity\CatalogTemplateRoleStat;
use App\Entity\CatalogTemplateRole;
use App\Entity\CatalogTemplateStat;
use App\Entity\CatalogTemplateWelcomeMessage;
use App\Entity\CharacterRole;
use App\Entity\DiscordServer;
use App\Entity\Element;
use App\Entity\Rank;
use App\Entity\RoleStat;
use App\Entity\Stat;
use App\Entity\WelcomeMessage;
use Doctrine\ORM\EntityManagerInterface;

abstract class AbstractCatalogCsvTargetAdapter implements CatalogCsvTargetAdapterInterface
{
    public function __construct(protected readonly EntityManagerInterface $entityManager)
    {
    }

    public function preview(
        DiscordServer|CatalogTemplate $target,
        CatalogCsvSection $section,
        CatalogCsvDocument $document,
    ): CatalogCsvPreview {
        $entities = $this->entities($target, $section);
        $state = array_map($this->state(...), $entities);
        usort($state, static fn (array $left, array $right): int => json_encode($left) <=> json_encode($right));

        if (!$document->valid()) {
            return new CatalogCsvPreview([], $document->errors(), [], [], $state);
        }

        $rows = array_map(
            fn (array $row): array => [
                'line' => $row['line'],
                'key' => $section->naturalKey($this->canonicalValues($section, $row['values'])),
                'values' => $this->canonicalValues($section, $row['values']),
            ],
            $document->rows(),
        );

        return match ($section) {
            CatalogCsvSection::Ranks => $this->previewRanks($entities, $rows, $state),
            CatalogCsvSection::Roles => $this->previewRoles($entities, $rows, $state),
            CatalogCsvSection::RoleStats => $this->previewRoleStats($target, $entities, $rows, $state),
            CatalogCsvSection::WelcomeMessages, CatalogCsvSection::ByeMessages => $this->previewMessages($target, $section, $entities, $rows, $state),
            CatalogCsvSection::Stats, CatalogCsvSection::Elements => $this->previewSimple($section, $entities, $rows, $state),
        };
    }

    public function apply(
        DiscordServer|CatalogTemplate $target,
        CatalogCsvSection $section,
        CatalogCsvPreview $preview,
        array $discordRoleIdsByLine,
    ): CatalogCsvImportResult {
        if (!$preview->valid()) {
            throw new \InvalidArgumentException('invalid_csv_import');
        }

        match ($section) {
            CatalogCsvSection::Ranks => $this->applyRanks($target, $preview, $discordRoleIdsByLine),
            CatalogCsvSection::Roles => $this->applyRoles($target, $preview),
            CatalogCsvSection::RoleStats => $this->applyRoleStats($target, $preview),
            CatalogCsvSection::WelcomeMessages, CatalogCsvSection::ByeMessages => $this->applyMessages($target, $section, $preview),
            CatalogCsvSection::Stats => $this->applyStats($target, $preview),
            CatalogCsvSection::Elements => $this->applyElements($target, $preview),
        };

        $this->entityManager->flush();

        return new CatalogCsvImportResult($preview->counts());
    }

    public function validateApply(
        DiscordServer|CatalogTemplate $target,
        CatalogCsvSection $section,
        CatalogCsvPreview $preview,
        array $discordRoleIdsByLine,
    ): void {
    }

    /**
     * @return class-string
     */
    abstract protected function entityClass(CatalogCsvSection $section): string;

    abstract protected function scopeField(): string;

    /**
     * @param array<string, mixed> $values
     * @param array<int, string>   $discordRoleIdsByLine
     * @param list<object>         $existingRanks
     */
    abstract protected function createRank(
        DiscordServer|CatalogTemplate $target,
        array $values,
        int $line,
        array $discordRoleIdsByLine,
        array $existingRanks,
    ): object;

    /**
     * @param array<string, mixed> $values
     */
    abstract protected function updateRank(object $rank, array $values): void;

    /**
     * @return list<object>
     */
    final protected function entities(DiscordServer|CatalogTemplate $target, CatalogCsvSection $section): array
    {
        return $this->entityManager->getRepository($this->entityClass($section))->findBy([
            $this->scopeField() => $target,
        ]);
    }

    /**
     * @param list<object>                                                                     $entities
     * @param list<array{line: int, key: string, values: array<string, string|int|bool|null>}> $rows
     * @param list<array<string, mixed>>                                                       $state
     */
    private function previewRanks(array $entities, array $rows, array $state): CatalogCsvPreview
    {
        $byKey = $this->byNaturalKey(CatalogCsvSection::Ranks, $entities);
        $operations = $this->operations(CatalogCsvSection::Ranks, $byKey, $rows);
        $projected = [];
        $staff = [];
        foreach ($entities as $rank) {
            $key = CatalogCsvSection::Ranks->naturalKey($this->payload(CatalogCsvSection::Ranks, $rank));
            $projected[$key] = $this->percentage($rank);
            $staff[$key] = $this->isStaff($rank);
        }
        foreach ($rows as $row) {
            $projected[$row['key']] = (int) $row['values']['pourcentage'];
            $staff[$row['key']] = (bool) $row['values']['est_staff'];
        }

        $currentTotal = array_sum(array_map($this->percentage(...), $entities));
        $projectedTotal = array_sum($projected);
        $errors = [];
        if (100 !== $projectedTotal) {
            $errors[] = $this->error('invalid_rank_percentage_total', \sprintf('%d %%', $projectedTotal));
        }
        if (\count(array_filter($staff)) > 1) {
            $errors[] = $this->error('multiple_staff_ranks');
        }

        $discordRoleLines = [];
        if ($this instanceof ServerCatalogCsvTargetAdapter) {
            foreach ($operations as $operation) {
                if ('create' === $operation['action']) {
                    $discordRoleLines[] = $operation['line'];
                }
            }
        }

        return new CatalogCsvPreview(
            $operations,
            $errors,
            [['label' => 'Rangs', 'current' => $currentTotal, 'projected' => $projectedTotal, 'valid' => 100 === $projectedTotal]],
            $discordRoleLines,
            $state,
        );
    }

    /**
     * @param list<object>                                                                     $entities
     * @param list<array{line: int, key: string, values: array<string, string|int|bool|null>}> $rows
     * @param list<array<string, mixed>>                                                       $state
     */
    private function previewRoles(array $entities, array $rows, array $state): CatalogCsvPreview
    {
        $byKey = $this->byNaturalKey(CatalogCsvSection::Roles, $entities);
        $operations = $this->operations(CatalogCsvSection::Roles, $byKey, $rows);
        $projected = [];
        foreach ($entities as $role) {
            $key = CatalogCsvSection::Roles->naturalKey($this->payload(CatalogCsvSection::Roles, $role));
            $projected[$key] = $this->percentage($role);
        }
        foreach ($rows as $row) {
            $projected[$row['key']] = (int) $row['values']['pourcentage'];
        }

        $currentTotal = array_sum(array_map($this->percentage(...), $entities));
        $projectedTotal = array_sum($projected);
        $errors = 100 === $projectedTotal
            ? []
            : [$this->error('invalid_role_percentage_total', \sprintf('%d %%', $projectedTotal))];

        return new CatalogCsvPreview(
            $operations,
            $errors,
            [['label' => 'Rôles', 'current' => $currentTotal, 'projected' => $projectedTotal, 'valid' => 100 === $projectedTotal]],
            [],
            $state,
        );
    }

    /**
     * @param list<object>                                                                     $entities
     * @param list<array{line: int, key: string, values: array<string, string|int|bool|null>}> $rows
     * @param list<array<string, mixed>>                                                       $state
     */
    private function previewRoleStats(
        DiscordServer|CatalogTemplate $target,
        array $entities,
        array $rows,
        array $state,
    ): CatalogCsvPreview {
        $roles = $this->entities($target, CatalogCsvSection::Roles);
        $stats = $this->entities($target, CatalogCsvSection::Stats);
        $rolesByKey = $this->byNaturalKey(CatalogCsvSection::Roles, $roles);
        $statsByKey = $this->byNaturalKey(CatalogCsvSection::Stats, $stats);
        $existing = $this->byNaturalKey(CatalogCsvSection::RoleStats, $entities);
        $operations = [];
        $errors = [];
        $touchedRoles = [];

        foreach ($rows as $row) {
            $roleKey = CatalogCsvSection::Roles->naturalKey(['nom' => $row['values']['role']]);
            $statKey = CatalogCsvSection::Stats->naturalKey(['nom' => $row['values']['stat']]);
            if (!isset($rolesByKey[$roleKey])) {
                $errors[] = $this->error('role_not_found', (string) $row['values']['role'], $row['line'], 'role');
                continue;
            }
            if (!isset($statsByKey[$statKey])) {
                $errors[] = $this->error('stat_not_found', (string) $row['values']['stat'], $row['line'], 'stat');
                continue;
            }
            $touchedRoles[$roleKey] = (string) $row['values']['role'];
            $operations[] = $this->operation(
                $row,
                $existing[$row['key']] ?? null,
                CatalogCsvSection::RoleStats,
            );
        }

        $currentByRole = [];
        $projectedByRole = [];
        foreach ($entities as $roleStat) {
            $roleKey = CatalogCsvSection::Roles->naturalKey(['nom' => $this->roleName($roleStat)]);
            $key = CatalogCsvSection::RoleStats->naturalKey($this->payload(CatalogCsvSection::RoleStats, $roleStat));
            $currentByRole[$roleKey] = ($currentByRole[$roleKey] ?? 0) + $this->percentage($roleStat);
            $projectedByRole[$roleKey][$key] = $this->percentage($roleStat);
        }
        foreach ($rows as $row) {
            $roleKey = CatalogCsvSection::Roles->naturalKey(['nom' => $row['values']['role']]);
            if (isset($touchedRoles[$roleKey])) {
                $projectedByRole[$roleKey][$row['key']] = (int) $row['values']['pourcentage'];
            }
        }

        $totals = [];
        foreach ($touchedRoles as $roleKey => $roleName) {
            $projectedTotal = array_sum($projectedByRole[$roleKey] ?? []);
            $totals[] = [
                'label' => \sprintf('Rôle %s', $roleName),
                'current' => $currentByRole[$roleKey] ?? 0,
                'projected' => $projectedTotal,
                'valid' => 100 === $projectedTotal,
            ];
            if (100 !== $projectedTotal) {
                $errors[] = $this->error(
                    'invalid_role_stat_percentage_total',
                    \sprintf('%s : %d %%', $roleName, $projectedTotal),
                );
            }
        }

        return new CatalogCsvPreview($operations, $errors, $totals, [], $state);
    }

    /**
     * @param list<object>                                                                     $entities
     * @param list<array{line: int, key: string, values: array<string, string|int|bool|null>}> $rows
     * @param list<array<string, mixed>>                                                       $state
     */
    private function previewMessages(
        DiscordServer|CatalogTemplate $target,
        CatalogCsvSection $section,
        array $entities,
        array $rows,
        array $state,
    ): CatalogCsvPreview {
        $ranks = $this->byNaturalKey(CatalogCsvSection::Ranks, $this->entities($target, CatalogCsvSection::Ranks));
        $existing = $this->byNaturalKey($section, $entities);
        $operations = [];
        $errors = [];
        foreach ($rows as $row) {
            $rankKey = CatalogCsvSection::Ranks->naturalKey(['nom' => $row['values']['rang']]);
            if (!isset($ranks[$rankKey])) {
                $errors[] = $this->error('rank_not_found', (string) $row['values']['rang'], $row['line'], 'rang');
                continue;
            }
            $operations[] = $this->operation($row, $existing[$row['key']] ?? null, $section);
        }

        return new CatalogCsvPreview($operations, $errors, [], [], $state);
    }

    /**
     * @param list<object>                                                                     $entities
     * @param list<array{line: int, key: string, values: array<string, string|int|bool|null>}> $rows
     * @param list<array<string, mixed>>                                                       $state
     */
    private function previewSimple(
        CatalogCsvSection $section,
        array $entities,
        array $rows,
        array $state,
    ): CatalogCsvPreview {
        return new CatalogCsvPreview(
            $this->operations($section, $this->byNaturalKey($section, $entities), $rows),
            [],
            [],
            [],
            $state,
        );
    }

    /**
     * @param list<object> $entities
     *
     * @return array<string, object>
     */
    private function byNaturalKey(CatalogCsvSection $section, array $entities): array
    {
        $indexed = [];
        foreach ($entities as $entity) {
            $indexed[$section->naturalKey($this->payload($section, $entity))] = $entity;
        }

        return $indexed;
    }

    /**
     * @param array<string, object>                                                            $existing
     * @param list<array{line: int, key: string, values: array<string, string|int|bool|null>}> $rows
     *
     * @return list<array{line: int, key: string, action: 'create'|'update'|'unchanged', current: ?array<string, mixed>, incoming: array<string, mixed>}>
     */
    private function operations(CatalogCsvSection $section, array $existing, array $rows): array
    {
        return array_map(
            fn (array $row): array => $this->operation($row, $existing[$row['key']] ?? null, $section),
            $rows,
        );
    }

    /**
     * @param array{line: int, key: string, values: array<string, string|int|bool|null>} $row
     *
     * @return array{line: int, key: string, action: 'create'|'update'|'unchanged', current: ?array<string, mixed>, incoming: array<string, mixed>}
     */
    private function operation(array $row, ?object $existing, CatalogCsvSection $section): array
    {
        $current = null === $existing ? null : $this->payload($section, $existing);
        $comparableCurrent = $current;
        if (null !== $comparableCurrent && \in_array($section, [
            CatalogCsvSection::WelcomeMessages,
            CatalogCsvSection::ByeMessages,
        ], true)) {
            $comparableCurrent['rang'] = $row['values']['rang'];
        }
        if (null !== $comparableCurrent && CatalogCsvSection::RoleStats === $section) {
            $comparableCurrent['role'] = $row['values']['role'];
            $comparableCurrent['stat'] = $row['values']['stat'];
        }
        $action = null === $current ? 'create' : ($comparableCurrent === $row['values'] ? 'unchanged' : 'update');

        return [
            'line' => $row['line'],
            'key' => $row['key'],
            'action' => $action,
            'current' => $current,
            'incoming' => $row['values'],
        ];
    }

    /**
     * @param array<string, string|int|bool|null> $values
     *
     * @return array<string, string|int|bool|null>
     */
    private function canonicalValues(CatalogCsvSection $section, array $values): array
    {
        foreach (['nom', 'rang', 'stat'] as $column) {
            if (isset($values[$column])) {
                $values[$column] = trim((string) $values[$column]);
            }
        }
        if (isset($values['message'])) {
            $values['message'] = trim((string) $values['message']);
        }
        if (\array_key_exists('titre_depart', $values)) {
            $values['titre_depart'] = '' === trim((string) ($values['titre_depart'] ?? ''))
                ? null
                : trim((string) $values['titre_depart']);
        }
        if (\array_key_exists('emoji', $values)) {
            $default = CatalogCsvSection::Roles === $section
                ? CharacterRole::DEFAULT_EMOJI
                : Element::DEFAULT_EMOJI;
            $values['emoji'] = '' === trim((string) ($values['emoji'] ?? ''))
                ? $default
                : trim((string) $values['emoji']);
        }
        if (CatalogCsvSection::Ranks === $section) {
            $values['est_staff'] = (bool) ($values['est_staff'] ?? false);
        }

        return $values;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(CatalogCsvSection $section, object $entity): array
    {
        return match ($section) {
            CatalogCsvSection::Ranks => [
                'nom' => $this->name($entity),
                'pourcentage' => $this->percentage($entity),
                'titre_depart' => $this->byeTitle($entity),
                'est_staff' => $this->isStaff($entity),
            ],
            CatalogCsvSection::RoleStats => [
                'role' => $this->roleName($entity),
                'stat' => $this->statName($entity),
                'pourcentage' => $this->percentage($entity),
            ],
            CatalogCsvSection::WelcomeMessages, CatalogCsvSection::ByeMessages => [
                'rang' => $this->rankName($entity),
                'message' => $this->message($entity),
            ],
            CatalogCsvSection::Roles => [
                'nom' => $this->name($entity),
                'pourcentage' => $this->percentage($entity),
                'emoji' => $this->emoji($entity, CharacterRole::DEFAULT_EMOJI),
            ],
            CatalogCsvSection::Stats => ['nom' => $this->name($entity)],
            CatalogCsvSection::Elements => [
                'nom' => $this->name($entity),
                'emoji' => $this->emoji($entity, Element::DEFAULT_EMOJI),
            ],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function state(object $entity): array
    {
        $state = ['class' => $entity::class];
        if ($entity instanceof Rank) {
            return $state + ['id' => $entity->id(), 'discord_id' => $entity->discordId()] + $this->payload(CatalogCsvSection::Ranks, $entity);
        }
        if ($entity instanceof CatalogTemplateRank) {
            return $state + ['id' => $entity->id(), 'role_key' => $entity->roleKey()] + $this->payload(CatalogCsvSection::Ranks, $entity);
        }

        $section = match (true) {
            $entity instanceof RoleStat, $entity instanceof CatalogTemplateRoleStat => CatalogCsvSection::RoleStats,
            $entity instanceof CharacterRole, $entity instanceof CatalogTemplateRole => CatalogCsvSection::Roles,
            $entity instanceof Stat, $entity instanceof CatalogTemplateStat => CatalogCsvSection::Stats,
            $entity instanceof Element, $entity instanceof CatalogTemplateElement => CatalogCsvSection::Elements,
            $entity instanceof WelcomeMessage, $entity instanceof CatalogTemplateWelcomeMessage => CatalogCsvSection::WelcomeMessages,
            $entity instanceof ByeMessage, $entity instanceof CatalogTemplateByeMessage => CatalogCsvSection::ByeMessages,
            default => throw new \LogicException('Unsupported CSV catalogue entity.'),
        };

        return $state + $this->payload($section, $entity);
    }

    /**
     * @return array{line: ?int, column: ?string, message: string, value: ?string}
     */
    private function error(string $message, ?string $value = null, ?int $line = null, ?string $column = null): array
    {
        return ['line' => $line, 'column' => $column, 'message' => $message, 'value' => $value];
    }

    /**
     * @param array<int, string> $discordRoleIdsByLine
     */
    private function applyRanks(
        DiscordServer|CatalogTemplate $target,
        CatalogCsvPreview $preview,
        array $discordRoleIdsByLine,
    ): void {
        $ranks = $this->entities($target, CatalogCsvSection::Ranks);
        $byKey = $this->byNaturalKey(CatalogCsvSection::Ranks, $ranks);
        foreach ($preview->operations() as $operation) {
            if ('unchanged' === $operation['action']) {
                continue;
            }
            if ('create' === $operation['action']) {
                $rank = $this->createRank($target, $operation['incoming'], $operation['line'], $discordRoleIdsByLine, $ranks);
                $this->entityManager->persist($rank);
                $ranks[] = $rank;
                $byKey[$operation['key']] = $rank;
                continue;
            }
            $this->updateRank($byKey[$operation['key']], $operation['incoming']);
        }
    }

    private function applyStats(DiscordServer|CatalogTemplate $target, CatalogCsvPreview $preview): void
    {
        $byKey = $this->byNaturalKey(CatalogCsvSection::Stats, $this->entities($target, CatalogCsvSection::Stats));
        foreach ($preview->operations() as $operation) {
            if ('create' === $operation['action']) {
                $entity = $target instanceof DiscordServer
                    ? new Stat($target, (string) $operation['incoming']['nom'])
                    : new CatalogTemplateStat($target, (string) $operation['incoming']['nom']);
                $this->entityManager->persist($entity);
            } elseif ('update' === $operation['action']) {
                $entity = $byKey[$operation['key']];
                if ($entity instanceof Stat || $entity instanceof CatalogTemplateStat) {
                    $entity->updateName((string) $operation['incoming']['nom']);
                }
            }
        }
    }

    private function applyRoles(DiscordServer|CatalogTemplate $target, CatalogCsvPreview $preview): void
    {
        $byKey = $this->byNaturalKey(CatalogCsvSection::Roles, $this->entities($target, CatalogCsvSection::Roles));
        foreach ($preview->operations() as $operation) {
            $values = $operation['incoming'];
            if ('create' === $operation['action']) {
                $entity = $target instanceof DiscordServer
                    ? new CharacterRole($target, (string) $values['nom'], (int) $values['pourcentage'], emojiUnicode: (string) $values['emoji'])
                    : new CatalogTemplateRole($target, (string) $values['nom'], (int) $values['pourcentage'], emojiUnicode: (string) $values['emoji']);
                $this->entityManager->persist($entity);
            } elseif ('update' === $operation['action']) {
                $entity = $byKey[$operation['key']];
                if ($entity instanceof CharacterRole || $entity instanceof CatalogTemplateRole) {
                    $entity->updateConfiguration(
                        (string) $values['nom'],
                        (int) $values['pourcentage'],
                        'unicode',
                        (string) $values['emoji'],
                        null,
                        null,
                        false,
                    );
                }
            }
        }
    }

    private function applyElements(DiscordServer|CatalogTemplate $target, CatalogCsvPreview $preview): void
    {
        $byKey = $this->byNaturalKey(CatalogCsvSection::Elements, $this->entities($target, CatalogCsvSection::Elements));
        foreach ($preview->operations() as $operation) {
            $values = $operation['incoming'];
            if ('create' === $operation['action']) {
                $entity = $target instanceof DiscordServer
                    ? new Element($target, (string) $values['nom'], emojiUnicode: (string) $values['emoji'])
                    : new CatalogTemplateElement($target, (string) $values['nom'], emojiUnicode: (string) $values['emoji']);
                $this->entityManager->persist($entity);
            } elseif ('update' === $operation['action']) {
                $entity = $byKey[$operation['key']];
                if ($entity instanceof Element || $entity instanceof CatalogTemplateElement) {
                    $entity->updateConfiguration((string) $values['nom'], 'unicode', (string) $values['emoji'], null, null, false);
                }
            }
        }
    }

    private function applyRoleStats(DiscordServer|CatalogTemplate $target, CatalogCsvPreview $preview): void
    {
        $roles = $this->byNaturalKey(CatalogCsvSection::Roles, $this->entities($target, CatalogCsvSection::Roles));
        $stats = $this->byNaturalKey(CatalogCsvSection::Stats, $this->entities($target, CatalogCsvSection::Stats));
        $byKey = $this->byNaturalKey(CatalogCsvSection::RoleStats, $this->entities($target, CatalogCsvSection::RoleStats));
        foreach ($preview->operations() as $operation) {
            $values = $operation['incoming'];
            $role = $roles[CatalogCsvSection::Roles->naturalKey(['nom' => $values['role']])];
            $stat = $stats[CatalogCsvSection::Stats->naturalKey(['nom' => $values['stat']])];
            if ('create' === $operation['action']) {
                $entity = $role instanceof CharacterRole && $stat instanceof Stat
                    ? new RoleStat($role, $stat, (int) $values['pourcentage'])
                    : new CatalogTemplateRoleStat($role, $stat, (int) $values['pourcentage']);
                $this->entityManager->persist($entity);
            } elseif ('update' === $operation['action']) {
                $entity = $byKey[$operation['key']];
                if ($entity instanceof RoleStat || $entity instanceof CatalogTemplateRoleStat) {
                    $entity->updatePercentage((int) $values['pourcentage']);
                }
            }
        }
    }

    private function applyMessages(
        DiscordServer|CatalogTemplate $target,
        CatalogCsvSection $section,
        CatalogCsvPreview $preview,
    ): void {
        $ranks = $this->byNaturalKey(CatalogCsvSection::Ranks, $this->entities($target, CatalogCsvSection::Ranks));
        foreach ($preview->operations() as $operation) {
            if ('create' !== $operation['action']) {
                continue;
            }
            $values = $operation['incoming'];
            $rank = $ranks[CatalogCsvSection::Ranks->naturalKey(['nom' => $values['rang']])];
            $message = (string) $values['message'];
            $entity = match (true) {
                CatalogCsvSection::WelcomeMessages === $section && $target instanceof DiscordServer && $rank instanceof Rank => new WelcomeMessage($target, $rank, $message),
                CatalogCsvSection::ByeMessages === $section && $target instanceof DiscordServer && $rank instanceof Rank => new ByeMessage($target, $rank, $message),
                CatalogCsvSection::WelcomeMessages === $section && $target instanceof CatalogTemplate && $rank instanceof CatalogTemplateRank => new CatalogTemplateWelcomeMessage($target, $rank, $message),
                CatalogCsvSection::ByeMessages === $section && $target instanceof CatalogTemplate && $rank instanceof CatalogTemplateRank => new CatalogTemplateByeMessage($target, $rank, $message),
                default => throw new \LogicException('Unsupported CSV message target.'),
            };
            $this->entityManager->persist($entity);
        }
    }

    private function name(object $entity): string
    {
        return match (true) {
            $entity instanceof Rank,
            $entity instanceof CatalogTemplateRank,
            $entity instanceof CharacterRole,
            $entity instanceof CatalogTemplateRole,
            $entity instanceof Stat,
            $entity instanceof CatalogTemplateStat,
            $entity instanceof Element,
            $entity instanceof CatalogTemplateElement => $entity->name(),
            default => throw new \LogicException('Entity has no catalogue name.'),
        };
    }

    private function percentage(object $entity): int
    {
        return match (true) {
            $entity instanceof Rank,
            $entity instanceof CatalogTemplateRank,
            $entity instanceof CharacterRole,
            $entity instanceof CatalogTemplateRole,
            $entity instanceof RoleStat,
            $entity instanceof CatalogTemplateRoleStat => $entity->percentage(),
            default => throw new \LogicException('Entity has no catalogue percentage.'),
        };
    }

    private function byeTitle(object $entity): ?string
    {
        return match (true) {
            $entity instanceof Rank, $entity instanceof CatalogTemplateRank => $entity->byeTitle(),
            default => throw new \LogicException('Entity has no departure title.'),
        };
    }

    private function isStaff(object $entity): bool
    {
        return match (true) {
            $entity instanceof Rank, $entity instanceof CatalogTemplateRank => $entity->isStaff(),
            default => throw new \LogicException('Entity has no staff flag.'),
        };
    }

    private function rankName(object $entity): string
    {
        return match (true) {
            $entity instanceof RoleStat,
            $entity instanceof CatalogTemplateRoleStat,
            $entity instanceof WelcomeMessage,
            $entity instanceof ByeMessage,
            $entity instanceof CatalogTemplateWelcomeMessage,
            $entity instanceof CatalogTemplateByeMessage => $entity->rank()->name(),
            default => throw new \LogicException('Entity has no catalogue rank.'),
        };
    }

    private function statName(object $entity): string
    {
        return match (true) {
            $entity instanceof RoleStat, $entity instanceof CatalogTemplateRoleStat => $entity->stat()->name(),
            default => throw new \LogicException('Entity has no catalogue stat.'),
        };
    }

    private function roleName(object $entity): string
    {
        return match (true) {
            $entity instanceof RoleStat, $entity instanceof CatalogTemplateRoleStat => $entity->role()->name(),
            default => throw new \LogicException('Entity has no catalogue role.'),
        };
    }

    private function message(object $entity): string
    {
        return match (true) {
            $entity instanceof WelcomeMessage,
            $entity instanceof ByeMessage,
            $entity instanceof CatalogTemplateWelcomeMessage,
            $entity instanceof CatalogTemplateByeMessage => $entity->message(),
            default => throw new \LogicException('Entity has no catalogue message.'),
        };
    }

    private function emoji(object $entity, string $default): string
    {
        return match (true) {
            $entity instanceof CharacterRole,
            $entity instanceof CatalogTemplateRole,
            $entity instanceof Element,
            $entity instanceof CatalogTemplateElement => 'unicode' === $entity->emojiSource()
                ? ($entity->emojiUnicode() ?? $default)
                : $entity->emojiMarkup(),
            default => throw new \LogicException('Entity has no catalogue emoji.'),
        };
    }
}
