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
        self::assertSelectorTextContains('body', 'Ouvrir le menu');
        self::assertSelectorTextContains('body', 'À conserver');
        self::assertSame(
            'https://camelia-studio.org/wp-content/uploads/2025/07/1080JPEG.jpg',
            $crawler->filter('[data-testid="hero-visual"]')->attr('src'),
        );
    }
}
