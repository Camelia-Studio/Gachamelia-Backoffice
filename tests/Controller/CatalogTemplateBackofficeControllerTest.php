<?php

namespace App\Tests\Controller;

use App\Discord\DiscordGuildResourcesProviderInterface;
use App\Entity\DiscordUser;
use App\Tests\Support\DatabaseResetter;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;

final class CatalogTemplateBackofficeControllerTest extends WebTestCase
{
    use DatabaseResetter;

    public function testDashboardLinksCatalogTemplatesForGlobalTemplateAdminsOnly(): void
    {
        $adminClient = static::createClient();
        $this->resetDatabase();
        $this->seedBackofficeAccess($adminClient, [DiscordUser::GLOBAL_ROLE_TEMPLATE_ADMIN]);

        $adminClient->request('GET', '/app');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-testid="catalog-template-admin-link"][href="/app/modeles-catalogue"]');

        static::ensureKernelShutdown();
        $memberClient = static::createClient();
        $this->resetDatabase();
        $this->seedBackofficeAccess($memberClient);

        $memberClient->request('GET', '/app');

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('[data-testid="catalog-template-admin-link"]');
    }

    public function testGlobalTemplateAdminCanCreateAndConfigureCatalogTemplate(): void
    {
        $client = static::createClient();
        $this->resetDatabase();
        $this->seedBackofficeAccess($client, [DiscordUser::GLOBAL_ROLE_TEMPLATE_ADMIN]);

        $client->request('GET', '/app/modeles-catalogue');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Modèles de catalogue');
        self::assertSelectorExists('form[action="/app/modeles-catalogue"] input[name="name"]');

        $client->request('POST', '/app/modeles-catalogue', [
            'name' => 'Starter officiel',
            'description' => 'Catalogue global prêt à importer.',
        ]);

        $templateId = (int) $this->connection()->fetchOne('SELECT id FROM catalog_templates WHERE name = ?', ['Starter officiel']);

        self::assertResponseRedirects('/app/modeles-catalogue/'.$templateId.'/configuration');

        $client->request('GET', '/app/modeles-catalogue/'.$templateId.'/configuration');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Configuration du modèle');
        self::assertSelectorExists('[data-testid="template-configuration-overview-card-ranks"] a[href="/app/modeles-catalogue/'.$templateId.'/configuration/ranks"]');
        self::assertSelectorExists('[data-testid="template-configuration-overview-card-rank-stats"]');
        self::assertSelectorExists('[data-testid="template-configuration-overview-card-welcome-messages"]');
        self::assertSelectorExists('[data-testid="template-configuration-overview-card-bye-messages"]');

        $client->request('POST', '/app/modeles-catalogue/'.$templateId.'/catalogue/ranks', [
            'role_key' => 'Comète',
            'name' => 'Comète de l’Aube',
            'percentage' => '35',
            'bye_title' => 'Comète filante',
            'is_staff' => '1',
        ]);

        self::assertResponseRedirects('/app/modeles-catalogue/'.$templateId.'/configuration/ranks');
        $rankId = (int) $this->connection()->fetchOne('SELECT id FROM catalog_template_ranks WHERE template_id = ?', [$templateId]);

        $client->request('POST', '/app/modeles-catalogue/'.$templateId.'/catalogue/stats', [
            'name' => 'Éther',
        ]);

        self::assertResponseRedirects('/app/modeles-catalogue/'.$templateId.'/configuration/stats');
        $statId = (int) $this->connection()->fetchOne('SELECT id FROM catalog_template_stats WHERE template_id = ?', [$templateId]);

        $client->request('POST', '/app/modeles-catalogue/'.$templateId.'/catalogue/rank-stats', [
            'rank_id' => (string) $rankId,
            'stat_id' => (string) $statId,
            'percentage' => '80',
        ]);

        self::assertResponseRedirects('/app/modeles-catalogue/'.$templateId.'/configuration/rank-stats');

        $client->request('POST', '/app/modeles-catalogue/'.$templateId.'/catalogue/roles', [
            'name' => 'Gardien',
            'percentage' => '45',
            'emoji_source' => 'unicode',
            'emoji_value' => '🛡️',
        ]);

        self::assertResponseRedirects('/app/modeles-catalogue/'.$templateId.'/configuration/roles');

        $client->request('POST', '/app/modeles-catalogue/'.$templateId.'/catalogue/elements', [
            'name' => 'Ambre',
            'emoji_source' => 'unicode',
            'emoji_value' => '🟠',
        ]);

        self::assertResponseRedirects('/app/modeles-catalogue/'.$templateId.'/configuration/elements');

        $client->request('POST', '/app/modeles-catalogue/'.$templateId.'/catalogue/welcome-messages', [
            'rank_id' => (string) $rankId,
            'message' => 'Bienvenue, {user}.',
        ]);

        self::assertResponseRedirects('/app/modeles-catalogue/'.$templateId.'/configuration/welcome-messages');

        $client->request('POST', '/app/modeles-catalogue/'.$templateId.'/catalogue/bye-messages', [
            'rank_id' => (string) $rankId,
            'message' => 'Au revoir, {user}.',
        ]);

        self::assertResponseRedirects('/app/modeles-catalogue/'.$templateId.'/configuration/bye-messages');

        self::assertSame('Comète de l’Aube', $this->connection()->fetchOne('SELECT name FROM catalog_template_ranks WHERE id = ?', [$rankId]));
        self::assertSame(80, (int) $this->connection()->fetchOne('SELECT percentage FROM catalog_template_rank_stats WHERE rank_id = ? AND stat_id = ?', [$rankId, $statId]));
        self::assertSame('Gardien', $this->connection()->fetchOne('SELECT name FROM catalog_template_roles WHERE template_id = ?', [$templateId]));
        self::assertSame('Ambre', $this->connection()->fetchOne('SELECT name FROM catalog_template_elements WHERE template_id = ?', [$templateId]));
        self::assertSame('Bienvenue, {user}.', $this->connection()->fetchOne('SELECT message FROM catalog_template_welcome_messages WHERE rank_id = ?', [$rankId]));
        self::assertSame('Au revoir, {user}.', $this->connection()->fetchOne('SELECT message FROM catalog_template_bye_messages WHERE rank_id = ?', [$rankId]));
    }

    public function testServerAdminCanImportPublishedTemplateWithDiscordRoleMapping(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->resetDatabase();
        $this->seedBackofficeAccess($client, [DiscordUser::GLOBAL_ROLE_TEMPLATE_ADMIN]);
        static::getContainer()->set(DiscordGuildResourcesProviderInterface::class, new CatalogTemplateFakeDiscordGuildResourcesProvider(
            [],
            [
                ['id' => '777777777777777777', 'name' => 'Comète', 'label' => '@Comète', 'position' => 9, 'managed' => false],
                ['id' => '888888888888888888', 'name' => 'Staff', 'label' => '@Staff', 'position' => 8, 'managed' => false],
            ],
        ));

        $serverId = $this->serverDatabaseId('admin');
        $this->connection()->insert('ranks', [
            'server_id' => $serverId,
            'discord_id' => 'old-rank',
            'name' => 'Ancien rang',
            'percentage' => 100,
            'bye_title' => null,
            'is_staff' => 0,
            'created_at' => '2026-07-07 10:00:00',
            'updated_at' => '2026-07-07 10:00:00',
        ]);

        $templateId = $this->seedPublishedTemplate();
        $templateRankId = (int) $this->connection()->fetchOne('SELECT id FROM catalog_template_ranks WHERE template_id = ?', [$templateId]);

        $client->request('GET', '/app/serveurs/admin/configuration/importer/'.$templateId);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('[data-testid="template-import-panel"]', 'écrasera le catalogue actuel');
        self::assertSelectorExists('form[action="/app/serveurs/admin/configuration/importer/'.$templateId.'"] select[name="rank_roles['.$templateRankId.']"] option[value="777777777777777777"]');

        $client->request('POST', '/app/serveurs/admin/configuration/importer/'.$templateId, [
            'rank_roles' => [
                (string) $templateRankId => '777777777777777777',
            ],
        ]);

        self::assertResponseRedirects('/app/serveurs/admin/configuration');
        self::assertSame(0, (int) $this->connection()->fetchOne('SELECT COUNT(*) FROM ranks WHERE name = ?', ['Ancien rang']));
        self::assertSame([
            'discord_id' => '777777777777777777',
            'name' => 'Comète de l’Aube',
            'percentage' => 35,
            'is_staff' => 1,
        ], $this->connection()->fetchAssociative('SELECT discord_id, name, percentage, is_staff FROM ranks WHERE server_id = ?', [$serverId]));
        self::assertSame('Gardien', $this->connection()->fetchOne('SELECT name FROM roles WHERE server_id = ?', [$serverId]));
        self::assertSame('Éther', $this->connection()->fetchOne('SELECT name FROM stats WHERE server_id = ?', [$serverId]));
        self::assertSame('Ambre', $this->connection()->fetchOne('SELECT name FROM elements WHERE server_id = ?', [$serverId]));
        self::assertSame('Bienvenue, {user}.', $this->connection()->fetchOne('SELECT message FROM welcome_messages WHERE server_id = ?', [$serverId]));
        self::assertSame('Au revoir, {user}.', $this->connection()->fetchOne('SELECT message FROM bye_messages WHERE server_id = ?', [$serverId]));
    }

    public function testNonGlobalAdminCannotManageCatalogTemplates(): void
    {
        $client = static::createClient();
        $this->resetDatabase();
        $this->seedBackofficeAccess($client);

        $client->request('GET', '/app/modeles-catalogue');

        self::assertResponseStatusCodeSame(403);
    }

    /**
     * @param list<string> $globalRoles
     */
    private function seedBackofficeAccess(KernelBrowser $client, array $globalRoles = []): void
    {
        $adminServerId = $this->seedKnownDiscordServer('admin', 'Serveur Admin', 'static-icon-hash');
        $memberServerId = $this->seedKnownDiscordServer('member', 'Serveur Membre', null);

        $this->connection()->insert('discord_users', [
            'discord_id' => '42',
            'username' => 'melaine',
            'global_name' => 'Melaine',
            'avatar' => null,
            'global_roles' => json_encode($globalRoles, JSON_THROW_ON_ERROR),
            'created_at' => '2026-07-07 10:00:00',
            'updated_at' => '2026-07-07 10:00:00',
        ]);
        $userId = (int) $this->connection()->lastInsertId();

        $this->connection()->insert('discord_server_members', [
            'user_id' => $userId,
            'server_id' => $adminServerId,
            'owner' => 0,
            'permissions' => '8',
            'can_manage_configuration' => 1,
            'created_at' => '2026-07-07 10:00:00',
            'updated_at' => '2026-07-07 10:00:00',
        ]);
        $this->connection()->insert('discord_server_members', [
            'user_id' => $userId,
            'server_id' => $memberServerId,
            'owner' => 0,
            'permissions' => '0',
            'can_manage_configuration' => 0,
            'created_at' => '2026-07-07 10:00:00',
            'updated_at' => '2026-07-07 10:00:00',
        ]);

        $session = static::getContainer()->get('session.factory')->createSession();
        $session->set('gachamelia.discord_user_id', $userId);
        $session->save();

        $client->getCookieJar()->set(new Cookie($session->getName(), $session->getId()));
    }

    private function seedKnownDiscordServer(string $discordId, string $name, ?string $icon): int
    {
        $this->connection()->insert('discord_servers', [
            'discord_id' => $discordId,
            'name' => $name,
            'icon' => $icon,
            'created_at' => '2026-07-07 10:00:00',
            'updated_at' => '2026-07-07 10:00:00',
        ]);

        return (int) $this->connection()->lastInsertId();
    }

    private function seedPublishedTemplate(): int
    {
        $this->connection()->insert('catalog_templates', [
            'name' => 'Starter officiel',
            'description' => 'Catalogue global prêt à importer.',
            'published' => 1,
            'created_by_id' => null,
            'created_at' => '2026-07-07 10:00:00',
            'updated_at' => '2026-07-07 10:00:00',
        ]);
        $templateId = (int) $this->connection()->lastInsertId();

        $this->connection()->insert('catalog_template_ranks', [
            'template_id' => $templateId,
            'role_key' => 'Comète',
            'name' => 'Comète de l’Aube',
            'percentage' => 35,
            'bye_title' => 'Comète filante',
            'is_staff' => 1,
        ]);
        $rankId = (int) $this->connection()->lastInsertId();

        $this->connection()->insert('catalog_template_stats', [
            'template_id' => $templateId,
            'name' => 'Éther',
        ]);
        $statId = (int) $this->connection()->lastInsertId();

        $this->connection()->insert('catalog_template_rank_stats', [
            'rank_id' => $rankId,
            'stat_id' => $statId,
            'percentage' => 80,
        ]);
        $this->connection()->insert('catalog_template_roles', [
            'template_id' => $templateId,
            'name' => 'Gardien',
            'percentage' => 45,
            'emoji_source' => 'unicode',
            'emoji_unicode' => '🛡️',
            'emoji_id' => null,
            'emoji_name' => null,
            'emoji_animated' => 0,
        ]);
        $this->connection()->insert('catalog_template_elements', [
            'template_id' => $templateId,
            'name' => 'Ambre',
            'emoji_source' => 'unicode',
            'emoji_unicode' => '🟠',
            'emoji_id' => null,
            'emoji_name' => null,
            'emoji_animated' => 0,
        ]);
        $this->connection()->insert('catalog_template_welcome_messages', [
            'template_id' => $templateId,
            'rank_id' => $rankId,
            'message' => 'Bienvenue, {user}.',
        ]);
        $this->connection()->insert('catalog_template_bye_messages', [
            'template_id' => $templateId,
            'rank_id' => $rankId,
            'message' => 'Au revoir, {user}.',
        ]);

        return $templateId;
    }

    private function serverDatabaseId(string $discordId): int
    {
        $serverId = $this->connection()->fetchOne('SELECT id FROM discord_servers WHERE discord_id = ?', [$discordId]);

        self::assertIsNumeric($serverId);

        return (int) $serverId;
    }
}

final readonly class CatalogTemplateFakeDiscordGuildResourcesProvider implements DiscordGuildResourcesProviderInterface
{
    /**
     * @param list<array{id: string, name: string, label: string, type: int}> $channels
     * @param list<array{id: string, name: string, label: string, position: int, managed: bool}> $roles
     */
    public function __construct(
        private array $channels,
        private array $roles,
    ) {
    }

    public function resourcesForGuild(string $guildId): array
    {
        return [
            'channels' => $this->channels,
            'roles' => $this->roles,
        ];
    }
}
