<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260706215225 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Replace role image URLs with Discord-ready emoji metadata on roles and elements.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE elements ADD emoji_source VARCHAR(16) DEFAULT 'unicode' NOT NULL, ADD emoji_unicode VARCHAR(64) DEFAULT NULL, ADD emoji_id VARCHAR(32) DEFAULT NULL, ADD emoji_name VARCHAR(255) DEFAULT NULL, ADD emoji_animated TINYINT DEFAULT 0 NOT NULL");
        $this->addSql("ALTER TABLE roles ADD emoji_source VARCHAR(16) DEFAULT 'unicode' NOT NULL, ADD emoji_unicode VARCHAR(64) DEFAULT NULL, ADD emoji_id VARCHAR(32) DEFAULT NULL, ADD emoji_name VARCHAR(255) DEFAULT NULL, ADD emoji_animated TINYINT DEFAULT 0 NOT NULL, DROP image_url");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE elements DROP emoji_source, DROP emoji_unicode, DROP emoji_id, DROP emoji_name, DROP emoji_animated");
        $this->addSql("ALTER TABLE roles ADD image_url VARCHAR(255) DEFAULT 'https://placehold.co/400' NOT NULL, DROP emoji_source, DROP emoji_unicode, DROP emoji_id, DROP emoji_name, DROP emoji_animated");
    }
}
