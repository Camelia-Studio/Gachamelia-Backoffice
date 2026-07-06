<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260706181953 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE discord_server_members (id BIGINT AUTO_INCREMENT NOT NULL, owner TINYINT DEFAULT 0 NOT NULL, permissions VARCHAR(64) DEFAULT \'0\' NOT NULL, can_manage_configuration TINYINT DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, user_id BIGINT NOT NULL, server_id BIGINT NOT NULL, INDEX IDX_B7D11FE5A76ED395 (user_id), INDEX IDX_B7D11FE51844E6B7 (server_id), UNIQUE INDEX uniq_discord_server_members_user_server (user_id, server_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE discord_users (id BIGINT AUTO_INCREMENT NOT NULL, discord_id VARCHAR(32) NOT NULL, username VARCHAR(255) NOT NULL, global_name VARCHAR(255) DEFAULT NULL, avatar VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX uniq_discord_users_discord_id (discord_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE discord_server_members ADD CONSTRAINT FK_B7D11FE5A76ED395 FOREIGN KEY (user_id) REFERENCES discord_users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE discord_server_members ADD CONSTRAINT FK_B7D11FE51844E6B7 FOREIGN KEY (server_id) REFERENCES discord_servers (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE discord_server_members DROP FOREIGN KEY FK_B7D11FE5A76ED395');
        $this->addSql('ALTER TABLE discord_server_members DROP FOREIGN KEY FK_B7D11FE51844E6B7');
        $this->addSql('DROP TABLE discord_server_members');
        $this->addSql('DROP TABLE discord_users');
    }
}
