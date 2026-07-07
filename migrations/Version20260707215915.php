<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260707215915 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add global catalog template tables.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE catalog_template_bye_messages (id BIGINT AUTO_INCREMENT NOT NULL, message VARCHAR(255) NOT NULL, template_id BIGINT NOT NULL, rank_id BIGINT NOT NULL, INDEX IDX_E50C67125DA0FB8 (template_id), INDEX IDX_E50C67127616678F (rank_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE catalog_template_elements (id BIGINT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, emoji_source VARCHAR(16) DEFAULT \'unicode\' NOT NULL, emoji_unicode VARCHAR(64) DEFAULT NULL, emoji_id VARCHAR(32) DEFAULT NULL, emoji_name VARCHAR(255) DEFAULT NULL, emoji_animated TINYINT DEFAULT 0 NOT NULL, template_id BIGINT NOT NULL, INDEX IDX_4B5F518E5DA0FB8 (template_id), UNIQUE INDEX uniq_catalog_template_elements_name (template_id, name), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE catalog_template_rank_stats (percentage INT NOT NULL, rank_id BIGINT NOT NULL, stat_id BIGINT NOT NULL, INDEX IDX_525E453F7616678F (rank_id), INDEX IDX_525E453F9502F0B (stat_id), PRIMARY KEY (rank_id, stat_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE catalog_template_ranks (id BIGINT AUTO_INCREMENT NOT NULL, role_key VARCHAR(255) NOT NULL, name VARCHAR(255) NOT NULL, percentage INT NOT NULL, bye_title VARCHAR(255) DEFAULT NULL, is_staff TINYINT DEFAULT 0 NOT NULL, template_id BIGINT NOT NULL, INDEX IDX_6B4561D65DA0FB8 (template_id), UNIQUE INDEX uniq_catalog_template_ranks_role_key (template_id, role_key), UNIQUE INDEX uniq_catalog_template_ranks_name (template_id, name), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE catalog_template_roles (id BIGINT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, percentage INT NOT NULL, emoji_source VARCHAR(16) DEFAULT \'unicode\' NOT NULL, emoji_unicode VARCHAR(64) DEFAULT NULL, emoji_id VARCHAR(32) DEFAULT NULL, emoji_name VARCHAR(255) DEFAULT NULL, emoji_animated TINYINT DEFAULT 0 NOT NULL, template_id BIGINT NOT NULL, INDEX IDX_169DEF055DA0FB8 (template_id), UNIQUE INDEX uniq_catalog_template_roles_name (template_id, name), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE catalog_template_stats (id BIGINT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, template_id BIGINT NOT NULL, INDEX IDX_F7E4A6685DA0FB8 (template_id), UNIQUE INDEX uniq_catalog_template_stats_name (template_id, name), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE catalog_template_welcome_messages (id BIGINT AUTO_INCREMENT NOT NULL, message VARCHAR(255) NOT NULL, template_id BIGINT NOT NULL, rank_id BIGINT NOT NULL, INDEX IDX_56D352645DA0FB8 (template_id), INDEX IDX_56D352647616678F (rank_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE catalog_templates (id BIGINT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, published TINYINT DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, created_by_id BIGINT DEFAULT NULL, INDEX IDX_95159733B03A8386 (created_by_id), UNIQUE INDEX uniq_catalog_templates_name (name), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE catalog_template_bye_messages ADD CONSTRAINT FK_E50C67125DA0FB8 FOREIGN KEY (template_id) REFERENCES catalog_templates (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE catalog_template_bye_messages ADD CONSTRAINT FK_E50C67127616678F FOREIGN KEY (rank_id) REFERENCES catalog_template_ranks (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE catalog_template_elements ADD CONSTRAINT FK_4B5F518E5DA0FB8 FOREIGN KEY (template_id) REFERENCES catalog_templates (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE catalog_template_rank_stats ADD CONSTRAINT FK_525E453F7616678F FOREIGN KEY (rank_id) REFERENCES catalog_template_ranks (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE catalog_template_rank_stats ADD CONSTRAINT FK_525E453F9502F0B FOREIGN KEY (stat_id) REFERENCES catalog_template_stats (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE catalog_template_ranks ADD CONSTRAINT FK_6B4561D65DA0FB8 FOREIGN KEY (template_id) REFERENCES catalog_templates (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE catalog_template_roles ADD CONSTRAINT FK_169DEF055DA0FB8 FOREIGN KEY (template_id) REFERENCES catalog_templates (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE catalog_template_stats ADD CONSTRAINT FK_F7E4A6685DA0FB8 FOREIGN KEY (template_id) REFERENCES catalog_templates (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE catalog_template_welcome_messages ADD CONSTRAINT FK_56D352645DA0FB8 FOREIGN KEY (template_id) REFERENCES catalog_templates (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE catalog_template_welcome_messages ADD CONSTRAINT FK_56D352647616678F FOREIGN KEY (rank_id) REFERENCES catalog_template_ranks (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE catalog_templates ADD CONSTRAINT FK_95159733B03A8386 FOREIGN KEY (created_by_id) REFERENCES discord_users (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE catalog_template_bye_messages DROP FOREIGN KEY FK_E50C67125DA0FB8');
        $this->addSql('ALTER TABLE catalog_template_bye_messages DROP FOREIGN KEY FK_E50C67127616678F');
        $this->addSql('ALTER TABLE catalog_template_elements DROP FOREIGN KEY FK_4B5F518E5DA0FB8');
        $this->addSql('ALTER TABLE catalog_template_rank_stats DROP FOREIGN KEY FK_525E453F7616678F');
        $this->addSql('ALTER TABLE catalog_template_rank_stats DROP FOREIGN KEY FK_525E453F9502F0B');
        $this->addSql('ALTER TABLE catalog_template_ranks DROP FOREIGN KEY FK_6B4561D65DA0FB8');
        $this->addSql('ALTER TABLE catalog_template_roles DROP FOREIGN KEY FK_169DEF055DA0FB8');
        $this->addSql('ALTER TABLE catalog_template_stats DROP FOREIGN KEY FK_F7E4A6685DA0FB8');
        $this->addSql('ALTER TABLE catalog_template_welcome_messages DROP FOREIGN KEY FK_56D352645DA0FB8');
        $this->addSql('ALTER TABLE catalog_template_welcome_messages DROP FOREIGN KEY FK_56D352647616678F');
        $this->addSql('ALTER TABLE catalog_templates DROP FOREIGN KEY FK_95159733B03A8386');
        $this->addSql('DROP TABLE catalog_template_bye_messages');
        $this->addSql('DROP TABLE catalog_template_elements');
        $this->addSql('DROP TABLE catalog_template_rank_stats');
        $this->addSql('DROP TABLE catalog_template_ranks');
        $this->addSql('DROP TABLE catalog_template_roles');
        $this->addSql('DROP TABLE catalog_template_stats');
        $this->addSql('DROP TABLE catalog_template_welcome_messages');
        $this->addSql('DROP TABLE catalog_templates');
    }
}
