<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260728120000 extends AbstractMigration
{
    #[\Override]
    public function getDescription(): string
    {
        return 'Move stat percentage distributions from ranks to character roles.';
    }

    #[\Override]
    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            0 < (int) $this->connection->fetchOne(
                'SELECT (SELECT COUNT(*) FROM rank_stats) + (SELECT COUNT(*) FROM catalog_template_rank_stats)',
            ),
            'Cannot move stat distributions to roles while rank-stat associations exist.',
        );

        $this->addSql('DROP TABLE catalog_template_rank_stats');
        $this->addSql('DROP TABLE rank_stats');

        $this->addSql('CREATE UNIQUE INDEX uniq_catalog_template_roles_id_template ON catalog_template_roles (id, template_id)');

        $this->addSql('CREATE TABLE role_stats (percentage INT NOT NULL, server_id BIGINT NOT NULL, role_id BIGINT NOT NULL, stat_id BIGINT NOT NULL, INDEX IDX_A09B8F851844E6B7 (server_id), INDEX IDX_A09B8F85D60322AC (role_id), INDEX IDX_A09B8F859502F0B (stat_id), INDEX IDX_A09B8F85D60322AC1844E6B7 (role_id, server_id), INDEX IDX_A09B8F859502F0B1844E6B7 (stat_id, server_id), PRIMARY KEY (role_id, stat_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE role_stats ADD CONSTRAINT FK_ROLE_STATS_SERVER FOREIGN KEY (server_id) REFERENCES discord_servers (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE role_stats ADD CONSTRAINT FK_ROLE_STATS_ROLE FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE role_stats ADD CONSTRAINT FK_ROLE_STATS_STAT FOREIGN KEY (stat_id) REFERENCES stats (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE role_stats ADD CONSTRAINT fk_role_stats_role_scope FOREIGN KEY (role_id, server_id) REFERENCES roles (id, server_id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE role_stats ADD CONSTRAINT fk_role_stats_stat_scope FOREIGN KEY (stat_id, server_id) REFERENCES stats (id, server_id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE role_stats ADD CONSTRAINT chk_role_stats_percentage CHECK (percentage BETWEEN 0 AND 100)');

        $this->addSql('CREATE TABLE catalog_template_role_stats (percentage INT NOT NULL, template_id BIGINT NOT NULL, role_id BIGINT NOT NULL, stat_id BIGINT NOT NULL, INDEX IDX_8886E28D5DA0FB8 (template_id), INDEX IDX_8886E28DD60322AC (role_id), INDEX IDX_8886E28D9502F0B (stat_id), INDEX IDX_8886E28DD60322AC5DA0FB8 (role_id, template_id), INDEX IDX_8886E28D9502F0B5DA0FB8 (stat_id, template_id), PRIMARY KEY (role_id, stat_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE catalog_template_role_stats ADD CONSTRAINT FK_TEMPLATE_ROLE_STATS_TEMPLATE FOREIGN KEY (template_id) REFERENCES catalog_templates (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE catalog_template_role_stats ADD CONSTRAINT FK_TEMPLATE_ROLE_STATS_ROLE FOREIGN KEY (role_id) REFERENCES catalog_template_roles (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE catalog_template_role_stats ADD CONSTRAINT FK_TEMPLATE_ROLE_STATS_STAT FOREIGN KEY (stat_id) REFERENCES catalog_template_stats (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE catalog_template_role_stats ADD CONSTRAINT fk_template_role_stats_role_scope FOREIGN KEY (role_id, template_id) REFERENCES catalog_template_roles (id, template_id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE catalog_template_role_stats ADD CONSTRAINT fk_template_role_stats_stat_scope FOREIGN KEY (stat_id, template_id) REFERENCES catalog_template_stats (id, template_id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE catalog_template_role_stats ADD CONSTRAINT chk_catalog_template_role_stats_percentage CHECK (percentage BETWEEN 0 AND 100)');
    }

    #[\Override]
    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE catalog_template_role_stats');
        $this->addSql('DROP TABLE role_stats');
        $this->addSql('DROP INDEX uniq_catalog_template_roles_id_template ON catalog_template_roles');

        $this->addSql('CREATE TABLE rank_stats (percentage INT NOT NULL, server_id BIGINT NOT NULL, rank_id BIGINT NOT NULL, stat_id BIGINT NOT NULL, INDEX IDX_7A4328371844E6B7 (server_id), INDEX IDX_7A4328377616678F (rank_id), INDEX IDX_7A4328379502F0B (stat_id), INDEX IDX_7A4328377616678F1844E6B7 (rank_id, server_id), INDEX IDX_7A4328379502F0B1844E6B7 (stat_id, server_id), PRIMARY KEY (rank_id, stat_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE rank_stats ADD CONSTRAINT FK_7A4328371844E6B7 FOREIGN KEY (server_id) REFERENCES discord_servers (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE rank_stats ADD CONSTRAINT FK_7A4328377616678F FOREIGN KEY (rank_id) REFERENCES ranks (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE rank_stats ADD CONSTRAINT FK_7A4328379502F0B FOREIGN KEY (stat_id) REFERENCES stats (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE rank_stats ADD CONSTRAINT fk_rank_stats_rank_scope FOREIGN KEY (rank_id, server_id) REFERENCES ranks (id, server_id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE rank_stats ADD CONSTRAINT fk_rank_stats_stat_scope FOREIGN KEY (stat_id, server_id) REFERENCES stats (id, server_id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE rank_stats ADD CONSTRAINT chk_rank_stats_percentage CHECK (percentage BETWEEN 0 AND 100)');

        $this->addSql('CREATE TABLE catalog_template_rank_stats (percentage INT NOT NULL, template_id BIGINT NOT NULL, rank_id BIGINT NOT NULL, stat_id BIGINT NOT NULL, INDEX IDX_525E453F5DA0FB8 (template_id), INDEX IDX_525E453F7616678F (rank_id), INDEX IDX_525E453F9502F0B (stat_id), INDEX IDX_525E453F7616678F5DA0FB8 (rank_id, template_id), INDEX IDX_525E453F9502F0B5DA0FB8 (stat_id, template_id), PRIMARY KEY (rank_id, stat_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE catalog_template_rank_stats ADD CONSTRAINT FK_525E453F5DA0FB8 FOREIGN KEY (template_id) REFERENCES catalog_templates (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE catalog_template_rank_stats ADD CONSTRAINT FK_525E453F7616678F FOREIGN KEY (rank_id) REFERENCES catalog_template_ranks (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE catalog_template_rank_stats ADD CONSTRAINT FK_525E453F9502F0B FOREIGN KEY (stat_id) REFERENCES catalog_template_stats (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE catalog_template_rank_stats ADD CONSTRAINT fk_template_rank_stats_rank_scope FOREIGN KEY (rank_id, template_id) REFERENCES catalog_template_ranks (id, template_id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE catalog_template_rank_stats ADD CONSTRAINT fk_template_rank_stats_stat_scope FOREIGN KEY (stat_id, template_id) REFERENCES catalog_template_stats (id, template_id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE catalog_template_rank_stats ADD CONSTRAINT chk_catalog_template_rank_stats_percentage CHECK (percentage BETWEEN 0 AND 100)');
    }
}
