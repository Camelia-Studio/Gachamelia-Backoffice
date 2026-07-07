<?php

namespace App\Tests\Support;

use Doctrine\DBAL\Connection;

trait DatabaseResetter
{
    private function resetDatabase(): void
    {
        $connection = $this->connection();
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS=0');

        foreach ([
            'catalog_template_bye_messages',
            'catalog_template_welcome_messages',
            'catalog_template_rank_stats',
            'catalog_template_elements',
            'catalog_template_stats',
            'catalog_template_roles',
            'catalog_template_ranks',
            'catalog_templates',
            'discord_server_members',
            'discord_users',
            'bye_messages',
            'welcome_messages',
            'users_elements',
            'user_stats',
            'rank_stats',
            'users',
            'elements',
            'stats',
            'roles',
            'ranks',
            'discord_emojis',
            'discord_servers',
        ] as $table) {
            if ($this->tableExists($table)) {
                $connection->executeStatement('TRUNCATE TABLE '.$table);
            }
        }

        $connection->executeStatement('SET FOREIGN_KEY_CHECKS=1');
    }

    private function tableExists(string $table): bool
    {
        return false !== $this->connection()->fetchOne('SHOW TABLES LIKE ?', [$table]);
    }

    private function connection(): Connection
    {
        return static::getContainer()->get(Connection::class);
    }
}
