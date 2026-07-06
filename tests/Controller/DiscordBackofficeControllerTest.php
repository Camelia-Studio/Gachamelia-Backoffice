<?php

namespace App\Tests\Controller;

use App\Discord\DiscordApiClientInterface;
use App\Tests\Support\DatabaseResetter;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;

final class DiscordBackofficeControllerTest extends WebTestCase
{
    use DatabaseResetter;

    public function testBackofficeDashboardRedirectsAnonymousUserToDiscordLogin(): void
    {
        $client = static::createClient();

        $client->request('GET', '/app');

        self::assertResponseRedirects('/connexion/discord');
    }

    public function testDiscordLoginStartsOauthFlowWithGuildScopesAndState(): void
    {
        $client = static::createClient();

        $client->request('GET', '/connexion/discord');

        self::assertResponseRedirects();
        $location = $client->getResponse()->headers->get('Location') ?? '';

        self::assertStringStartsWith('https://discord.com/oauth2/authorize?', $location);
        self::assertStringContainsString('client_id=test-discord-client', $location);
        self::assertStringContainsString('redirect_uri=http%3A%2F%2Flocalhost%2Fconnexion%2Fdiscord%2Fretour', $location);
        self::assertStringContainsString('response_type=code', $location);
        self::assertStringContainsString('scope=identify+guilds', $location);
        self::assertStringContainsString('state=', $location);
    }

    public function testDiscordCallbackRejectsInvalidState(): void
    {
        $client = static::createClient();

        $client->request('GET', '/connexion/discord/retour?code=test-code&state=bad-state');

        self::assertResponseRedirects('/');
    }

    public function testDiscordCallbackPersistsUserMembershipsFromKnownServersWithoutBotTokenCall(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->resetDatabase();
        $this->seedKnownDiscordServer('admin', 'Ancien nom', 'old-icon');
        $this->seedKnownDiscordServer('member', 'Serveur Membre', null);
        $this->seedKnownDiscordServer('known-without-user', 'Serveur Absent', null);

        $fakeDiscordApiClient = new FakeDiscordApiClient();
        static::getContainer()->set(DiscordApiClientInterface::class, $fakeDiscordApiClient);

        $client->request('GET', '/connexion/discord');
        $location = $client->getResponse()->headers->get('Location') ?? '';
        parse_str(parse_url($location, PHP_URL_QUERY) ?: '', $query);

        self::assertIsString($query['state'] ?? null);

        $client->request('GET', '/connexion/discord/retour?code=valid-code&state='.$query['state']);

        self::assertResponseRedirects('/app');

        $user = $this->connection()->fetchAssociative('SELECT discord_id, username, global_name, avatar FROM discord_users WHERE discord_id = ?', ['42']);
        self::assertSame([
            'discord_id' => '42',
            'username' => 'melaine',
            'global_name' => 'Melaine',
            'avatar' => 'avatar-hash',
        ], $user);

        $memberships = $this->connection()->fetchAllAssociative(
            <<<'SQL'
                SELECT ds.discord_id, ds.name, ds.icon, dsm.owner, dsm.permissions, dsm.can_manage_configuration
                FROM discord_server_members dsm
                INNER JOIN discord_servers ds ON ds.id = dsm.server_id
                INNER JOIN discord_users du ON du.id = dsm.user_id
                WHERE du.discord_id = ?
                ORDER BY ds.discord_id
            SQL,
            ['42'],
        );

        self::assertSame([
            [
                'discord_id' => 'admin',
                'name' => 'Serveur Admin',
                'icon' => 'fresh-icon',
                'owner' => 0,
                'permissions' => '8',
                'can_manage_configuration' => 1,
            ],
            [
                'discord_id' => 'member',
                'name' => 'Serveur Membre',
                'icon' => null,
                'owner' => 0,
                'permissions' => '0',
                'can_manage_configuration' => 0,
            ],
        ], $memberships);

        $client->followRedirect();

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('[data-testid="backoffice-dashboard"]', 'Serveur Admin');
        self::assertSelectorTextContains('[data-testid="backoffice-dashboard"]', 'Serveur Membre');
        self::assertSelectorTextNotContains('[data-testid="backoffice-dashboard"]', 'Serveur Sans Bot');
        self::assertSelectorTextNotContains('[data-testid="backoffice-dashboard"]', 'Serveur Absent');
    }

    public function testDashboardListsDatabaseServersAndRoleSpecificLinks(): void
    {
        $client = static::createClient();
        $this->resetDatabase();
        $this->seedPersistentBackofficeAccess($client);

        $crawler = $client->request('GET', '/app');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('[data-testid="backoffice-dashboard"]', 'Serveur Admin');
        self::assertSelectorTextContains('[data-testid="backoffice-dashboard"]', 'Serveur Membre');
        self::assertSelectorExists('[data-testid="guild-admin"] img[alt="Logo Serveur Admin"][src="https://cdn.discordapp.com/icons/admin/static-icon-hash.webp?size=64"]');
        self::assertSelectorExists('[data-testid="guild-member"] [data-testid="guild-icon-fallback"]');
        self::assertSelectorExists('[data-testid="guild-admin"] a[href="/app/serveurs/admin/configuration"]');
        self::assertSelectorExists('[data-testid="guild-admin"] a[href="/app/serveurs/admin/fiche-personnage"]');
        self::assertSelectorNotExists('[data-testid="guild-member"] a[href="/app/serveurs/member/configuration"]');
        self::assertSelectorExists('[data-testid="guild-member"] a[href="/app/serveurs/member/fiche-personnage"]');
        self::assertStringContainsString(
            'cursor-pointer',
            $crawler->filter('form[action="/deconnexion"] button[type="submit"]')->attr('class') ?? '',
        );
    }

    public function testConfigurationRootDisplaysModuleCardsAndRequiresAdministratorAccess(): void
    {
        $client = static::createClient();
        $this->resetDatabase();
        $this->seedPersistentBackofficeAccess($client);

        $crawler = $client->request('GET', '/app/serveurs/admin/configuration');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Configuration du serveur');
        self::assertSelectorTextContains('body', 'Serveur Admin');
        self::assertSelectorExists('[data-testid="server-configuration"] img[alt="Logo Serveur Admin"][src="https://cdn.discordapp.com/icons/admin/static-icon-hash.webp?size=64"]');
        self::assertSelectorExists('[data-testid="configuration-nav-overview"][aria-current="page"]');
        self::assertSelectorExists('[data-testid="configuration-overview"]');
        self::assertStringContainsString('xl:grid-cols-3', $crawler->filter('[data-testid="configuration-overview"] > div')->attr('class') ?? '');
        self::assertStringContainsString('min-h-72', $crawler->filter('[data-testid="configuration-overview-card-ranks"]')->attr('class') ?? '');
        self::assertSelectorExists('[data-testid="configuration-overview-card-ranks"] a[href="/app/serveurs/admin/configuration/ranks"]');
        self::assertSelectorExists('[data-testid="configuration-overview-card-roles"] a[href="/app/serveurs/admin/configuration/roles"]');
        self::assertSelectorExists('[data-testid="configuration-overview-card-stats"] a[href="/app/serveurs/admin/configuration/stats"]');
        self::assertSelectorExists('[data-testid="configuration-overview-card-elements"] a[href="/app/serveurs/admin/configuration/elements"]');
        self::assertSelectorExists('[data-testid="configuration-overview-card-rank-stats"] a[href="/app/serveurs/admin/configuration/rank-stats"]');
        self::assertSelectorExists('[data-testid="configuration-overview-card-welcome-messages"] a[href="/app/serveurs/admin/configuration/welcome-messages"]');
        self::assertSelectorExists('[data-testid="configuration-overview-card-bye-messages"] a[href="/app/serveurs/admin/configuration/bye-messages"]');

        $client->request('GET', '/app/serveurs/member/configuration');

        self::assertResponseStatusCodeSame(403);
    }

    public function testConfigurationSectionPagesDisplayDedicatedServerCatalogRows(): void
    {
        $client = static::createClient();
        $this->resetDatabase();
        $this->seedPersistentBackofficeAccess($client);

        $adminServerId = $this->serverDatabaseId('admin');
        $otherServerId = $this->serverDatabaseId('unrelated');

        $this->connection()->insert('ranks', [
            'server_id' => $adminServerId,
            'discord_id' => 'rank-admin',
            'name' => 'Novice',
            'percentage' => 30,
            'bye_title' => 'Novice sortant',
            'is_staff' => 0,
            'created_at' => '2026-07-06 10:00:00',
            'updated_at' => '2026-07-06 10:00:00',
        ]);
        $this->connection()->insert('roles', [
            'server_id' => $adminServerId,
            'name' => 'Guerrier',
            'percentage' => 45,
            'emoji_source' => 'server',
            'emoji_id' => '123456789012345678',
            'emoji_name' => 'guerrier',
            'emoji_animated' => 0,
            'emoji_unicode' => null,
        ]);
        $this->connection()->insert('stats', [
            'server_id' => $adminServerId,
            'name' => 'Force',
        ]);
        $this->connection()->insert('elements', [
            'server_id' => $adminServerId,
            'name' => 'Feu',
            'emoji_source' => 'unicode',
            'emoji_unicode' => '🔥',
            'emoji_id' => null,
            'emoji_name' => null,
            'emoji_animated' => 0,
        ]);
        $this->connection()->insert('discord_emojis', [
            'server_id' => $adminServerId,
            'cache_key' => 'server:admin',
            'source' => 'server',
            'discord_id' => '246801357924680135',
            'name' => 'ambre',
            'animated' => 0,
            'available' => 1,
            'last_seen_at' => '2026-07-06 10:00:00',
            'updated_at' => '2026-07-06 10:00:00',
        ]);
        $this->connection()->insert('discord_emojis', [
            'server_id' => null,
            'cache_key' => 'application',
            'source' => 'bot',
            'discord_id' => '135792468013579246',
            'name' => 'gachamelia',
            'animated' => 1,
            'available' => 1,
            'last_seen_at' => '2026-07-06 10:00:00',
            'updated_at' => '2026-07-06 10:00:00',
        ]);
        $this->connection()->insert('stats', [
            'server_id' => $otherServerId,
            'name' => 'Hors serveur',
        ]);

        $client->request('GET', '/app/serveurs/admin/configuration/ranks');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-testid="configuration-nav-overview"][href="/app/serveurs/admin/configuration"]');
        self::assertSelectorExists('[data-testid="configuration-nav-ranks"][aria-current="page"]');
        self::assertSelectorExists('[data-testid="configuration-nav-roles"][href="/app/serveurs/admin/configuration/roles"]');
        self::assertSelectorExists('[data-testid="configuration-nav-stats"][href="/app/serveurs/admin/configuration/stats"]');
        self::assertSelectorExists('[data-testid="configuration-nav-elements"][href="/app/serveurs/admin/configuration/elements"]');
        self::assertSelectorExists('[data-testid="configuration-nav-rank-stats"][href="/app/serveurs/admin/configuration/rank-stats"]');
        self::assertSelectorExists('[data-testid="configuration-nav-welcome-messages"][href="/app/serveurs/admin/configuration/welcome-messages"]');
        self::assertSelectorExists('[data-testid="configuration-nav-bye-messages"][href="/app/serveurs/admin/configuration/bye-messages"]');
        self::assertSelectorExists('[data-testid="catalog-create-panel"]');
        self::assertSelectorExists('[data-testid="catalog-list-panel"]');
        self::assertSelectorTextContains('[data-testid="configuration-panel"]', 'Novice');
        self::assertSelectorTextContains('[data-testid="configuration-panel"]', 'Titre du message de départ');
        self::assertSelectorTextNotContains('[data-testid="configuration-panel"]', 'Titre de départ');
        self::assertSelectorTextNotContains('[data-testid="configuration-panel"]', 'Probabilités de stats');
        self::assertSelectorTextNotContains('[data-testid="configuration-panel"]', 'Messages d’arrivée');
        self::assertSelectorTextNotContains('[data-testid="configuration-panel"]', 'Force');
        self::assertSelectorTextNotContains('[data-testid="configuration-panel"]', 'Guerrier');
        self::assertSelectorTextNotContains('[data-testid="configuration-panel"]', 'Feu');
        self::assertSelectorTextNotContains('[data-testid="server-configuration"]', 'Hors serveur');

        $client->request('GET', '/app/serveurs/admin/configuration/roles');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-testid="configuration-nav-roles"][aria-current="page"]');
        self::assertSelectorExists('[data-testid="catalog-create-panel"] form[data-controller="emoji-picker"]');
        self::assertSelectorExists('[data-testid="catalog-create-panel"] input[name="emoji_source"][type="hidden"][data-emoji-picker-target="source"]');
        self::assertSelectorExists('[data-testid="catalog-create-panel"] input[name="emoji_value"][type="hidden"][data-emoji-picker-target="value"]');
        self::assertSelectorNotExists('[data-testid="catalog-create-panel"] input[name="emoji_value"]:not([type="hidden"])');
        self::assertSelectorExists('[data-testid="emoji-picker-option-server-246801357924680135"]');
        self::assertSelectorExists('[data-testid="emoji-picker-option-bot-135792468013579246"]');
        self::assertSelectorExists('[data-testid="catalog-create-panel"] [data-testid="emoji-preview"] img[data-emoji-picker-target="image"]');
        self::assertSelectorExists('[data-testid="catalog-list-panel"]');
        self::assertSelectorExists('[data-testid="role-card"] img[alt="Emoji du rôle Guerrier"][src="https://cdn.discordapp.com/emojis/123456789012345678.webp?size=64&quality=lossless"]');
        self::assertSelectorTextContains('[data-testid="configuration-panel"]', 'Guerrier');
        self::assertSelectorTextContains('[data-testid="configuration-panel"]', 'Emoji serveur');
        self::assertSelectorTextContains('[data-testid="configuration-panel"]', '<:guerrier:123456789012345678>');
        self::assertSelectorTextNotContains('[data-testid="configuration-panel"]', 'Novice');

        $client->request('GET', '/app/serveurs/admin/configuration/stats');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-testid="configuration-nav-stats"][aria-current="page"]');
        self::assertSelectorExists('[data-testid="catalog-create-panel"]');
        self::assertSelectorExists('[data-testid="catalog-list-panel"] [data-testid="stat-card"]');
        self::assertSelectorTextContains('[data-testid="configuration-panel"]', 'Force');
        self::assertSelectorTextNotContains('[data-testid="configuration-panel"]', 'Feu');

        $client->request('GET', '/app/serveurs/admin/configuration/elements');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-testid="configuration-nav-elements"][aria-current="page"]');
        self::assertSelectorExists('[data-testid="catalog-create-panel"] form[data-controller="emoji-picker"]');
        self::assertSelectorExists('[data-testid="catalog-create-panel"] input[name="emoji_value"][type="hidden"][data-emoji-picker-target="value"]');
        self::assertSelectorNotExists('[data-testid="catalog-create-panel"] input[name="emoji_value"]:not([type="hidden"])');
        self::assertSelectorExists('[data-testid="catalog-list-panel"] [data-testid="element-card"]');
        self::assertSelectorTextContains('[data-testid="element-card"]', '🔥');
        self::assertSelectorTextContains('[data-testid="configuration-panel"]', 'Feu');
        self::assertSelectorTextNotContains('[data-testid="configuration-panel"]', 'Force');
    }

    public function testDedicatedRankRelationPagesAreAvailableFromSidebarAndEditable(): void
    {
        $client = static::createClient();
        $this->resetDatabase();
        $this->seedPersistentBackofficeAccess($client);

        $adminServerId = $this->serverDatabaseId('admin');
        $otherServerId = $this->serverDatabaseId('unrelated');

        $this->connection()->insert('ranks', [
            'server_id' => $adminServerId,
            'discord_id' => 'rank-admin',
            'name' => 'Novice',
            'percentage' => 30,
            'bye_title' => null,
            'is_staff' => 0,
            'created_at' => '2026-07-06 10:00:00',
            'updated_at' => '2026-07-06 10:00:00',
        ]);
        $rankId = (int) $this->connection()->lastInsertId();

        $this->connection()->insert('ranks', [
            'server_id' => $otherServerId,
            'discord_id' => 'rank-other',
            'name' => 'Rang externe',
            'percentage' => 100,
            'bye_title' => null,
            'is_staff' => 0,
            'created_at' => '2026-07-06 10:00:00',
            'updated_at' => '2026-07-06 10:00:00',
        ]);
        $otherRankId = (int) $this->connection()->lastInsertId();

        $this->connection()->insert('stats', [
            'server_id' => $adminServerId,
            'name' => 'Force',
        ]);
        $statId = (int) $this->connection()->lastInsertId();

        $this->connection()->insert('stats', [
            'server_id' => $otherServerId,
            'name' => 'Stat externe',
        ]);
        $otherStatId = (int) $this->connection()->lastInsertId();

        $this->connection()->insert('rank_stats', [
            'rank_id' => $rankId,
            'stat_id' => $statId,
            'percentage' => 70,
        ]);
        $this->connection()->insert('rank_stats', [
            'rank_id' => $otherRankId,
            'stat_id' => $otherStatId,
            'percentage' => 99,
        ]);

        $this->connection()->insert('welcome_messages', [
            'server_id' => $adminServerId,
            'rank_id' => $rankId,
            'message' => 'Bienvenue parmi nous.',
        ]);
        $welcomeMessageId = (int) $this->connection()->lastInsertId();

        $this->connection()->insert('bye_messages', [
            'server_id' => $adminServerId,
            'rank_id' => $rankId,
            'message' => 'À bientôt.',
        ]);
        $byeMessageId = (int) $this->connection()->lastInsertId();

        $client->request('GET', '/app/serveurs/admin/configuration/rank-stats');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-testid="configuration-nav-rank-stats"][aria-current="page"]');
        self::assertSelectorTextContains('[data-testid="configuration-panel"]', 'Novice');
        self::assertSelectorTextContains('[data-testid="configuration-panel"]', 'Force');
        self::assertSelectorTextContains('[data-testid="configuration-panel"]', '70%');
        self::assertSelectorTextNotContains('[data-testid="configuration-panel"]', 'Rang externe');
        self::assertSelectorTextNotContains('[data-testid="configuration-panel"]', 'Stat externe');

        $client->request('GET', '/app/serveurs/admin/configuration/welcome-messages');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-testid="configuration-nav-welcome-messages"][aria-current="page"]');
        self::assertSelectorExists('form[action="/app/serveurs/admin/catalogue/welcome-messages/'.$welcomeMessageId.'"] textarea[name="message"]');
        self::assertSelectorTextContains('[data-testid="configuration-panel"]', 'Bienvenue parmi nous.');
        self::assertSelectorTextContains('[data-testid="configuration-panel"]', 'Novice');

        $client->request('GET', '/app/serveurs/admin/configuration/bye-messages');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-testid="configuration-nav-bye-messages"][aria-current="page"]');
        self::assertSelectorExists('form[action="/app/serveurs/admin/catalogue/bye-messages/'.$byeMessageId.'"] textarea[name="message"]');
        self::assertSelectorTextContains('[data-testid="configuration-panel"]', 'À bientôt.');
        self::assertSelectorTextContains('[data-testid="configuration-panel"]', 'Novice');
    }

    public function testAdministratorCanCreateServerCatalogRows(): void
    {
        $client = static::createClient();
        $this->resetDatabase();
        $this->seedPersistentBackofficeAccess($client);

        $client->request('POST', '/app/serveurs/admin/catalogue/ranks', [
            'discord_id' => 'rank-created',
            'name' => 'Étoile',
            'percentage' => '25',
            'bye_title' => 'Étoile filante',
            'is_staff' => '1',
        ]);

        self::assertResponseRedirects('/app/serveurs/admin/configuration/ranks');

        $client->request('POST', '/app/serveurs/admin/catalogue/roles', [
            'name' => 'Alchimiste',
            'percentage' => '40',
            'emoji_source' => 'server',
            'emoji_value' => '<:alchimiste:987654321098765432>',
        ]);

        self::assertResponseRedirects('/app/serveurs/admin/configuration/roles');

        $client->request('POST', '/app/serveurs/admin/catalogue/stats', [
            'name' => 'Sagesse',
        ]);

        self::assertResponseRedirects('/app/serveurs/admin/configuration/stats');

        $client->request('POST', '/app/serveurs/admin/catalogue/elements', [
            'name' => 'Lune',
            'emoji_source' => 'unicode',
            'emoji_value' => '🌙',
        ]);

        self::assertResponseRedirects('/app/serveurs/admin/configuration/elements');

        $serverId = $this->serverDatabaseId('admin');

        self::assertSame([
            'discord_id' => 'rank-created',
            'name' => 'Étoile',
            'percentage' => 25,
            'bye_title' => 'Étoile filante',
            'is_staff' => 1,
        ], $this->connection()->fetchAssociative(
            'SELECT discord_id, name, percentage, bye_title, is_staff FROM ranks WHERE server_id = ?',
            [$serverId],
        ));
        self::assertSame([
            'name' => 'Alchimiste',
            'percentage' => 40,
            'emoji_source' => 'server',
            'emoji_id' => '987654321098765432',
            'emoji_name' => 'alchimiste',
            'emoji_animated' => 0,
            'emoji_unicode' => null,
        ], $this->connection()->fetchAssociative(
            'SELECT name, percentage, emoji_source, emoji_id, emoji_name, emoji_animated, emoji_unicode FROM roles WHERE server_id = ?',
            [$serverId],
        ));
        self::assertSame('Sagesse', $this->connection()->fetchOne('SELECT name FROM stats WHERE server_id = ?', [$serverId]));
        self::assertSame(
            ['name' => 'Lune', 'emoji_source' => 'unicode', 'emoji_unicode' => '🌙'],
            $this->connection()->fetchAssociative('SELECT name, emoji_source, emoji_unicode FROM elements WHERE server_id = ?', [$serverId]),
        );
    }

    public function testAdministratorCanUpdateAndDeleteServerRoles(): void
    {
        $client = static::createClient();
        $this->resetDatabase();
        $this->seedPersistentBackofficeAccess($client);

        $adminServerId = $this->serverDatabaseId('admin');
        $otherServerId = $this->serverDatabaseId('unrelated');

        $this->connection()->insert('roles', [
            'server_id' => $adminServerId,
            'name' => 'Ancien rôle',
            'percentage' => 12,
            'emoji_source' => 'unicode',
            'emoji_unicode' => '🎭',
            'emoji_id' => null,
            'emoji_name' => null,
            'emoji_animated' => 0,
        ]);
        $roleId = (int) $this->connection()->lastInsertId();

        $this->connection()->insert('roles', [
            'server_id' => $otherServerId,
            'name' => 'Rôle externe',
            'percentage' => 99,
            'emoji_source' => 'unicode',
            'emoji_unicode' => '🌒',
            'emoji_id' => null,
            'emoji_name' => null,
            'emoji_animated' => 0,
        ]);

        $client->request('POST', '/app/serveurs/admin/catalogue/roles/'.$roleId, [
            'name' => 'Nouveau rôle',
            'percentage' => '35',
            'emoji_source' => 'server',
            'emoji_value' => '<:nouveau:555555555555555555>',
        ]);

        self::assertResponseRedirects('/app/serveurs/admin/configuration/roles');
        self::assertSame([
            'name' => 'Nouveau rôle',
            'percentage' => 35,
            'emoji_source' => 'server',
            'emoji_id' => '555555555555555555',
            'emoji_name' => 'nouveau',
            'emoji_animated' => 0,
            'emoji_unicode' => null,
        ], $this->connection()->fetchAssociative(
            'SELECT name, percentage, emoji_source, emoji_id, emoji_name, emoji_animated, emoji_unicode FROM roles WHERE id = ?',
            [$roleId],
        ));

        $client->request('POST', '/app/serveurs/admin/catalogue/roles/'.$roleId.'/supprimer');

        self::assertResponseRedirects('/app/serveurs/admin/configuration/roles');
        self::assertSame(0, (int) $this->connection()->fetchOne('SELECT COUNT(*) FROM roles WHERE server_id = ?', [$adminServerId]));
        self::assertSame(1, (int) $this->connection()->fetchOne('SELECT COUNT(*) FROM roles WHERE server_id = ?', [$otherServerId]));
    }

    public function testAdministratorCanUpdateAndDeleteServerCatalogRows(): void
    {
        $client = static::createClient();
        $this->resetDatabase();
        $this->seedPersistentBackofficeAccess($client);

        $adminServerId = $this->serverDatabaseId('admin');
        $otherServerId = $this->serverDatabaseId('unrelated');

        $this->connection()->insert('ranks', [
            'server_id' => $adminServerId,
            'discord_id' => 'rank-old',
            'name' => 'Ancien rang',
            'percentage' => 12,
            'bye_title' => null,
            'is_staff' => 0,
            'created_at' => '2026-07-06 10:00:00',
            'updated_at' => '2026-07-06 10:00:00',
        ]);
        $rankId = (int) $this->connection()->lastInsertId();
        $this->connection()->insert('ranks', [
            'server_id' => $otherServerId,
            'discord_id' => 'rank-other',
            'name' => 'Rang externe',
            'percentage' => 100,
            'bye_title' => null,
            'is_staff' => 0,
            'created_at' => '2026-07-06 10:00:00',
            'updated_at' => '2026-07-06 10:00:00',
        ]);

        $this->connection()->insert('stats', [
            'server_id' => $adminServerId,
            'name' => 'Ancienne stat',
        ]);
        $statId = (int) $this->connection()->lastInsertId();
        $this->connection()->insert('stats', [
            'server_id' => $otherServerId,
            'name' => 'Stat externe',
        ]);

        $this->connection()->insert('elements', [
            'server_id' => $adminServerId,
            'name' => 'Ancien élément',
            'emoji_source' => 'unicode',
            'emoji_unicode' => '✨',
            'emoji_id' => null,
            'emoji_name' => null,
            'emoji_animated' => 0,
        ]);
        $elementId = (int) $this->connection()->lastInsertId();
        $this->connection()->insert('elements', [
            'server_id' => $otherServerId,
            'name' => 'Élément externe',
            'emoji_source' => 'unicode',
            'emoji_unicode' => '🌒',
            'emoji_id' => null,
            'emoji_name' => null,
            'emoji_animated' => 0,
        ]);

        $client->request('POST', '/app/serveurs/admin/catalogue/ranks/'.$rankId, [
            'discord_id' => 'rank-new',
            'name' => 'Nouveau rang',
            'percentage' => '42',
            'bye_title' => 'Départ solaire',
            'is_staff' => '1',
        ]);

        self::assertResponseRedirects('/app/serveurs/admin/configuration/ranks');
        self::assertSame([
            'discord_id' => 'rank-new',
            'name' => 'Nouveau rang',
            'percentage' => 42,
            'bye_title' => 'Départ solaire',
            'is_staff' => 1,
        ], $this->connection()->fetchAssociative(
            'SELECT discord_id, name, percentage, bye_title, is_staff FROM ranks WHERE id = ?',
            [$rankId],
        ));

        $client->request('POST', '/app/serveurs/admin/catalogue/stats/'.$statId, [
            'name' => 'Nouvelle stat',
        ]);

        self::assertResponseRedirects('/app/serveurs/admin/configuration/stats');
        self::assertSame('Nouvelle stat', $this->connection()->fetchOne('SELECT name FROM stats WHERE id = ?', [$statId]));

        $client->request('POST', '/app/serveurs/admin/catalogue/elements/'.$elementId, [
            'name' => 'Nouvel élément',
            'emoji_source' => 'bot',
            'emoji_value' => '<a:cristal:555555555555555555>',
        ]);

        self::assertResponseRedirects('/app/serveurs/admin/configuration/elements');
        self::assertSame([
            'name' => 'Nouvel élément',
            'emoji_source' => 'bot',
            'emoji_id' => '555555555555555555',
            'emoji_name' => 'cristal',
            'emoji_animated' => 1,
            'emoji_unicode' => null,
        ], $this->connection()->fetchAssociative(
            'SELECT name, emoji_source, emoji_id, emoji_name, emoji_animated, emoji_unicode FROM elements WHERE id = ?',
            [$elementId],
        ));

        $client->request('POST', '/app/serveurs/admin/catalogue/ranks/'.$rankId.'/supprimer');
        self::assertResponseRedirects('/app/serveurs/admin/configuration/ranks');

        $client->request('POST', '/app/serveurs/admin/catalogue/stats/'.$statId.'/supprimer');
        self::assertResponseRedirects('/app/serveurs/admin/configuration/stats');

        $client->request('POST', '/app/serveurs/admin/catalogue/elements/'.$elementId.'/supprimer');
        self::assertResponseRedirects('/app/serveurs/admin/configuration/elements');

        self::assertSame(0, (int) $this->connection()->fetchOne('SELECT COUNT(*) FROM ranks WHERE server_id = ?', [$adminServerId]));
        self::assertSame(0, (int) $this->connection()->fetchOne('SELECT COUNT(*) FROM stats WHERE server_id = ?', [$adminServerId]));
        self::assertSame(0, (int) $this->connection()->fetchOne('SELECT COUNT(*) FROM elements WHERE server_id = ?', [$adminServerId]));
        self::assertSame(1, (int) $this->connection()->fetchOne('SELECT COUNT(*) FROM ranks WHERE server_id = ?', [$otherServerId]));
        self::assertSame(1, (int) $this->connection()->fetchOne('SELECT COUNT(*) FROM stats WHERE server_id = ?', [$otherServerId]));
        self::assertSame(1, (int) $this->connection()->fetchOne('SELECT COUNT(*) FROM elements WHERE server_id = ?', [$otherServerId]));
    }

    public function testAdministratorCanManageRankStatsAndMessages(): void
    {
        $client = static::createClient();
        $this->resetDatabase();
        $this->seedPersistentBackofficeAccess($client);

        $adminServerId = $this->serverDatabaseId('admin');

        $this->connection()->insert('ranks', [
            'server_id' => $adminServerId,
            'discord_id' => 'rank-admin',
            'name' => 'Novice',
            'percentage' => 30,
            'bye_title' => null,
            'is_staff' => 0,
            'created_at' => '2026-07-06 10:00:00',
            'updated_at' => '2026-07-06 10:00:00',
        ]);
        $rankId = (int) $this->connection()->lastInsertId();

        $this->connection()->insert('stats', [
            'server_id' => $adminServerId,
            'name' => 'Force',
        ]);
        $statId = (int) $this->connection()->lastInsertId();

        $client->request('POST', '/app/serveurs/admin/catalogue/ranks/'.$rankId.'/stats', [
            'stat_id' => (string) $statId,
            'percentage' => '80',
        ]);

        self::assertResponseRedirects('/app/serveurs/admin/configuration/ranks');
        self::assertSame(80, (int) $this->connection()->fetchOne(
            'SELECT percentage FROM rank_stats WHERE rank_id = ? AND stat_id = ?',
            [$rankId, $statId],
        ));

        $client->request('POST', '/app/serveurs/admin/catalogue/ranks/'.$rankId.'/stats', [
            'stat_id' => (string) $statId,
            'percentage' => '25',
        ]);

        self::assertResponseRedirects('/app/serveurs/admin/configuration/ranks');
        self::assertSame(1, (int) $this->connection()->fetchOne(
            'SELECT COUNT(*) FROM rank_stats WHERE rank_id = ? AND stat_id = ?',
            [$rankId, $statId],
        ));
        self::assertSame(25, (int) $this->connection()->fetchOne(
            'SELECT percentage FROM rank_stats WHERE rank_id = ? AND stat_id = ?',
            [$rankId, $statId],
        ));

        $client->request('POST', '/app/serveurs/admin/catalogue/ranks/'.$rankId.'/welcome-messages', [
            'message' => 'Bienvenue dans la guilde.',
        ]);

        self::assertResponseRedirects('/app/serveurs/admin/configuration/ranks');
        $welcomeMessageId = (int) $this->connection()->fetchOne('SELECT id FROM welcome_messages WHERE rank_id = ?', [$rankId]);
        self::assertSame('Bienvenue dans la guilde.', $this->connection()->fetchOne('SELECT message FROM welcome_messages WHERE id = ?', [$welcomeMessageId]));

        $client->request('POST', '/app/serveurs/admin/catalogue/ranks/'.$rankId.'/bye-messages', [
            'message' => 'À la prochaine.',
        ]);

        self::assertResponseRedirects('/app/serveurs/admin/configuration/ranks');
        $byeMessageId = (int) $this->connection()->fetchOne('SELECT id FROM bye_messages WHERE rank_id = ?', [$rankId]);
        self::assertSame('À la prochaine.', $this->connection()->fetchOne('SELECT message FROM bye_messages WHERE id = ?', [$byeMessageId]));

        $client->request('POST', '/app/serveurs/admin/catalogue/ranks/'.$rankId.'/stats/'.$statId.'/supprimer');
        self::assertResponseRedirects('/app/serveurs/admin/configuration/ranks');
        self::assertSame(0, (int) $this->connection()->fetchOne('SELECT COUNT(*) FROM rank_stats WHERE rank_id = ?', [$rankId]));

        $client->request('POST', '/app/serveurs/admin/catalogue/ranks/'.$rankId.'/welcome-messages/'.$welcomeMessageId.'/supprimer');
        self::assertResponseRedirects('/app/serveurs/admin/configuration/ranks');
        self::assertSame(0, (int) $this->connection()->fetchOne('SELECT COUNT(*) FROM welcome_messages WHERE rank_id = ?', [$rankId]));

        $client->request('POST', '/app/serveurs/admin/catalogue/ranks/'.$rankId.'/bye-messages/'.$byeMessageId.'/supprimer');
        self::assertResponseRedirects('/app/serveurs/admin/configuration/ranks');
        self::assertSame(0, (int) $this->connection()->fetchOne('SELECT COUNT(*) FROM bye_messages WHERE rank_id = ?', [$rankId]));
    }

    public function testAdministratorCanManageMessagesFromDedicatedPages(): void
    {
        $client = static::createClient();
        $this->resetDatabase();
        $this->seedPersistentBackofficeAccess($client);

        $adminServerId = $this->serverDatabaseId('admin');

        $this->connection()->insert('ranks', [
            'server_id' => $adminServerId,
            'discord_id' => 'rank-admin',
            'name' => 'Novice',
            'percentage' => 30,
            'bye_title' => null,
            'is_staff' => 0,
            'created_at' => '2026-07-06 10:00:00',
            'updated_at' => '2026-07-06 10:00:00',
        ]);
        $rankId = (int) $this->connection()->lastInsertId();

        $client->request('POST', '/app/serveurs/admin/catalogue/welcome-messages', [
            'rank_id' => (string) $rankId,
            'message' => 'Bienvenue initial.',
        ]);

        self::assertResponseRedirects('/app/serveurs/admin/configuration/welcome-messages');
        $welcomeMessageId = (int) $this->connection()->fetchOne('SELECT id FROM welcome_messages WHERE rank_id = ?', [$rankId]);
        self::assertSame('Bienvenue initial.', $this->connection()->fetchOne('SELECT message FROM welcome_messages WHERE id = ?', [$welcomeMessageId]));

        $client->request('POST', '/app/serveurs/admin/catalogue/welcome-messages/'.$welcomeMessageId, [
            'message' => 'Bienvenue édité.',
        ]);

        self::assertResponseRedirects('/app/serveurs/admin/configuration/welcome-messages');
        self::assertSame('Bienvenue édité.', $this->connection()->fetchOne('SELECT message FROM welcome_messages WHERE id = ?', [$welcomeMessageId]));

        $client->request('POST', '/app/serveurs/admin/catalogue/bye-messages', [
            'rank_id' => (string) $rankId,
            'message' => 'Départ initial.',
        ]);

        self::assertResponseRedirects('/app/serveurs/admin/configuration/bye-messages');
        $byeMessageId = (int) $this->connection()->fetchOne('SELECT id FROM bye_messages WHERE rank_id = ?', [$rankId]);
        self::assertSame('Départ initial.', $this->connection()->fetchOne('SELECT message FROM bye_messages WHERE id = ?', [$byeMessageId]));

        $client->request('POST', '/app/serveurs/admin/catalogue/bye-messages/'.$byeMessageId, [
            'message' => 'Départ édité.',
        ]);

        self::assertResponseRedirects('/app/serveurs/admin/configuration/bye-messages');
        self::assertSame('Départ édité.', $this->connection()->fetchOne('SELECT message FROM bye_messages WHERE id = ?', [$byeMessageId]));

        $client->request('POST', '/app/serveurs/admin/catalogue/welcome-messages/'.$welcomeMessageId.'/supprimer');
        self::assertResponseRedirects('/app/serveurs/admin/configuration/welcome-messages');

        $client->request('POST', '/app/serveurs/admin/catalogue/bye-messages/'.$byeMessageId.'/supprimer');
        self::assertResponseRedirects('/app/serveurs/admin/configuration/bye-messages');

        self::assertSame(0, (int) $this->connection()->fetchOne('SELECT COUNT(*) FROM welcome_messages WHERE rank_id = ?', [$rankId]));
        self::assertSame(0, (int) $this->connection()->fetchOne('SELECT COUNT(*) FROM bye_messages WHERE rank_id = ?', [$rankId]));
    }

    public function testMemberCannotCreateServerCatalogRows(): void
    {
        $client = static::createClient();
        $this->resetDatabase();
        $this->seedPersistentBackofficeAccess($client);

        $client->request('POST', '/app/serveurs/member/catalogue/stats', [
            'name' => 'Interdit',
        ]);

        self::assertResponseStatusCodeSame(403);
        self::assertSame(0, (int) $this->connection()->fetchOne('SELECT COUNT(*) FROM stats'));

        $client->request('POST', '/app/serveurs/member/catalogue/welcome-messages', [
            'rank_id' => '1',
            'message' => 'Interdit',
        ]);

        self::assertResponseStatusCodeSame(403);

        $client->request('POST', '/app/serveurs/member/catalogue/bye-messages', [
            'rank_id' => '1',
            'message' => 'Interdit',
        ]);

        self::assertResponseStatusCodeSame(403);
        self::assertSame(0, (int) $this->connection()->fetchOne('SELECT COUNT(*) FROM welcome_messages'));
        self::assertSame(0, (int) $this->connection()->fetchOne('SELECT COUNT(*) FROM bye_messages'));
    }

    public function testCharacterSheetPageIsAvailableToEveryDatabaseAccessibleGuildMember(): void
    {
        $client = static::createClient();
        $this->resetDatabase();
        $this->seedPersistentBackofficeAccess($client);

        $client->request('GET', '/app/serveurs/admin/fiche-personnage');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Fiche personnage');
        self::assertSelectorTextContains('body', 'Serveur Admin');

        $client->request('GET', '/app/serveurs/member/fiche-personnage');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Fiche personnage');
        self::assertSelectorTextContains('body', 'Serveur Membre');
    }

    public function testUnknownServerReturnsNotFound(): void
    {
        $client = static::createClient();
        $this->resetDatabase();
        $this->seedPersistentBackofficeAccess($client);

        $client->request('GET', '/app/serveurs/unknown/fiche-personnage');

        self::assertResponseStatusCodeSame(404);
    }

    private function seedKnownDiscordServer(string $discordId, string $name, ?string $icon): int
    {
        $this->connection()->insert('discord_servers', [
            'discord_id' => $discordId,
            'name' => $name,
            'icon' => $icon,
            'created_at' => '2026-07-06 10:00:00',
            'updated_at' => '2026-07-06 10:00:00',
        ]);

        return (int) $this->connection()->lastInsertId();
    }

    private function seedPersistentBackofficeAccess(KernelBrowser $client): void
    {
        $adminServerId = $this->seedKnownDiscordServer('admin', 'Serveur Admin', 'static-icon-hash');
        $memberServerId = $this->seedKnownDiscordServer('member', 'Serveur Membre', null);
        $this->seedKnownDiscordServer('unrelated', 'Serveur Non Lié', null);

        $this->connection()->insert('discord_users', [
            'discord_id' => '42',
            'username' => 'melaine',
            'global_name' => 'Melaine',
            'avatar' => null,
            'created_at' => '2026-07-06 10:00:00',
            'updated_at' => '2026-07-06 10:00:00',
        ]);
        $userId = (int) $this->connection()->lastInsertId();

        $this->connection()->insert('discord_server_members', [
            'user_id' => $userId,
            'server_id' => $adminServerId,
            'owner' => 0,
            'permissions' => '8',
            'can_manage_configuration' => 1,
            'created_at' => '2026-07-06 10:00:00',
            'updated_at' => '2026-07-06 10:00:00',
        ]);
        $this->connection()->insert('discord_server_members', [
            'user_id' => $userId,
            'server_id' => $memberServerId,
            'owner' => 0,
            'permissions' => '0',
            'can_manage_configuration' => 0,
            'created_at' => '2026-07-06 10:00:00',
            'updated_at' => '2026-07-06 10:00:00',
        ]);

        $session = static::getContainer()->get('session.factory')->createSession();
        $session->set('gachamelia.discord_user_id', $userId);
        $session->save();

        $client->getCookieJar()->set(new Cookie($session->getName(), $session->getId()));
    }

    private function serverDatabaseId(string $discordId): int
    {
        $serverId = $this->connection()->fetchOne('SELECT id FROM discord_servers WHERE discord_id = ?', [$discordId]);

        self::assertIsNumeric($serverId);

        return (int) $serverId;
    }
}

final class FakeDiscordApiClient implements DiscordApiClientInterface
{
    public function exchangeCodeForAccessToken(string $code): string
    {
        return 'fake-access-token';
    }

    public function fetchCurrentUser(string $accessToken): array
    {
        return [
            'id' => '42',
            'username' => 'melaine',
            'global_name' => 'Melaine',
            'avatar' => 'avatar-hash',
        ];
    }

    public function fetchCurrentUserGuilds(string $accessToken): array
    {
        return [
            ['id' => 'admin', 'name' => 'Serveur Admin', 'icon' => 'fresh-icon', 'owner' => false, 'permissions' => '8'],
            ['id' => 'member', 'name' => 'Serveur Membre', 'icon' => null, 'owner' => false, 'permissions' => '0'],
            ['id' => 'without-bot', 'name' => 'Serveur Sans Bot', 'icon' => null, 'owner' => true, 'permissions' => '8'],
        ];
    }
}
