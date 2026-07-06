<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260706175117 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE bye_messages (id BIGINT AUTO_INCREMENT NOT NULL, message VARCHAR(255) NOT NULL, server_id BIGINT NOT NULL, rank_id BIGINT NOT NULL, INDEX IDX_1ED6D8E21844E6B7 (server_id), INDEX IDX_1ED6D8E27616678F (rank_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE discord_servers (id BIGINT AUTO_INCREMENT NOT NULL, discord_id VARCHAR(32) NOT NULL, name VARCHAR(255) NOT NULL, icon VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX uniq_discord_servers_discord_id (discord_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE elements (id BIGINT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, server_id BIGINT NOT NULL, INDEX IDX_444A075D1844E6B7 (server_id), UNIQUE INDEX uniq_elements_server_name (server_id, name), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE rank_stats (percentage INT NOT NULL, rank_id BIGINT NOT NULL, stat_id BIGINT NOT NULL, INDEX IDX_7A4328377616678F (rank_id), INDEX IDX_7A4328379502F0B (stat_id), PRIMARY KEY (rank_id, stat_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE ranks (id BIGINT AUTO_INCREMENT NOT NULL, discord_id VARCHAR(32) NOT NULL, name VARCHAR(255) NOT NULL, percentage INT NOT NULL, bye_title VARCHAR(255) DEFAULT NULL, is_staff TINYINT DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, server_id BIGINT NOT NULL, INDEX IDX_CBE6A0141844E6B7 (server_id), UNIQUE INDEX uniq_ranks_server_discord_id (server_id, discord_id), UNIQUE INDEX uniq_ranks_server_name (server_id, name), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE roles (id BIGINT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, percentage INT NOT NULL, image_url VARCHAR(255) DEFAULT \'https://placehold.co/400\' NOT NULL, server_id BIGINT NOT NULL, INDEX IDX_B63E2EC71844E6B7 (server_id), UNIQUE INDEX uniq_roles_server_name (server_id, name), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE stats (id BIGINT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, server_id BIGINT NOT NULL, INDEX IDX_574767AA1844E6B7 (server_id), UNIQUE INDEX uniq_stats_server_name (server_id, name), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE user_stats (value INT DEFAULT 0 NOT NULL, user_id BIGINT NOT NULL, stat_id BIGINT NOT NULL, INDEX IDX_B5859CF2A76ED395 (user_id), INDEX IDX_B5859CF29502F0B (stat_id), PRIMARY KEY (user_id, stat_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE users (id BIGINT AUTO_INCREMENT NOT NULL, discord_id VARCHAR(32) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, server_id BIGINT NOT NULL, rank_id BIGINT DEFAULT NULL, role_id BIGINT DEFAULT NULL, INDEX IDX_1483A5E91844E6B7 (server_id), INDEX IDX_1483A5E97616678F (rank_id), INDEX IDX_1483A5E9D60322AC (role_id), UNIQUE INDEX uniq_users_server_discord_id (server_id, discord_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE users_elements (user_id BIGINT NOT NULL, element_id BIGINT NOT NULL, INDEX IDX_3F0B2C87A76ED395 (user_id), INDEX IDX_3F0B2C871F1F2A24 (element_id), PRIMARY KEY (user_id, element_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE welcome_messages (id BIGINT AUTO_INCREMENT NOT NULL, message VARCHAR(255) NOT NULL, server_id BIGINT NOT NULL, rank_id BIGINT NOT NULL, INDEX IDX_4F0FFBEF1844E6B7 (server_id), INDEX IDX_4F0FFBEF7616678F (rank_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE bye_messages ADD CONSTRAINT FK_1ED6D8E21844E6B7 FOREIGN KEY (server_id) REFERENCES discord_servers (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE bye_messages ADD CONSTRAINT FK_1ED6D8E27616678F FOREIGN KEY (rank_id) REFERENCES ranks (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE elements ADD CONSTRAINT FK_444A075D1844E6B7 FOREIGN KEY (server_id) REFERENCES discord_servers (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE rank_stats ADD CONSTRAINT FK_7A4328377616678F FOREIGN KEY (rank_id) REFERENCES ranks (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE rank_stats ADD CONSTRAINT FK_7A4328379502F0B FOREIGN KEY (stat_id) REFERENCES stats (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE ranks ADD CONSTRAINT FK_CBE6A0141844E6B7 FOREIGN KEY (server_id) REFERENCES discord_servers (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE roles ADD CONSTRAINT FK_B63E2EC71844E6B7 FOREIGN KEY (server_id) REFERENCES discord_servers (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE stats ADD CONSTRAINT FK_574767AA1844E6B7 FOREIGN KEY (server_id) REFERENCES discord_servers (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_stats ADD CONSTRAINT FK_B5859CF2A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_stats ADD CONSTRAINT FK_B5859CF29502F0B FOREIGN KEY (stat_id) REFERENCES stats (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE users ADD CONSTRAINT FK_1483A5E91844E6B7 FOREIGN KEY (server_id) REFERENCES discord_servers (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE users ADD CONSTRAINT FK_1483A5E97616678F FOREIGN KEY (rank_id) REFERENCES ranks (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE users ADD CONSTRAINT FK_1483A5E9D60322AC FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE users_elements ADD CONSTRAINT FK_3F0B2C87A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE users_elements ADD CONSTRAINT FK_3F0B2C871F1F2A24 FOREIGN KEY (element_id) REFERENCES elements (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE welcome_messages ADD CONSTRAINT FK_4F0FFBEF1844E6B7 FOREIGN KEY (server_id) REFERENCES discord_servers (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE welcome_messages ADD CONSTRAINT FK_4F0FFBEF7616678F FOREIGN KEY (rank_id) REFERENCES ranks (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE bye_messages DROP FOREIGN KEY FK_1ED6D8E21844E6B7');
        $this->addSql('ALTER TABLE bye_messages DROP FOREIGN KEY FK_1ED6D8E27616678F');
        $this->addSql('ALTER TABLE elements DROP FOREIGN KEY FK_444A075D1844E6B7');
        $this->addSql('ALTER TABLE rank_stats DROP FOREIGN KEY FK_7A4328377616678F');
        $this->addSql('ALTER TABLE rank_stats DROP FOREIGN KEY FK_7A4328379502F0B');
        $this->addSql('ALTER TABLE ranks DROP FOREIGN KEY FK_CBE6A0141844E6B7');
        $this->addSql('ALTER TABLE roles DROP FOREIGN KEY FK_B63E2EC71844E6B7');
        $this->addSql('ALTER TABLE stats DROP FOREIGN KEY FK_574767AA1844E6B7');
        $this->addSql('ALTER TABLE user_stats DROP FOREIGN KEY FK_B5859CF2A76ED395');
        $this->addSql('ALTER TABLE user_stats DROP FOREIGN KEY FK_B5859CF29502F0B');
        $this->addSql('ALTER TABLE users DROP FOREIGN KEY FK_1483A5E91844E6B7');
        $this->addSql('ALTER TABLE users DROP FOREIGN KEY FK_1483A5E97616678F');
        $this->addSql('ALTER TABLE users DROP FOREIGN KEY FK_1483A5E9D60322AC');
        $this->addSql('ALTER TABLE users_elements DROP FOREIGN KEY FK_3F0B2C87A76ED395');
        $this->addSql('ALTER TABLE users_elements DROP FOREIGN KEY FK_3F0B2C871F1F2A24');
        $this->addSql('ALTER TABLE welcome_messages DROP FOREIGN KEY FK_4F0FFBEF1844E6B7');
        $this->addSql('ALTER TABLE welcome_messages DROP FOREIGN KEY FK_4F0FFBEF7616678F');
        $this->addSql('DROP TABLE bye_messages');
        $this->addSql('DROP TABLE discord_servers');
        $this->addSql('DROP TABLE elements');
        $this->addSql('DROP TABLE rank_stats');
        $this->addSql('DROP TABLE ranks');
        $this->addSql('DROP TABLE roles');
        $this->addSql('DROP TABLE stats');
        $this->addSql('DROP TABLE user_stats');
        $this->addSql('DROP TABLE users');
        $this->addSql('DROP TABLE users_elements');
        $this->addSql('DROP TABLE welcome_messages');
    }
}
