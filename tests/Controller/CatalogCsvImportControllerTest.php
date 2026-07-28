<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Discord\DiscordGuildResourcesProviderInterface;
use App\Entity\CatalogTemplate;
use App\Entity\DiscordServer;
use App\Entity\DiscordServerMember;
use App\Entity\DiscordUser;
use App\Tests\Support\DatabaseResetter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class CatalogCsvImportControllerTest extends WebTestCase
{
    use DatabaseResetter;

    private EntityManagerInterface $entityManager;

    public function testEveryCatalogueSectionShowsImportAndExampleActionsExceptSettings(): void
    {
        $client = self::createClient();
        $this->resetDatabase();
        [, , $template] = $this->seedAccess($client);

        foreach (['ranks', 'role-stats', 'welcome-messages', 'bye-messages', 'roles', 'stats', 'elements'] as $section) {
            $client->request('GET', '/app/serveurs/guild/configuration/'.$section);
            self::assertResponseIsSuccessful();
            self::assertSelectorExists('[data-testid="catalog-csv-actions"] a[href="/app/serveurs/guild/configuration/'.$section.'/csv"]');
            self::assertSelectorExists('[data-testid="catalog-csv-actions"] a[href="/app/serveurs/guild/configuration/'.$section.'/csv/exemple"]');

            $client->request('GET', '/app/modeles-catalogue/'.$template->id().'/configuration/'.$section);
            self::assertResponseIsSuccessful();
            self::assertSelectorExists('[data-testid="catalog-csv-actions"] a[href="/app/modeles-catalogue/'.$template->id().'/configuration/'.$section.'/csv"]');
            self::assertSelectorExists('[data-testid="catalog-csv-actions"] a[href="/app/modeles-catalogue/'.$template->id().'/configuration/'.$section.'/csv/exemple"]');
        }

        $client->request('GET', '/app/serveurs/guild/configuration/settings');
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('[data-testid="catalog-csv-actions"]');
    }

    public function testExampleDownloadUsesBomExpectedHeaderAndNoDiscordId(): void
    {
        $client = self::createClient();
        $this->resetDatabase();
        $this->seedAccess($client);

        $client->request('GET', '/app/serveurs/guild/configuration/ranks/csv/exemple');

        self::assertResponseIsSuccessful();
        self::assertSame('attachment; filename=exemple-rangs.csv', $client->getResponse()->headers->get('Content-Disposition'));
        self::assertStringStartsWith("\xEF\xBB\xBFnom;pourcentage;titre_depart;est_staff\n", $client->getResponse()->getContent());
        self::assertStringNotContainsString('discord', strtolower($client->getResponse()->getContent()));
    }

    public function testCsvUploadAndBackofficeControlsExposeClearInteractiveAffordances(): void
    {
        $client = self::createClient();
        $this->resetDatabase();
        $this->seedAccess($client);

        $crawler = $client->request('GET', '/app/serveurs/guild/configuration/roles/csv');

        self::assertResponseIsSuccessful();
        $bodyClasses = $crawler->filter('body')->attr('class') ?? '';
        self::assertStringContainsString('[&_button:not(:disabled)]:cursor-pointer', $bodyClasses);
        self::assertStringContainsString('[&_button:not(:disabled):hover]:brightness-90', $bodyClasses);
        self::assertStringContainsString('[&_button:disabled]:cursor-not-allowed', $bodyClasses);
        self::assertStringContainsString('[&_a[href]:hover]:brightness-90', $bodyClasses);
        self::assertStringContainsString('[&_select:not(:disabled)]:cursor-pointer', $bodyClasses);
        self::assertStringContainsString('[&_input[type=checkbox]:not(:disabled)]:cursor-pointer', $bodyClasses);

        $fileClasses = $crawler->filter('input[type="file"][name="csv_file"]')->attr('class') ?? '';
        self::assertStringContainsString('cursor-pointer', $fileClasses);
        self::assertStringContainsString('file:cursor-pointer', $fileClasses);
        self::assertStringContainsString('file:hover:bg-camelia-rose', $fileClasses);
    }

    public function testInvalidFileShowsStructuredErrorsAndDoesNotWrite(): void
    {
        $client = self::createClient();
        $this->resetDatabase();
        $this->seedAccess($client);

        $crawler = $this->upload($client, '/app/serveurs/guild/configuration/roles/csv/apercu', "nom;pourcentage;emoji\nGuerrier;80;⚔️\n");

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorExists('[data-testid="catalog-csv-error"]');
        self::assertSelectorTextContains('[data-testid="catalog-csv-error"]', '100');
        self::assertSame(0, (int) $this->connection()->fetchOne('SELECT COUNT(*) FROM roles'));
        self::assertCount(0, $crawler->filter('[data-testid="catalog-csv-preview"]'));
    }

    public function testPreviewThenApplyMergesAndReturnsToVisibleCatalogueState(): void
    {
        $client = self::createClient();
        $this->resetDatabase();
        $this->seedAccess($client);

        $crawler = $this->upload($client, '/app/serveurs/guild/configuration/roles/csv/apercu', "nom;pourcentage;emoji\nGuerrier;100;⚔️\n");

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-testid="catalog-csv-preview"]');
        self::assertSelectorExists('[data-testid="catalog-csv-operation"][data-action="create"]');
        self::assertSelectorTextContains('[data-testid="catalog-csv-totals"]', '100 %');
        $action = $crawler->filter('[data-testid="catalog-csv-preview"] form')->attr('action');
        self::assertIsString($action);

        $client->request('POST', $action, ['_token' => $this->csrfToken($client)]);

        self::assertResponseRedirects('/app/serveurs/guild/configuration/roles');
        self::assertSame('Guerrier', $this->connection()->fetchOne('SELECT name FROM roles'));
        $client->followRedirect();
        self::assertSelectorExists('[data-testid="catalog-validation"]');
        self::assertSelectorTextContains('[data-testid="flash-success"]', '1 création');

        $client->request('POST', $action, ['_token' => $this->csrfToken($client)]);
        self::assertResponseStatusCodeSame(404);
    }

    public function testNewServerRankRequiresAndRevalidatesDiscordRoleMapping(): void
    {
        $client = self::createClient();
        $client->disableReboot();
        $this->resetDatabase();
        $this->seedAccess($client);
        self::getContainer()->set(DiscordGuildResourcesProviderInterface::class, new CatalogCsvFakeDiscordResourcesProvider());

        $crawler = $this->upload($client, '/app/serveurs/guild/configuration/ranks/csv/apercu', "nom;pourcentage;titre_depart;est_staff\nGardien;100;;non\n");

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('select[name="discord_roles[2]"] option[value="discord-gardien"]');
        self::assertSelectorNotExists('select[name="discord_roles[2]"] option[value="managed-role"]');
        $action = $crawler->filter('[data-testid="catalog-csv-preview"] form')->attr('action');
        self::assertIsString($action);

        $client->request('POST', $action, [
            '_token' => $this->csrfToken($client),
            'discord_roles' => [2 => 'unknown-role'],
        ]);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(0, (int) $this->connection()->fetchOne('SELECT COUNT(*) FROM ranks'));

        $client->request('POST', $action, [
            '_token' => $this->csrfToken($client),
            'discord_roles' => [2 => 'discord-gardien'],
        ]);
        self::assertResponseRedirects('/app/serveurs/guild/configuration/ranks');
        self::assertSame('discord-gardien', $this->connection()->fetchOne('SELECT discord_id FROM ranks'));
    }

    public function testTemplateAdminCanPreviewAndApplyAnImport(): void
    {
        $client = self::createClient();
        $this->resetDatabase();
        [, , $template] = $this->seedAccess($client);

        $crawler = $this->upload(
            $client,
            '/app/modeles-catalogue/'.$template->id().'/configuration/stats/csv/apercu',
            "nom\nForce\n",
        );

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-testid="catalog-csv-operation"][data-action="create"]');
        $action = $crawler->filter('[data-testid="catalog-csv-preview"] form')->attr('action');
        self::assertIsString($action);

        $client->request('POST', $action, ['_token' => $this->csrfToken($client)]);

        self::assertResponseRedirects('/app/modeles-catalogue/'.$template->id().'/configuration/stats');
        self::assertSame('Force', $this->connection()->fetchOne(
            'SELECT name FROM catalog_template_stats WHERE template_id = ?',
            [$template->id()],
        ));
    }

    public function testAccessAndMutationGuardsApplyToBothTargets(): void
    {
        $anonymous = self::createClient();
        $anonymous->request('GET', '/app/serveurs/guild/configuration/stats/csv');
        self::assertResponseRedirects('/connexion/discord');

        self::ensureKernelShutdown();
        $client = self::createClient();
        $this->resetDatabase();
        [, $server, $template] = $this->seedAccess($client);
        $server->deactivate();
        $this->entityManager->flush();

        $client->request('GET', '/app/serveurs/guild/configuration/stats/csv/exemple');
        self::assertResponseIsSuccessful();
        $client->request('GET', '/app/serveurs/guild/configuration/stats/csv');
        self::assertResponseStatusCodeSame(409);

        $client->request('POST', '/app/modeles-catalogue/'.$template->id().'/configuration/stats/csv/apercu');
        self::assertResponseStatusCodeSame(403);
    }

    /**
     * @return array{DiscordUser, DiscordServer, CatalogTemplate}
     */
    private function seedAccess(KernelBrowser $client): array
    {
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $user = new DiscordUser('42', 'melaine', 'Melaine', null);
        $user->replaceGlobalRoles([DiscordUser::GLOBAL_ROLE_TEMPLATE_ADMIN]);
        $server = new DiscordServer('guild', 'Serveur');
        $this->entityManager->persist($user);
        $this->entityManager->persist($server);
        $this->entityManager->persist(new DiscordServerMember($user, $server, false, '8', true));
        $template = new CatalogTemplate('Modèle', createdBy: $user);
        $this->entityManager->persist($template);
        $this->entityManager->flush();

        $session = self::getContainer()->get('session.factory')->createSession();
        $session->set('gachamelia.discord_user_id', $user->id());
        $session->save();
        $client->getCookieJar()->set(new Cookie($session->getName(), $session->getId()));

        return [$user, $server, $template];
    }

    private function upload(KernelBrowser $client, string $uri, string $contents): \Symfony\Component\DomCrawler\Crawler
    {
        $path = tempnam(sys_get_temp_dir(), 'catalog-csv-');
        self::assertIsString($path);
        file_put_contents($path, $contents);

        return $client->request('POST', $uri, ['_token' => $this->csrfToken($client)], [
            'csv_file' => new UploadedFile($path, 'catalogue.csv', 'text/csv', null, true),
        ]);
    }

    private function csrfToken(KernelBrowser $client): string
    {
        $crawler = $client->request('GET', '/app');
        self::assertResponseIsSuccessful();
        $token = $crawler->filter('form[action="/deconnexion"] input[name="_token"]')->attr('value');
        self::assertIsString($token);

        return $token;
    }
}

final class CatalogCsvFakeDiscordResourcesProvider implements DiscordGuildResourcesProviderInterface
{
    public function resourcesForGuild(string $guildId, bool $fresh = false): array
    {
        return [
            'channels' => [],
            'roles' => [
                ['id' => 'discord-gardien', 'name' => 'Gardien', 'label' => '@Gardien', 'position' => 10, 'managed' => false],
                ['id' => 'managed-role', 'name' => 'Bot', 'label' => '@Bot', 'position' => 20, 'managed' => true],
            ],
        ];
    }
}
