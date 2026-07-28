<?php

declare(strict_types=1);

namespace App\Tests\Doctrine;

use App\Tests\Support\DatabaseResetter;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Doctrine\Migrations\Exception\AbortMigration;
use DoctrineMigrations\Version20260721231728;
use DoctrineMigrations\Version20260728120000;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class MultiServerMigrationPreflightTest extends KernelTestCase
{
    use DatabaseResetter;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->resetDatabase();
        require_once \dirname(__DIR__, 2).'/migrations/Version20260721231728.php';
        require_once \dirname(__DIR__, 2).'/migrations/Version20260728120000.php';
    }

    public function testInvalidHistoricalDataAbortsBeforeAnyDdlAndCanBeRetried(): void
    {
        $schema = $this->connection()->createSchemaManager()->introspectSchema();
        $this->executeMigration($this->roleStatMigration(), $schema, false);
        $schema = $this->connection()->createSchemaManager()->introspectSchema();
        $this->executeMigration($this->migration(), $schema, false);

        try {
            $this->connection()->insert('discord_servers', [
                'discord_id' => 'guild',
                'name' => 'Serveur',
                'icon' => null,
                'created_at' => '2026-07-27 10:00:00',
                'updated_at' => '2026-07-27 10:00:00',
            ]);
            $serverId = (int) $this->connection()->lastInsertId();
            foreach (['staff-1' => 'Staff un', 'staff-2' => 'Staff deux'] as $discordId => $name) {
                $this->connection()->insert('ranks', [
                    'server_id' => $serverId,
                    'discord_id' => $discordId,
                    'name' => $name,
                    'percentage' => 0,
                    'bye_title' => null,
                    'is_staff' => 1,
                    'created_at' => '2026-07-27 10:00:00',
                    'updated_at' => '2026-07-27 10:00:00',
                ]);
            }

            foreach ([1, 2] as $attempt) {
                $migration = $this->migration();
                try {
                    $migration->up($this->connection()->createSchemaManager()->introspectSchema());
                    self::fail(\sprintf('Migration attempt %d should abort on duplicate staff ranks.', $attempt));
                } catch (AbortMigration $exception) {
                    self::assertStringContainsString('staff rank', $exception->getMessage());
                }

                self::assertSame([], $migration->getSql());
                self::assertFalse($this->columnExists('catalog_template_rank_stats', 'template_id'));
            }

            $this->connection()->delete('ranks', ['discord_id' => 'staff-2']);
            $this->connection()->update('ranks', ['is_staff' => 2], ['discord_id' => 'staff-1']);

            foreach ([1, 2] as $attempt) {
                $migration = $this->migration();
                try {
                    $migration->up($this->connection()->createSchemaManager()->introspectSchema());
                    self::fail(\sprintf('Migration attempt %d should abort on an invalid is_staff value.', $attempt));
                } catch (AbortMigration $exception) {
                    self::assertStringContainsString('is_staff', $exception->getMessage());
                }

                self::assertSame([], $migration->getSql());
                self::assertFalse($this->columnExists('catalog_template_rank_stats', 'template_id'));
            }
        } finally {
            $this->connection()->executeStatement('DELETE FROM ranks');
            $this->connection()->executeStatement('DELETE FROM discord_servers');
            $this->executeMigration(
                $this->migration(),
                $this->connection()->createSchemaManager()->introspectSchema(),
                true,
            );
            $this->executeMigration(
                $this->roleStatMigration(),
                $this->connection()->createSchemaManager()->introspectSchema(),
                true,
            );
        }
    }

    private function migration(): Version20260721231728
    {
        return new Version20260721231728($this->connection(), new NullLogger());
    }

    private function roleStatMigration(): Version20260728120000
    {
        return new Version20260728120000($this->connection(), new NullLogger());
    }

    private function executeMigration(AbstractMigration $migration, Schema $schema, bool $up): void
    {
        if ($up) {
            $migration->up($schema);
        } else {
            $migration->down($schema);
        }

        foreach ($migration->getSql() as $query) {
            $this->connection()->executeStatement($query->getStatement(), $query->getParameters(), $query->getTypes());
        }
    }

    private function columnExists(string $table, string $column): bool
    {
        return false !== $this->connection()->fetchOne(
            \sprintf('SHOW COLUMNS FROM %s LIKE ?', $table),
            [$column],
        );
    }
}
