<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260721231728 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Enforce catalog percentages, staff uniqueness, and multi-tenant relation scopes.';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        foreach ([
            'ALTER TABLE catalog_template_rank_stats ADD template_id BIGINT DEFAULT NULL',
            'ALTER TABLE rank_stats ADD server_id BIGINT DEFAULT NULL',
            'ALTER TABLE user_stats ADD server_id BIGINT DEFAULT NULL',
            'ALTER TABLE users_elements ADD server_id BIGINT DEFAULT NULL',
            'ALTER TABLE catalog_template_ranks ADD staff_scope_id BIGINT DEFAULT NULL',
            'ALTER TABLE ranks ADD staff_scope_id BIGINT DEFAULT NULL',
            'UPDATE catalog_template_rank_stats relation INNER JOIN catalog_template_ranks parent ON parent.id = relation.rank_id SET relation.template_id = parent.template_id',
            'UPDATE rank_stats relation INNER JOIN ranks parent ON parent.id = relation.rank_id SET relation.server_id = parent.server_id',
            'UPDATE user_stats relation INNER JOIN users parent ON parent.id = relation.user_id SET relation.server_id = parent.server_id',
            'UPDATE users_elements relation INNER JOIN users parent ON parent.id = relation.user_id SET relation.server_id = parent.server_id',
            'UPDATE catalog_template_ranks SET staff_scope_id = CASE WHEN is_staff = 1 THEN template_id ELSE NULL END',
            'UPDATE ranks SET staff_scope_id = CASE WHEN is_staff = 1 THEN server_id ELSE NULL END',
            'ALTER TABLE catalog_template_rank_stats MODIFY template_id BIGINT NOT NULL',
            'ALTER TABLE rank_stats MODIFY server_id BIGINT NOT NULL',
            'ALTER TABLE user_stats MODIFY server_id BIGINT NOT NULL',
            'ALTER TABLE users_elements MODIFY server_id BIGINT NOT NULL',
        ] as $sql) {
            $this->addSql($sql);
        }

        foreach ([
            'CREATE UNIQUE INDEX uniq_catalog_template_ranks_id_template ON catalog_template_ranks (id, template_id)',
            'CREATE UNIQUE INDEX uniq_catalog_template_ranks_staff_scope ON catalog_template_ranks (staff_scope_id)',
            'CREATE UNIQUE INDEX uniq_catalog_template_stats_id_template ON catalog_template_stats (id, template_id)',
            'CREATE UNIQUE INDEX uniq_elements_id_server ON elements (id, server_id)',
            'CREATE UNIQUE INDEX uniq_ranks_id_server ON ranks (id, server_id)',
            'CREATE UNIQUE INDEX uniq_ranks_staff_scope ON ranks (staff_scope_id)',
            'CREATE UNIQUE INDEX uniq_roles_id_server ON roles (id, server_id)',
            'CREATE UNIQUE INDEX uniq_stats_id_server ON stats (id, server_id)',
            'CREATE UNIQUE INDEX uniq_users_id_server ON users (id, server_id)',
            'CREATE INDEX IDX_1ED6D8E27616678F1844E6B7 ON bye_messages (rank_id, server_id)',
            'CREATE INDEX IDX_E50C67127616678F5DA0FB8 ON catalog_template_bye_messages (rank_id, template_id)',
            'CREATE INDEX IDX_525E453F5DA0FB8 ON catalog_template_rank_stats (template_id)',
            'CREATE INDEX IDX_525E453F7616678F5DA0FB8 ON catalog_template_rank_stats (rank_id, template_id)',
            'CREATE INDEX IDX_525E453F9502F0B5DA0FB8 ON catalog_template_rank_stats (stat_id, template_id)',
            'CREATE INDEX IDX_56D352647616678F5DA0FB8 ON catalog_template_welcome_messages (rank_id, template_id)',
            'CREATE INDEX IDX_7A4328371844E6B7 ON rank_stats (server_id)',
            'CREATE INDEX IDX_7A4328377616678F1844E6B7 ON rank_stats (rank_id, server_id)',
            'CREATE INDEX IDX_7A4328379502F0B1844E6B7 ON rank_stats (stat_id, server_id)',
            'CREATE INDEX IDX_B5859CF21844E6B7 ON user_stats (server_id)',
            'CREATE INDEX IDX_B5859CF2A76ED3951844E6B7 ON user_stats (user_id, server_id)',
            'CREATE INDEX IDX_B5859CF29502F0B1844E6B7 ON user_stats (stat_id, server_id)',
            'CREATE INDEX IDX_1483A5E97616678F1844E6B7 ON users (rank_id, server_id)',
            'CREATE INDEX IDX_1483A5E9D60322AC1844E6B7 ON users (role_id, server_id)',
            'CREATE INDEX IDX_3F0B2C871844E6B7 ON users_elements (server_id)',
            'CREATE INDEX IDX_3F0B2C87A76ED3951844E6B7 ON users_elements (user_id, server_id)',
            'CREATE INDEX IDX_3F0B2C871F1F2A241844E6B7 ON users_elements (element_id, server_id)',
            'CREATE INDEX IDX_4F0FFBEF7616678F1844E6B7 ON welcome_messages (rank_id, server_id)',
        ] as $sql) {
            $this->addSql($sql);
        }

        foreach ([
            'ALTER TABLE catalog_template_rank_stats ADD CONSTRAINT FK_525E453F5DA0FB8 FOREIGN KEY (template_id) REFERENCES catalog_templates (id) ON DELETE CASCADE',
            'ALTER TABLE rank_stats ADD CONSTRAINT FK_7A4328371844E6B7 FOREIGN KEY (server_id) REFERENCES discord_servers (id) ON DELETE CASCADE',
            'ALTER TABLE user_stats ADD CONSTRAINT FK_B5859CF21844E6B7 FOREIGN KEY (server_id) REFERENCES discord_servers (id) ON DELETE CASCADE',
            'ALTER TABLE users_elements ADD CONSTRAINT FK_3F0B2C871844E6B7 FOREIGN KEY (server_id) REFERENCES discord_servers (id) ON DELETE CASCADE',
            'ALTER TABLE catalog_template_ranks ADD CONSTRAINT FK_6B4561D64A75F99F FOREIGN KEY (staff_scope_id) REFERENCES catalog_templates (id) ON DELETE CASCADE',
            'ALTER TABLE ranks ADD CONSTRAINT FK_CBE6A0144A75F99F FOREIGN KEY (staff_scope_id) REFERENCES discord_servers (id) ON DELETE CASCADE',
            'ALTER TABLE bye_messages ADD CONSTRAINT fk_bye_messages_rank_scope FOREIGN KEY (rank_id, server_id) REFERENCES ranks (id, server_id) ON DELETE CASCADE',
            'ALTER TABLE catalog_template_bye_messages ADD CONSTRAINT fk_template_bye_rank_scope FOREIGN KEY (rank_id, template_id) REFERENCES catalog_template_ranks (id, template_id) ON DELETE CASCADE',
            'ALTER TABLE catalog_template_rank_stats ADD CONSTRAINT fk_template_rank_stats_rank_scope FOREIGN KEY (rank_id, template_id) REFERENCES catalog_template_ranks (id, template_id) ON DELETE CASCADE',
            'ALTER TABLE catalog_template_rank_stats ADD CONSTRAINT fk_template_rank_stats_stat_scope FOREIGN KEY (stat_id, template_id) REFERENCES catalog_template_stats (id, template_id) ON DELETE CASCADE',
            'ALTER TABLE catalog_template_welcome_messages ADD CONSTRAINT fk_template_welcome_rank_scope FOREIGN KEY (rank_id, template_id) REFERENCES catalog_template_ranks (id, template_id) ON DELETE CASCADE',
            'ALTER TABLE rank_stats ADD CONSTRAINT fk_rank_stats_rank_scope FOREIGN KEY (rank_id, server_id) REFERENCES ranks (id, server_id) ON DELETE CASCADE',
            'ALTER TABLE rank_stats ADD CONSTRAINT fk_rank_stats_stat_scope FOREIGN KEY (stat_id, server_id) REFERENCES stats (id, server_id) ON DELETE CASCADE',
            'ALTER TABLE user_stats ADD CONSTRAINT fk_user_stats_user_scope FOREIGN KEY (user_id, server_id) REFERENCES users (id, server_id) ON DELETE CASCADE',
            'ALTER TABLE user_stats ADD CONSTRAINT fk_user_stats_stat_scope FOREIGN KEY (stat_id, server_id) REFERENCES stats (id, server_id) ON DELETE CASCADE',
            'ALTER TABLE users ADD CONSTRAINT fk_users_rank_scope FOREIGN KEY (rank_id, server_id) REFERENCES ranks (id, server_id)',
            'ALTER TABLE users ADD CONSTRAINT fk_users_role_scope FOREIGN KEY (role_id, server_id) REFERENCES roles (id, server_id)',
            'ALTER TABLE users_elements ADD CONSTRAINT fk_users_elements_user_scope FOREIGN KEY (user_id, server_id) REFERENCES users (id, server_id) ON DELETE CASCADE',
            'ALTER TABLE users_elements ADD CONSTRAINT fk_users_elements_element_scope FOREIGN KEY (element_id, server_id) REFERENCES elements (id, server_id) ON DELETE CASCADE',
            'ALTER TABLE welcome_messages ADD CONSTRAINT fk_welcome_messages_rank_scope FOREIGN KEY (rank_id, server_id) REFERENCES ranks (id, server_id) ON DELETE CASCADE',
        ] as $sql) {
            $this->addSql($sql);
        }

        foreach ([
            'ALTER TABLE ranks ADD CONSTRAINT chk_ranks_percentage CHECK (percentage BETWEEN 0 AND 100)',
            'ALTER TABLE ranks ADD CONSTRAINT chk_ranks_staff_scope CHECK ((is_staff = 0 AND staff_scope_id IS NULL) OR (is_staff = 1 AND staff_scope_id = server_id))',
            'ALTER TABLE roles ADD CONSTRAINT chk_roles_percentage CHECK (percentage BETWEEN 0 AND 100)',
            'ALTER TABLE rank_stats ADD CONSTRAINT chk_rank_stats_percentage CHECK (percentage BETWEEN 0 AND 100)',
            'ALTER TABLE catalog_template_ranks ADD CONSTRAINT chk_catalog_template_ranks_percentage CHECK (percentage BETWEEN 0 AND 100)',
            'ALTER TABLE catalog_template_ranks ADD CONSTRAINT chk_catalog_template_ranks_staff_scope CHECK ((is_staff = 0 AND staff_scope_id IS NULL) OR (is_staff = 1 AND staff_scope_id = template_id))',
            'ALTER TABLE catalog_template_roles ADD CONSTRAINT chk_catalog_template_roles_percentage CHECK (percentage BETWEEN 0 AND 100)',
            'ALTER TABLE catalog_template_rank_stats ADD CONSTRAINT chk_catalog_template_rank_stats_percentage CHECK (percentage BETWEEN 0 AND 100)',
        ] as $sql) {
            $this->addSql($sql);
        }
    }

    public function down(Schema $schema): void
    {
        foreach ([
            'ALTER TABLE bye_messages DROP FOREIGN KEY fk_bye_messages_rank_scope',
            'ALTER TABLE catalog_template_bye_messages DROP FOREIGN KEY fk_template_bye_rank_scope',
            'ALTER TABLE catalog_template_rank_stats DROP FOREIGN KEY fk_template_rank_stats_rank_scope',
            'ALTER TABLE catalog_template_rank_stats DROP FOREIGN KEY fk_template_rank_stats_stat_scope',
            'ALTER TABLE catalog_template_welcome_messages DROP FOREIGN KEY fk_template_welcome_rank_scope',
            'ALTER TABLE rank_stats DROP FOREIGN KEY fk_rank_stats_rank_scope',
            'ALTER TABLE rank_stats DROP FOREIGN KEY fk_rank_stats_stat_scope',
            'ALTER TABLE user_stats DROP FOREIGN KEY fk_user_stats_user_scope',
            'ALTER TABLE user_stats DROP FOREIGN KEY fk_user_stats_stat_scope',
            'ALTER TABLE users DROP FOREIGN KEY fk_users_rank_scope',
            'ALTER TABLE users DROP FOREIGN KEY fk_users_role_scope',
            'ALTER TABLE users_elements DROP FOREIGN KEY fk_users_elements_user_scope',
            'ALTER TABLE users_elements DROP FOREIGN KEY fk_users_elements_element_scope',
            'ALTER TABLE welcome_messages DROP FOREIGN KEY fk_welcome_messages_rank_scope',
            'ALTER TABLE catalog_template_rank_stats DROP FOREIGN KEY FK_525E453F5DA0FB8',
            'ALTER TABLE rank_stats DROP FOREIGN KEY FK_7A4328371844E6B7',
            'ALTER TABLE user_stats DROP FOREIGN KEY FK_B5859CF21844E6B7',
            'ALTER TABLE users_elements DROP FOREIGN KEY FK_3F0B2C871844E6B7',
            'ALTER TABLE catalog_template_ranks DROP FOREIGN KEY FK_6B4561D64A75F99F',
            'ALTER TABLE ranks DROP FOREIGN KEY FK_CBE6A0144A75F99F',
        ] as $sql) {
            $this->addSql($sql);
        }

        foreach ([
            'ALTER TABLE ranks DROP CHECK chk_ranks_percentage',
            'ALTER TABLE ranks DROP CHECK chk_ranks_staff_scope',
            'ALTER TABLE roles DROP CHECK chk_roles_percentage',
            'ALTER TABLE rank_stats DROP CHECK chk_rank_stats_percentage',
            'ALTER TABLE catalog_template_ranks DROP CHECK chk_catalog_template_ranks_percentage',
            'ALTER TABLE catalog_template_ranks DROP CHECK chk_catalog_template_ranks_staff_scope',
            'ALTER TABLE catalog_template_roles DROP CHECK chk_catalog_template_roles_percentage',
            'ALTER TABLE catalog_template_rank_stats DROP CHECK chk_catalog_template_rank_stats_percentage',
        ] as $sql) {
            $this->addSql($sql);
        }

        foreach ([
            'DROP INDEX IDX_1ED6D8E27616678F1844E6B7 ON bye_messages',
            'DROP INDEX IDX_E50C67127616678F5DA0FB8 ON catalog_template_bye_messages',
            'DROP INDEX IDX_525E453F5DA0FB8 ON catalog_template_rank_stats',
            'DROP INDEX IDX_525E453F7616678F5DA0FB8 ON catalog_template_rank_stats',
            'DROP INDEX IDX_525E453F9502F0B5DA0FB8 ON catalog_template_rank_stats',
            'DROP INDEX IDX_56D352647616678F5DA0FB8 ON catalog_template_welcome_messages',
            'DROP INDEX IDX_7A4328371844E6B7 ON rank_stats',
            'DROP INDEX IDX_7A4328377616678F1844E6B7 ON rank_stats',
            'DROP INDEX IDX_7A4328379502F0B1844E6B7 ON rank_stats',
            'DROP INDEX IDX_B5859CF21844E6B7 ON user_stats',
            'DROP INDEX IDX_B5859CF2A76ED3951844E6B7 ON user_stats',
            'DROP INDEX IDX_B5859CF29502F0B1844E6B7 ON user_stats',
            'DROP INDEX IDX_1483A5E97616678F1844E6B7 ON users',
            'DROP INDEX IDX_1483A5E9D60322AC1844E6B7 ON users',
            'DROP INDEX IDX_3F0B2C871844E6B7 ON users_elements',
            'DROP INDEX IDX_3F0B2C87A76ED3951844E6B7 ON users_elements',
            'DROP INDEX IDX_3F0B2C871F1F2A241844E6B7 ON users_elements',
            'DROP INDEX IDX_4F0FFBEF7616678F1844E6B7 ON welcome_messages',
            'DROP INDEX uniq_catalog_template_ranks_id_template ON catalog_template_ranks',
            'DROP INDEX uniq_catalog_template_ranks_staff_scope ON catalog_template_ranks',
            'DROP INDEX uniq_catalog_template_stats_id_template ON catalog_template_stats',
            'DROP INDEX uniq_elements_id_server ON elements',
            'DROP INDEX uniq_ranks_id_server ON ranks',
            'DROP INDEX uniq_ranks_staff_scope ON ranks',
            'DROP INDEX uniq_roles_id_server ON roles',
            'DROP INDEX uniq_stats_id_server ON stats',
            'DROP INDEX uniq_users_id_server ON users',
        ] as $sql) {
            $this->addSql($sql);
        }

        $this->addSql('ALTER TABLE catalog_template_rank_stats DROP template_id');
        $this->addSql('ALTER TABLE rank_stats DROP server_id');
        $this->addSql('ALTER TABLE user_stats DROP server_id');
        $this->addSql('ALTER TABLE users_elements DROP server_id');
        $this->addSql('ALTER TABLE catalog_template_ranks DROP staff_scope_id');
        $this->addSql('ALTER TABLE ranks DROP staff_scope_id');
    }
}
