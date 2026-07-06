<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260706222000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add Discord emoji cache metadata for the backoffice picker.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE discord_emojis (id BIGINT AUTO_INCREMENT NOT NULL, cache_key VARCHAR(64) NOT NULL, source VARCHAR(16) NOT NULL, discord_id VARCHAR(32) NOT NULL, name VARCHAR(255) NOT NULL, animated TINYINT DEFAULT 0 NOT NULL, available TINYINT DEFAULT 1 NOT NULL, last_seen_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, server_id BIGINT DEFAULT NULL, INDEX IDX_4A66FD111844E6B7 (server_id), INDEX idx_discord_emojis_server_source (server_id, source), INDEX idx_discord_emojis_cache_available (cache_key, source, available), UNIQUE INDEX uniq_discord_emojis_cache_source_id (cache_key, source, discord_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE discord_emojis ADD CONSTRAINT FK_4A66FD111844E6B7 FOREIGN KEY (server_id) REFERENCES discord_servers (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE discord_emojis DROP FOREIGN KEY FK_4A66FD111844E6B7');
        $this->addSql('DROP TABLE discord_emojis');
    }
}
