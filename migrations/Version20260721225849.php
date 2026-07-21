<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260721225849 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Track Discord server activity and soft deactivation.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE discord_servers ADD active TINYINT DEFAULT 1 NOT NULL, ADD last_seen_at DATETIME DEFAULT NULL, ADD inactive_at DATETIME DEFAULT NULL');
        $this->addSql('UPDATE discord_servers SET last_seen_at = updated_at WHERE last_seen_at IS NULL');
        $this->addSql('ALTER TABLE discord_servers MODIFY last_seen_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE discord_servers DROP active, DROP last_seen_at, DROP inactive_at');
    }
}
