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
        self::assertSelectorExists('[data-testid="guild-admin"] a[href="/app/serveurs/admin/configuration/ranks"]');
        self::assertSelectorExists('[data-testid="guild-admin"] a[href="/app/serveurs/admin/fiche-personnage"]');
        self::assertSelectorNotExists('[data-testid="guild-member"] a[href="/app/serveurs/member/configuration/ranks"]');
        self::assertSelectorExists('[data-testid="guild-member"] a[href="/app/serveurs/member/fiche-personnage"]');
        self::assertStringContainsString(
            'cursor-pointer',
            $crawler->filter('form[action="/deconnexion"] button[type="submit"]')->attr('class') ?? '',
        );
    }

    public function testConfigurationRootRedirectsToRanksAndSectionRequiresAdministratorAccess(): void
    {
        $client = static::createClient();
        $this->resetDatabase();
        $this->seedPersistentBackofficeAccess($client);

        $client->request('GET', '/app/serveurs/admin/configuration');

        self::assertResponseRedirects('/app/serveurs/admin/configuration/ranks');

        $client->request('GET', '/app/serveurs/admin/configuration/ranks');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Configuration du serveur');
        self::assertSelectorTextContains('body', 'Serveur Admin');
        self::assertSelectorExists('[data-testid="server-configuration"] img[alt="Logo Serveur Admin"][src="https://cdn.discordapp.com/icons/admin/static-icon-hash.webp?size=64"]');
        self::assertSelectorExists('[data-testid="configuration-nav-ranks"][aria-current="page"]');

        $client->request('GET', '/app/serveurs/member/configuration/ranks');

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
            'image_url' => 'https://example.test/guerrier.png',
        ]);
        $this->connection()->insert('stats', [
            'server_id' => $adminServerId,
            'name' => 'Force',
        ]);
        $this->connection()->insert('elements', [
            'server_id' => $adminServerId,
            'name' => 'Feu',
        ]);
        $this->connection()->insert('stats', [
            'server_id' => $otherServerId,
            'name' => 'Hors serveur',
        ]);

        $client->request('GET', '/app/serveurs/admin/configuration/ranks');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-testid="configuration-nav-ranks"][aria-current="page"]');
        self::assertSelectorExists('[data-testid="configuration-nav-roles"][href="/app/serveurs/admin/configuration/roles"]');
        self::assertSelectorExists('[data-testid="configuration-nav-stats"][href="/app/serveurs/admin/configuration/stats"]');
        self::assertSelectorExists('[data-testid="configuration-nav-elements"][href="/app/serveurs/admin/configuration/elements"]');
        self::assertSelectorTextContains('[data-testid="configuration-panel"]', 'Novice');
        self::assertSelectorTextNotContains('[data-testid="configuration-panel"]', 'Guerrier');
        self::assertSelectorTextNotContains('[data-testid="configuration-panel"]', 'Force');
        self::assertSelectorTextNotContains('[data-testid="configuration-panel"]', 'Feu');
        self::assertSelectorTextNotContains('[data-testid="server-configuration"]', 'Hors serveur');

        $client->request('GET', '/app/serveurs/admin/configuration/roles');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-testid="configuration-nav-roles"][aria-current="page"]');
        self::assertSelectorTextContains('[data-testid="configuration-panel"]', 'Guerrier');
        self::assertSelectorTextNotContains('[data-testid="configuration-panel"]', 'Novice');

        $client->request('GET', '/app/serveurs/admin/configuration/stats');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-testid="configuration-nav-stats"][aria-current="page"]');
        self::assertSelectorTextContains('[data-testid="configuration-panel"]', 'Force');
        self::assertSelectorTextNotContains('[data-testid="configuration-panel"]', 'Feu');

        $client->request('GET', '/app/serveurs/admin/configuration/elements');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-testid="configuration-nav-elements"][aria-current="page"]');
        self::assertSelectorTextContains('[data-testid="configuration-panel"]', 'Feu');
        self::assertSelectorTextNotContains('[data-testid="configuration-panel"]', 'Force');
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
            'image_url' => 'https://example.test/alchimiste.png',
        ]);

        self::assertResponseRedirects('/app/serveurs/admin/configuration/roles');

        $client->request('POST', '/app/serveurs/admin/catalogue/stats', [
            'name' => 'Sagesse',
        ]);

        self::assertResponseRedirects('/app/serveurs/admin/configuration/stats');

        $client->request('POST', '/app/serveurs/admin/catalogue/elements', [
            'name' => 'Lune',
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
            'image_url' => 'https://example.test/alchimiste.png',
        ], $this->connection()->fetchAssociative(
            'SELECT name, percentage, image_url FROM roles WHERE server_id = ?',
            [$serverId],
        ));
        self::assertSame('Sagesse', $this->connection()->fetchOne('SELECT name FROM stats WHERE server_id = ?', [$serverId]));
        self::assertSame('Lune', $this->connection()->fetchOne('SELECT name FROM elements WHERE server_id = ?', [$serverId]));
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
