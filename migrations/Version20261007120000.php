<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261007120000 extends AbstractMigration
{
    #[\Override]
    public function getDescription(): string
    {
        return 'Add per-server progression settings and user XP state.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE discord_servers ADD progression_settings JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE users ADD progression_xp BIGINT NOT NULL DEFAULT 0, ADD total_xp BIGINT NOT NULL DEFAULT 0, ADD constellations INT NOT NULL DEFAULT 0');
    }

    #[\Override]
    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users DROP progression_xp, DROP total_xp, DROP constellations');
        $this->addSql('ALTER TABLE discord_servers DROP progression_settings');
    }
}
