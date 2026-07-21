<?php

declare(strict_types=1);

namespace App\Tests\Doctrine;

use App\Tests\Support\DatabaseResetter;
use Doctrine\DBAL\Exception;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class MultiServerDatabaseInvariantTest extends KernelTestCase
{
    use DatabaseResetter;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->resetDatabase();
    }

    public function testUserCatalogAssignmentsCannotCrossServerBoundaries(): void
    {
        [$firstServerId, $secondServerId] = $this->seedServers();
        $rankId = $this->seedRank($secondServerId, 'rank-other', 'Autre rang');
        $roleId = $this->seedRole($secondServerId, 'Autre rôle');

        $this->assertDatabaseRejects(
            fn () => $this->connection()->insert('users', [
                'server_id' => $firstServerId,
                'discord_id' => 'user-rank-cross-scope',
                'rank_id' => $rankId,
                'role_id' => null,
                'created_at' => '2026-07-22 10:00:00',
                'updated_at' => '2026-07-22 10:00:00',
            ]),
            'users_rank_scope',
        );

        $userId = $this->seedUser($firstServerId, 'user-role-cross-scope');
        $this->assertDatabaseRejects(
            fn () => $this->connection()->update('users', ['role_id' => $roleId], ['id' => $userId]),
            'users_role_scope',
        );
    }

    public function testRuntimeRelationsCannotMixServerCatalogs(): void
    {
        [$firstServerId, $secondServerId] = $this->seedServers();
        $rankId = $this->seedRank($firstServerId, 'rank-first', 'Premier rang');
        $statId = $this->seedStat($secondServerId, 'Stat externe');
        $userId = $this->seedUser($firstServerId, 'runtime-user');
        $elementId = $this->seedElement($secondServerId, 'Élément externe');

        $this->assertDatabaseRejects(
            fn () => $this->connection()->insert('rank_stats', ['server_id' => $firstServerId, 'rank_id' => $rankId, 'stat_id' => $statId, 'percentage' => 50]),
            'fk_rank_stats_stat_scope',
        );
        $this->assertDatabaseRejects(
            fn () => $this->connection()->insert('user_stats', ['server_id' => $firstServerId, 'user_id' => $userId, 'stat_id' => $statId, 'value' => 1]),
            'fk_user_stats_stat_scope',
        );
        $this->assertDatabaseRejects(
            fn () => $this->connection()->insert('users_elements', ['server_id' => $firstServerId, 'user_id' => $userId, 'element_id' => $elementId]),
            'fk_users_elements_element_scope',
        );
    }

    public function testMessagesCannotReferenceARankFromAnotherServer(): void
    {
        [$firstServerId, $secondServerId] = $this->seedServers();
        $rankId = $this->seedRank($secondServerId, 'rank-other', 'Autre rang');

        foreach (['welcome_messages', 'bye_messages'] as $table) {
            $this->assertDatabaseRejects(
                fn () => $this->connection()->insert($table, [
                    'server_id' => $firstServerId,
                    'rank_id' => $rankId,
                    'message' => 'Message invalide.',
                ]),
                'fk_'.str_replace('_messages', '_messages_rank_scope', $table),
            );
        }
    }

    public function testTemplateRelationsCannotCrossTemplateBoundaries(): void
    {
        [$firstTemplateId, $secondTemplateId] = $this->seedTemplates();
        $rankId = $this->seedTemplateRank($firstTemplateId, 'rank-first', 'Premier rang');
        $otherRankId = $this->seedTemplateRank($secondTemplateId, 'rank-other', 'Autre rang');
        $statId = $this->seedTemplateStat($secondTemplateId, 'Stat externe');

        $this->assertDatabaseRejects(
            fn () => $this->connection()->insert('catalog_template_rank_stats', [
                'template_id' => $firstTemplateId,
                'rank_id' => $rankId,
                'stat_id' => $statId,
                'percentage' => 50,
            ]),
            'fk_template_rank_stats_stat_scope',
        );

        foreach (['catalog_template_welcome_messages', 'catalog_template_bye_messages'] as $table) {
            $this->assertDatabaseRejects(
                fn () => $this->connection()->insert($table, [
                    'template_id' => $firstTemplateId,
                    'rank_id' => $otherRankId,
                    'message' => 'Message invalide.',
                ]),
                str_contains($table, 'welcome') ? 'fk_template_welcome_rank_scope' : 'fk_template_bye_rank_scope',
            );
        }
    }

    public function testPercentagesMustRemainBetweenZeroAndOneHundred(): void
    {
        [$serverId] = $this->seedServers();
        [$templateId] = $this->seedTemplates();

        $this->assertDatabaseRejects(
            fn () => $this->seedRank($serverId, 'rank-invalid', 'Rang invalide', 101),
            'chk_ranks_percentage',
        );
        $this->assertDatabaseRejects(
            fn () => $this->seedRole($serverId, 'Rôle invalide', -1),
            'chk_roles_percentage',
        );
        $this->assertDatabaseRejects(
            fn () => $this->seedTemplateRank($templateId, 'rank-invalid', 'Rang invalide', 101),
            'chk_catalog_template_ranks_percentage',
        );
        $this->assertDatabaseRejects(
            fn () => $this->seedTemplateRole($templateId, 'Rôle invalide', -1),
            'chk_catalog_template_roles_percentage',
        );
    }

    public function testOnlyOneStaffRankCanExistPerServerAndTemplate(): void
    {
        [$serverId] = $this->seedServers();
        [$templateId] = $this->seedTemplates();

        $this->seedRank($serverId, 'staff-first', 'Premier staff', 0, true);
        $this->assertDatabaseRejects(
            fn () => $this->seedRank($serverId, 'staff-second', 'Second staff', 0, true),
            'ranks_staff_scope',
        );

        $this->seedTemplateRank($templateId, 'staff-first', 'Premier staff', 0, true);
        $this->assertDatabaseRejects(
            fn () => $this->seedTemplateRank($templateId, 'staff-second', 'Second staff', 0, true),
            'catalog_template_ranks_staff_scope',
        );
    }

    /**
     * @return array{int, int}
     */
    private function seedServers(): array
    {
        $this->connection()->insert('discord_servers', [
            'discord_id' => 'server-first',
            'name' => 'Premier serveur',
            'icon' => null,
            'created_at' => '2026-07-22 10:00:00',
            'updated_at' => '2026-07-22 10:00:00',
        ]);
        $firstServerId = (int) $this->connection()->lastInsertId();

        $this->connection()->insert('discord_servers', [
            'discord_id' => 'server-second',
            'name' => 'Second serveur',
            'icon' => null,
            'created_at' => '2026-07-22 10:00:00',
            'updated_at' => '2026-07-22 10:00:00',
        ]);

        return [$firstServerId, (int) $this->connection()->lastInsertId()];
    }

    /**
     * @return array{int, int}
     */
    private function seedTemplates(): array
    {
        $this->connection()->insert('catalog_templates', [
            'name' => 'Premier modèle',
            'description' => null,
            'published' => 0,
            'created_by_id' => null,
            'created_at' => '2026-07-22 10:00:00',
            'updated_at' => '2026-07-22 10:00:00',
        ]);
        $firstTemplateId = (int) $this->connection()->lastInsertId();

        $this->connection()->insert('catalog_templates', [
            'name' => 'Second modèle',
            'description' => null,
            'published' => 0,
            'created_by_id' => null,
            'created_at' => '2026-07-22 10:00:00',
            'updated_at' => '2026-07-22 10:00:00',
        ]);

        return [$firstTemplateId, (int) $this->connection()->lastInsertId()];
    }

    private function seedRank(int $serverId, string $discordId, string $name, int $percentage = 50, bool $staff = false): int
    {
        $this->connection()->insert('ranks', [
            'server_id' => $serverId,
            'discord_id' => $discordId,
            'name' => $name,
            'percentage' => $percentage,
            'bye_title' => null,
            'is_staff' => $staff ? 1 : 0,
            'staff_scope_id' => $staff ? $serverId : null,
            'created_at' => '2026-07-22 10:00:00',
            'updated_at' => '2026-07-22 10:00:00',
        ]);

        return (int) $this->connection()->lastInsertId();
    }

    private function seedRole(int $serverId, string $name, int $percentage = 50): int
    {
        $this->connection()->insert('roles', [
            'server_id' => $serverId,
            'name' => $name,
            'percentage' => $percentage,
            'emoji_source' => 'unicode',
            'emoji_unicode' => '🎭',
            'emoji_id' => null,
            'emoji_name' => null,
            'emoji_animated' => 0,
        ]);

        return (int) $this->connection()->lastInsertId();
    }

    private function seedStat(int $serverId, string $name): int
    {
        $this->connection()->insert('stats', ['server_id' => $serverId, 'name' => $name]);

        return (int) $this->connection()->lastInsertId();
    }

    private function seedElement(int $serverId, string $name): int
    {
        $this->connection()->insert('elements', [
            'server_id' => $serverId,
            'name' => $name,
            'emoji_source' => 'unicode',
            'emoji_unicode' => '✨',
            'emoji_id' => null,
            'emoji_name' => null,
            'emoji_animated' => 0,
        ]);

        return (int) $this->connection()->lastInsertId();
    }

    private function seedUser(int $serverId, string $discordId): int
    {
        $this->connection()->insert('users', [
            'server_id' => $serverId,
            'discord_id' => $discordId,
            'rank_id' => null,
            'role_id' => null,
            'created_at' => '2026-07-22 10:00:00',
            'updated_at' => '2026-07-22 10:00:00',
        ]);

        return (int) $this->connection()->lastInsertId();
    }

    private function seedTemplateRank(int $templateId, string $roleKey, string $name, int $percentage = 50, bool $staff = false): int
    {
        $this->connection()->insert('catalog_template_ranks', [
            'template_id' => $templateId,
            'role_key' => $roleKey,
            'name' => $name,
            'percentage' => $percentage,
            'bye_title' => null,
            'is_staff' => $staff ? 1 : 0,
            'staff_scope_id' => $staff ? $templateId : null,
        ]);

        return (int) $this->connection()->lastInsertId();
    }

    private function seedTemplateStat(int $templateId, string $name): int
    {
        $this->connection()->insert('catalog_template_stats', ['template_id' => $templateId, 'name' => $name]);

        return (int) $this->connection()->lastInsertId();
    }

    private function seedTemplateRole(int $templateId, string $name, int $percentage): int
    {
        $this->connection()->insert('catalog_template_roles', [
            'template_id' => $templateId,
            'name' => $name,
            'percentage' => $percentage,
            'emoji_source' => 'unicode',
            'emoji_unicode' => '🎭',
            'emoji_id' => null,
            'emoji_name' => null,
            'emoji_animated' => 0,
        ]);

        return (int) $this->connection()->lastInsertId();
    }

    private function assertDatabaseRejects(callable $operation, string $constraint): void
    {
        try {
            $operation();
            self::fail(sprintf('Database accepted an invalid write guarded by %s.', $constraint));
        } catch (Exception $exception) {
            self::assertStringContainsString($constraint, $exception->getMessage());
        }
    }
}
