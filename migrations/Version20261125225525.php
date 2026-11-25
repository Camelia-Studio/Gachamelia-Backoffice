<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261125225525 extends AbstractMigration
{
    #[\Override]
    public function getDescription(): string
    {
        return 'Add the player progression journal: tier changes and daily XP totals.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE progression_events (id BIGINT AUTO_INCREMENT NOT NULL, rank_name VARCHAR(255) NOT NULL, type VARCHAR(16) NOT NULL, tier SMALLINT NOT NULL, previous_value INT NOT NULL, new_value INT NOT NULL, occurred_at DATETIME NOT NULL, server_id BIGINT NOT NULL, rank_id BIGINT DEFAULT NULL, user_id BIGINT NOT NULL, INDEX IDX_3760571844E6B7 (server_id), INDEX IDX_3760577616678F (rank_id), INDEX IDX_376057A76ED395 (user_id), INDEX idx_progression_events_user_occurred (user_id, occurred_at), INDEX IDX_376057A76ED3951844E6B7 (user_id, server_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE user_daily_xp (id BIGINT AUTO_INCREMENT NOT NULL, message_xp BIGINT DEFAULT 0 NOT NULL, voice_xp BIGINT DEFAULT 0 NOT NULL, day DATE NOT NULL, server_id BIGINT NOT NULL, user_id BIGINT NOT NULL, INDEX IDX_49C212FE1844E6B7 (server_id), INDEX IDX_49C212FEA76ED395 (user_id), UNIQUE INDEX uniq_user_daily_xp_user_day (user_id, day), INDEX IDX_49C212FEA76ED3951844E6B7 (user_id, server_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE progression_events ADD CONSTRAINT FK_3760571844E6B7 FOREIGN KEY (server_id) REFERENCES discord_servers (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE progression_events ADD CONSTRAINT FK_3760577616678F FOREIGN KEY (rank_id) REFERENCES ranks (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE progression_events ADD CONSTRAINT FK_376057A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE progression_events ADD CONSTRAINT fk_progression_events_user_scope FOREIGN KEY (user_id, server_id) REFERENCES users (id, server_id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_daily_xp ADD CONSTRAINT FK_49C212FE1844E6B7 FOREIGN KEY (server_id) REFERENCES discord_servers (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_daily_xp ADD CONSTRAINT FK_49C212FEA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_daily_xp ADD CONSTRAINT fk_user_daily_xp_user_scope FOREIGN KEY (user_id, server_id) REFERENCES users (id, server_id) ON DELETE CASCADE');
    }

    #[\Override]
    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE progression_events');
        $this->addSql('DROP TABLE user_daily_xp');
    }
}
