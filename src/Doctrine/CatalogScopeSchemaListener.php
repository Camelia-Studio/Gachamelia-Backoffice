<?php

declare(strict_types=1);

namespace App\Doctrine;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use Doctrine\ORM\Tools\ToolEvents;

#[AsDoctrineListener(event: ToolEvents::postGenerateSchema)]
final class CatalogScopeSchemaListener
{
    public function postGenerateSchema(GenerateSchemaEventArgs $event): void
    {
        $schema = $event->getSchema();

        $this->addCompositeForeignKey($schema, 'users', ['rank_id', 'server_id'], 'ranks', ['id', 'server_id'], 'fk_users_rank_scope');
        $this->addCompositeForeignKey($schema, 'users', ['role_id', 'server_id'], 'roles', ['id', 'server_id'], 'fk_users_role_scope');
        $this->addCompositeForeignKey($schema, 'rank_stats', ['rank_id', 'server_id'], 'ranks', ['id', 'server_id'], 'fk_rank_stats_rank_scope', true);
        $this->addCompositeForeignKey($schema, 'rank_stats', ['stat_id', 'server_id'], 'stats', ['id', 'server_id'], 'fk_rank_stats_stat_scope', true);
        $this->addCompositeForeignKey($schema, 'user_stats', ['user_id', 'server_id'], 'users', ['id', 'server_id'], 'fk_user_stats_user_scope', true);
        $this->addCompositeForeignKey($schema, 'user_stats', ['stat_id', 'server_id'], 'stats', ['id', 'server_id'], 'fk_user_stats_stat_scope', true);
        $this->addCompositeForeignKey($schema, 'users_elements', ['user_id', 'server_id'], 'users', ['id', 'server_id'], 'fk_users_elements_user_scope', true);
        $this->addCompositeForeignKey($schema, 'users_elements', ['element_id', 'server_id'], 'elements', ['id', 'server_id'], 'fk_users_elements_element_scope', true);
        $this->addCompositeForeignKey($schema, 'welcome_messages', ['rank_id', 'server_id'], 'ranks', ['id', 'server_id'], 'fk_welcome_messages_rank_scope', true);
        $this->addCompositeForeignKey($schema, 'bye_messages', ['rank_id', 'server_id'], 'ranks', ['id', 'server_id'], 'fk_bye_messages_rank_scope', true);

        $this->addCompositeForeignKey($schema, 'catalog_template_rank_stats', ['rank_id', 'template_id'], 'catalog_template_ranks', ['id', 'template_id'], 'fk_template_rank_stats_rank_scope', true);
        $this->addCompositeForeignKey($schema, 'catalog_template_rank_stats', ['stat_id', 'template_id'], 'catalog_template_stats', ['id', 'template_id'], 'fk_template_rank_stats_stat_scope', true);
        $this->addCompositeForeignKey($schema, 'catalog_template_welcome_messages', ['rank_id', 'template_id'], 'catalog_template_ranks', ['id', 'template_id'], 'fk_template_welcome_rank_scope', true);
        $this->addCompositeForeignKey($schema, 'catalog_template_bye_messages', ['rank_id', 'template_id'], 'catalog_template_ranks', ['id', 'template_id'], 'fk_template_bye_rank_scope', true);
    }

    /**
     * @param list<string> $localColumns
     * @param list<string> $foreignColumns
     */
    private function addCompositeForeignKey(
        Schema $schema,
        string $tableName,
        array $localColumns,
        string $foreignTable,
        array $foreignColumns,
        string $name,
        bool $cascade = false,
    ): void {
        $table = $schema->getTable($tableName);
        if ($table->hasForeignKey($name)) {
            return;
        }

        $table->addForeignKeyConstraint(
            $foreignTable,
            $localColumns,
            $foreignColumns,
            $cascade ? ['onDelete' => 'CASCADE'] : [],
            $name,
        );
    }
}
