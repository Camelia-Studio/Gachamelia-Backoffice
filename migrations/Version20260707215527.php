<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260707215527 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add global backoffice roles on Discord users.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE discord_users ADD global_roles JSON DEFAULT NULL');
        $this->addSql("UPDATE discord_users SET global_roles = '[]' WHERE global_roles IS NULL");
        $this->addSql('ALTER TABLE discord_users CHANGE global_roles global_roles JSON NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE discord_users DROP global_roles');
    }
}
