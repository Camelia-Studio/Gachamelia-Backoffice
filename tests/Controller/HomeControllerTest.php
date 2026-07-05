<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class HomeControllerTest extends WebTestCase
{
    public function testHomePagePresentsGachameliaLanding(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('body[data-layout="landing"]');
        self::assertSelectorNotExists('[data-testid="backoffice-navbar"]');
        self::assertSelectorTextContains('h1', 'Chaque arrivée devient une invocation.');
        self::assertSelectorTextContains('body', 'Bot gacha communautaire');
        self::assertSelectorTextContains('body', 'On ne rejoint pas seulement le serveur');
        self::assertSelectorTextContains('body', 'Une carte personnage à garder sous la main');
        self::assertSelectorTextContains('body', 'Découvrir le projet');
        self::assertSelectorTextContains('body', 'Pour les membres');
        self::assertSelectorTextContains('body', 'Pour l’équipe');
        self::assertSelectorExists('[data-controller="mobile-menu"]');
        self::assertSelectorExists('a[href="#bot"][data-action="mobile-menu#navigate"]');
        self::assertSelectorExists('a[href="#fiche"][data-action="mobile-menu#navigate"]');
        self::assertSelectorExists('a[href="#espaces"][data-action="mobile-menu#navigate"]');
        self::assertSelectorExists('a[href="https://git.crystalyx.net/camelia-studio/Gachamelia/wiki"]');
        self::assertSelectorExists('a[href="https://git.crystalyx.net/camelia-studio/Gachamelia"]');
        self::assertSelectorExists('a[href="https://discord.gg/nBuZ9vJ"]');
        self::assertSelectorTextContains('body', 'Ouvrir le menu');
        self::assertSelectorTextContains('body', 'À conserver');
        self::assertSame(
            '/images/gachamelia-hero.jpg',
            $crawler->filter('[data-testid="hero-visual"]')->attr('src'),
        );
        self::assertSame(
            '/images/gachamelia-bot-avatar.png',
            $crawler->filter('[data-testid="bot-avatar-visual"]')->attr('src'),
        );
        foreach ($crawler->filter('img[src="/images/gachamelia-bot-avatar.png"]') as $avatar) {
            self::assertStringContainsString('rounded-full', $avatar->getAttribute('class'));
        }
        self::assertStringContainsString(
            'scroll-mt-24',
            $crawler->filter('#bot')->attr('class') ?? '',
        );
    }

    public function testHeroKeepsSingleDiscoverActionNearIntroCopy(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-testid="hero-actions"] a[href="#bot"]');
        self::assertSelectorNotExists('[data-testid="hero-actions"] a[href="https://git.crystalyx.net/camelia-studio/Gachamelia/wiki"]');
        self::assertSame(1, $crawler->filter('[data-testid="hero-actions"] a')->count());
        self::assertStringNotContainsString('Gitea du projet', $crawler->text());
        self::assertStringNotContainsString('Discord de l’asso', $crawler->text());
    }

    public function testHeroGachaSummaryHandlesLongRandomValues(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/');

        self::assertResponseIsSuccessful();

        $summary = $crawler->filter('[data-testid="hero-desktop-gacha-summary"]');
        self::assertSame(1, $summary->count());
        self::assertStringContainsString('w-[min(calc(100vw-3rem),48rem)]', $summary->attr('class') ?? '');

        $summaryGrid = $summary->filter('[data-testid="hero-desktop-gacha-summary-grid"]');
        self::assertStringContainsString(
            'grid-cols-[minmax(5.5rem,0.75fr)_minmax(12rem,1.6fr)_minmax(6rem,0.9fr)_minmax(7rem,1fr)]',
            $summaryGrid->attr('class') ?? '',
        );

        foreach ($summary->filter('[data-testid="hero-gacha-summary-card"]') as $card) {
            self::assertStringContainsString('min-w-0', $card->getAttribute('class'));
        }

        foreach ($summary->filter('[data-testid="hero-gacha-summary-value"]') as $value) {
            self::assertStringContainsString('break-words', $value->getAttribute('class'));
            self::assertStringNotContainsString('whitespace-nowrap', $value->getAttribute('class'));
        }
    }

    public function testHomePageExposesSeoMetadataAndDisablesTurbo(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSame(
            'Gachamélia - Bot gacha communautaire Discord',
            trim($crawler->filter('title')->text()),
        );
        self::assertSelectorExists('body[data-turbo="false"]');
        self::assertSame(
            'Gachamélia transforme les arrivées Discord en invocations gacha communautaires avec rareté, rôle, élément et fiche personnage.',
            $crawler->filter('meta[name="description"]')->attr('content'),
        );
        self::assertSame('index, follow', $crawler->filter('meta[name="robots"]')->attr('content'));
        self::assertSame('http://localhost/', $crawler->filter('link[rel="canonical"]')->attr('href'));
        self::assertSame('/images/gachamelia-bot-avatar.png', $crawler->filter('link[rel="icon"]')->attr('href'));
        self::assertSame('/site.webmanifest', $crawler->filter('link[rel="manifest"]')->attr('href'));
        self::assertSame(
            'Gachamélia - Bot gacha communautaire Discord',
            $crawler->filter('meta[property="og:title"]')->attr('content'),
        );
        self::assertSame('website', $crawler->filter('meta[property="og:type"]')->attr('content'));
        self::assertSame('http://localhost/images/gachamelia-hero.jpg', $crawler->filter('meta[property="og:image"]')->attr('content'));
        self::assertSame('summary_large_image', $crawler->filter('meta[name="twitter:card"]')->attr('content'));
        self::assertSelectorExists('script[type="application/ld+json"]');
        self::assertStringContainsString(
            '"applicationCategory":"Discord bot"',
            $crawler->filter('script[type="application/ld+json"]')->text(),
        );
    }

    public function testAssetUrlsUseSingleSubdirectoryPrefixWhenServedBelowBasePath(): void
    {
        $_ENV['APP_BASE_PATH'] = $_SERVER['APP_BASE_PATH'] = '/gachamelia';
        $_ENV['DEFAULT_URI'] = $_SERVER['DEFAULT_URI'] = 'https://cila.camelia-studio.org/gachamelia/';
        putenv('APP_BASE_PATH=/gachamelia');
        putenv('DEFAULT_URI=https://cila.camelia-studio.org/gachamelia/');

        self::ensureKernelShutdown();

        try {
            $client = static::createClient();

            $crawler = $client->request('GET', '/gachamelia/', server: [
                'HTTP_HOST' => 'cila.camelia-studio.org',
                'HTTPS' => 'on',
                'PHP_SELF' => '/gachamelia/index.php',
                'REQUEST_URI' => '/gachamelia/',
                'SCRIPT_FILENAME' => '/var/www/gachamelia/public/index.php',
                'SCRIPT_NAME' => '/gachamelia/index.php',
            ]);

            self::assertResponseIsSuccessful();
            self::assertSame(
                '/gachamelia/images/gachamelia-hero.jpg',
                $crawler->filter('[data-testid="hero-visual"]')->attr('src'),
            );
            self::assertSame(
                '/gachamelia/images/gachamelia-bot-avatar.png',
                $crawler->filter('[data-testid="bot-avatar-visual"]')->attr('src'),
            );
            self::assertSame(
                '/gachamelia/site.webmanifest',
                $crawler->filter('link[rel="manifest"]')->attr('href'),
            );
            self::assertSame(
                'https://cila.camelia-studio.org/gachamelia/images/gachamelia-hero.jpg',
                $crawler->filter('meta[property="og:image"]')->attr('content'),
            );
            self::assertStringNotContainsString('/gachamelia/gachamelia/', $client->getResponse()->getContent() ?: '');
        } finally {
            $_ENV['APP_BASE_PATH'] = $_SERVER['APP_BASE_PATH'] = '';
            $_ENV['DEFAULT_URI'] = $_SERVER['DEFAULT_URI'] = 'http://localhost';
            putenv('APP_BASE_PATH=');
            putenv('DEFAULT_URI=http://localhost');
            self::ensureKernelShutdown();
        }
    }

    public function testSiteManifestUsesRelativeUrlsForSubdirectoryDeployments(): void
    {
        $manifest = json_decode(
            file_get_contents(\dirname(__DIR__, 2).'/public/site.webmanifest') ?: '',
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        self::assertSame('./', $manifest['start_url']);
        self::assertSame('./', $manifest['scope']);
        self::assertSame('images/gachamelia-bot-avatar.png', $manifest['icons'][0]['src']);
    }

    public function testSeoUtilityEndpointsUseCurrentHost(): void
    {
        $client = static::createClient();

        $client->request('GET', '/robots.txt', server: ['HTTP_HOST' => 'gachamelia.example']);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'text/plain; charset=UTF-8');
        self::assertStringContainsString('Sitemap: http://gachamelia.example/sitemap.xml', $client->getResponse()->getContent() ?: '');

        $client->request('GET', '/sitemap.xml', server: ['HTTP_HOST' => 'gachamelia.example']);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'application/xml; charset=UTF-8');
        self::assertStringContainsString('<loc>http://gachamelia.example/</loc>', $client->getResponse()->getContent() ?: '');
    }
}
