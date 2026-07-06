<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260706233500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add Discord server runtime settings.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE discord_servers ADD welcome_channel_id VARCHAR(32) DEFAULT NULL, ADD bye_channel_id VARCHAR(32) DEFAULT NULL, ADD staff_role_id VARCHAR(32) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE discord_servers DROP welcome_channel_id, DROP bye_channel_id, DROP staff_role_id');
    }
}
