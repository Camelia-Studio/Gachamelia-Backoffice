<?php

namespace App\Tests\Controller;

use App\Discord\DiscordApiClientInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;

final class DiscordBackofficeControllerTest extends WebTestCase
{
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

    public function testDiscordCallbackStoresOnlyGuildsSharedWithBot(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        static::getContainer()->set(DiscordApiClientInterface::class, new FakeDiscordApiClient());

        $client->request('GET', '/connexion/discord');
        $location = $client->getResponse()->headers->get('Location') ?? '';
        parse_str(parse_url($location, PHP_URL_QUERY) ?: '', $query);

        self::assertIsString($query['state'] ?? null);

        $client->request('GET', '/connexion/discord/retour?code=valid-code&state='.$query['state']);

        self::assertResponseRedirects('/app');

        $client->followRedirect();

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('[data-testid="backoffice-dashboard"]', 'Serveur Admin');
        self::assertSelectorTextNotContains('[data-testid="backoffice-dashboard"]', 'Serveur Sans Bot');
    }

    public function testDashboardListsAccessibleGuildsAndRoleSpecificLinks(): void
    {
        $client = static::createClient();
        $this->seedBackofficeSession($client);

        $crawler = $client->request('GET', '/app');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('[data-testid="backoffice-dashboard"]', 'Serveur Admin');
        self::assertSelectorTextContains('[data-testid="backoffice-dashboard"]', 'Serveur Membre');
        self::assertSelectorExists('[data-testid="guild-admin"] a[href="/app/serveurs/admin/configuration"]');
        self::assertSelectorExists('[data-testid="guild-admin"] a[href="/app/serveurs/admin/fiche-personnage"]');
        self::assertSelectorNotExists('[data-testid="guild-member"] a[href="/app/serveurs/member/configuration"]');
        self::assertSelectorExists('[data-testid="guild-member"] a[href="/app/serveurs/member/fiche-personnage"]');
        self::assertStringContainsString(
            'cursor-pointer',
            $crawler->filter('form[action="/deconnexion"] button[type="submit"]')->attr('class') ?? '',
        );
    }

    public function testConfigurationPageRequiresAdministratorAccess(): void
    {
        $client = static::createClient();
        $this->seedBackofficeSession($client);

        $client->request('GET', '/app/serveurs/admin/configuration');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Configuration du serveur');
        self::assertSelectorTextContains('body', 'Serveur Admin');

        $client->request('GET', '/app/serveurs/member/configuration');

        self::assertResponseStatusCodeSame(403);
    }

    public function testCharacterSheetPageIsAvailableToEveryAccessibleGuildMember(): void
    {
        $client = static::createClient();
        $this->seedBackofficeSession($client);

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
        $this->seedBackofficeSession($client);

        $client->request('GET', '/app/serveurs/unknown/fiche-personnage');

        self::assertResponseStatusCodeSame(404);
    }

    private function seedBackofficeSession(KernelBrowser $client): void
    {
        $session = static::getContainer()->get('session.factory')->createSession();
        $session->set('gachamelia.discord_profile', [
            'id' => '42',
            'username' => 'Melaine',
            'global_name' => 'Melaine',
            'avatar' => null,
        ]);
        $session->set('gachamelia.discord_guilds', [
            [
                'id' => 'admin',
                'name' => 'Serveur Admin',
                'icon' => null,
                'owner' => false,
                'permissions' => '8',
                'canManageConfiguration' => true,
            ],
            [
                'id' => 'member',
                'name' => 'Serveur Membre',
                'icon' => null,
                'owner' => false,
                'permissions' => '0',
                'canManageConfiguration' => false,
            ],
        ]);
        $session->save();

        $client->getCookieJar()->set(new Cookie($session->getName(), $session->getId()));
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
            'username' => 'Melaine',
            'global_name' => 'Melaine',
            'avatar' => null,
        ];
    }

    public function fetchCurrentUserGuilds(string $accessToken): array
    {
        return [
            ['id' => 'admin', 'name' => 'Serveur Admin', 'icon' => null, 'owner' => false, 'permissions' => '8'],
            ['id' => 'member', 'name' => 'Serveur Membre', 'icon' => null, 'owner' => false, 'permissions' => '0'],
            ['id' => 'without-bot', 'name' => 'Serveur Sans Bot', 'icon' => null, 'owner' => true, 'permissions' => '8'],
        ];
    }

    public function fetchBotGuilds(): array
    {
        return [
            ['id' => 'admin', 'name' => 'Serveur Admin', 'icon' => null],
            ['id' => 'member', 'name' => 'Serveur Membre', 'icon' => null],
        ];
    }
}
