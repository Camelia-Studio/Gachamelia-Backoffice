<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class HomeControllerTest extends WebTestCase
{
    public function testHomePagePresentsGachameliaLanding(): void
    {
        $client = self::createClient();

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
        self::assertSelectorExists('[data-mobile-menu]');
        self::assertSelectorExists('a[href="#bot"][data-mobile-menu-link]');
        self::assertSelectorExists('a[href="#fiche"][data-mobile-menu-link]');
        self::assertSelectorExists('a[href="#espaces"][data-mobile-menu-link]');
        self::assertSelectorExists('a[href="https://git.crystalyx.net/camelia-studio/Gachamelia/wiki"]');
        self::assertSelectorExists('a[href="https://git.crystalyx.net/camelia-studio/Gachamelia"]');
        self::assertSelectorExists('a[href="https://discord.gg/nBuZ9vJ"]');
        self::assertSelectorExists('a[href="/app"][data-testid="landing-backoffice-link"]');
        self::assertSelectorTextContains('body', 'Backoffice');
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
        self::assertSame(
            $crawler->filter('img[src="/images/gachamelia-bot-avatar.png"]')->count(),
            $crawler->filter('.lp-brand img, .lp-drawer-brand img, img.lp-avatar')->count(),
            'Every bot avatar must use a component that rounds it.',
        );
        self::assertStringContainsString(
            'lp-section-anchor',
            $crawler->filter('#bot')->attr('class') ?? '',
        );
    }

    public function testHeroKeepsSingleDiscoverActionNearIntroCopy(): void
    {
        $client = self::createClient();

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
        $client = self::createClient();

        $crawler = $client->request('GET', '/');

        self::assertResponseIsSuccessful();

        $summary = $crawler->filter('[data-testid="hero-desktop-gacha-summary"]');
        self::assertSame(1, $summary->count());
        self::assertStringContainsString('lp-summary--desktop', $summary->attr('class') ?? '');

        $summaryGrid = $summary->filter('[data-testid="hero-desktop-gacha-summary-grid"]');
        self::assertStringContainsString('lp-summary-grid', $summaryGrid->attr('class') ?? '');

        foreach ($summary->filter('[data-testid="hero-gacha-summary-card"]') as $card) {
            if (!$card instanceof \DOMElement) {
                self::fail('Expected a summary card element.');
            }

            self::assertStringContainsString('lp-stat', $card->getAttribute('class'));
        }

        foreach ($summary->filter('[data-testid="hero-gacha-summary-value"]') as $value) {
            if (!$value instanceof \DOMElement) {
                self::fail('Expected a summary value element.');
            }

            self::assertStringContainsString('lp-stat-value', $value->getAttribute('class'));
        }

        // Les valeurs aléatoires peuvent être longues : la grille borne la largeur et le texte passe à la ligne.
        $stylesheet = file_get_contents(\dirname(__DIR__, 2).'/assets/styles/app.css');
        self::assertIsString($stylesheet);
        self::assertStringContainsString('width: min(calc(100vw - 3rem), 48rem);', $stylesheet);
        self::assertStringContainsString(
            'grid-template-columns: minmax(5.5rem, 0.75fr) minmax(12rem, 1.6fr) minmax(6rem, 0.9fr) minmax(7rem, 1fr);',
            $stylesheet,
        );
        self::assertMatchesRegularExpression('/\.lp-stat\s*\{[^}]*min-width:\s*0;/', $stylesheet);
        self::assertMatchesRegularExpression('/\.lp-stat-value\s*\{[^}]*overflow-wrap:\s*anywhere;/', $stylesheet);
        self::assertStringNotContainsString('white-space: nowrap', $stylesheet);
    }

    public function testHomePageExposesSeoMetadata(): void
    {
        $client = self::createClient();

        $crawler = $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSame(
            'Gachamélia - Bot gacha communautaire Discord',
            trim($crawler->filter('title')->text()),
        );
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
            $client = self::createClient();

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
            $importMap = json_decode($crawler->filter('script[type="importmap"]')->text(), true, flags: JSON_THROW_ON_ERROR);
            self::assertStringStartsWith('/gachamelia/assets/app-', $importMap['imports']['app']);
            self::assertSame(2, $crawler->filter('link[rel="stylesheet"][href^="/gachamelia/assets/"]')->count());
            foreach ($crawler->filter('link[rel="stylesheet"][href^="/"]') as $stylesheet) {
                self::assertInstanceOf(\DOMElement::class, $stylesheet);
                self::assertStringStartsWith('/gachamelia/assets/styles/', $stylesheet->getAttribute('href'));
            }
            $content = $client->getResponse()->getContent();
            self::assertIsString($content);
            self::assertStringNotContainsString('/gachamelia/gachamelia/', $content);
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
        $content = file_get_contents(\dirname(__DIR__, 2).'/public/site.webmanifest');
        self::assertIsString($content);
        $manifest = json_decode($content, true, flags: JSON_THROW_ON_ERROR);

        self::assertSame('./', $manifest['start_url']);
        self::assertSame('./', $manifest['scope']);
        self::assertSame('images/gachamelia-bot-avatar.png', $manifest['icons'][0]['src']);
    }

    public function testSeoUtilityEndpointsUseCurrentHost(): void
    {
        $client = self::createClient();

        $client->request('GET', '/robots.txt', server: ['HTTP_HOST' => 'gachamelia.example']);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'text/plain; charset=UTF-8');
        $robotsContent = $client->getResponse()->getContent();
        self::assertIsString($robotsContent);
        self::assertStringContainsString('Sitemap: http://gachamelia.example/sitemap.xml', $robotsContent);

        $client->request('GET', '/sitemap.xml', server: ['HTTP_HOST' => 'gachamelia.example']);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'application/xml; charset=UTF-8');
        $sitemapContent = $client->getResponse()->getContent();
        self::assertIsString($sitemapContent);
        self::assertStringContainsString('<loc>http://gachamelia.example/</loc>', $sitemapContent);
    }
}
