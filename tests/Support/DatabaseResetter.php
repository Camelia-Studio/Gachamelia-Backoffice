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
