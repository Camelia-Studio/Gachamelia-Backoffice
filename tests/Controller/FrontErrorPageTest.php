<?php

namespace App\Tests\Controller;

use Symfony\Bridge\Twig\ErrorRenderer\TwigErrorRenderer;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Twig\Environment;

final class FrontErrorPageTest extends WebTestCase
{
    public function testNotFoundErrorPageUsesBackofficeLayout(): void
    {
        self::bootKernel(['debug' => false]);

        /** @var Environment $twig */
        $twig = static::getContainer()->get('twig');
        $flattenException = (new TwigErrorRenderer($twig, debug: false))->render(new NotFoundHttpException());
        $crawler = new Crawler($flattenException->getAsString());

        self::assertSame(404, $flattenException->getStatusCode());
        self::assertSame(1, $crawler->filter('[data-testid="backoffice-layout"]')->count());
        self::assertSame(1, $crawler->filter('[data-testid="backoffice-navbar"]')->count());
        self::assertStringContainsString('Gachamélia', $crawler->filter('[data-testid="backoffice-brand"]')->text());
        self::assertSame(1, $crawler->filter('[data-testid="backoffice-public-home-action"]')->count());
        self::assertSame(1, $crawler->filter('[data-testid="backoffice-error-page"]')->count());
        self::assertStringContainsString('404', $crawler->filter('[data-testid="backoffice-error-code"]')->text());
        self::assertStringContainsString('Invocation introuvable', $crawler->filter('[data-testid="backoffice-error-title"]')->text());
        self::assertSame('Page introuvable - Gachamélia Backoffice', trim($crawler->filter('title')->text()));
        self::assertSame('noindex, nofollow', $crawler->filter('meta[name="robots"]')->attr('content'));
        self::assertSame(1, $crawler->filter('[data-testid="backoffice-error-home-action"]')->count());
        self::assertStringNotContainsString('No route found', $crawler->text());
    }

    public function testGenericFrontErrorTemplateCanRenderServerErrors(): void
    {
        self::bootKernel();

        /** @var Environment $twig */
        $twig = static::getContainer()->get('twig');

        if (!$twig->getLoader()->exists('@Twig/Exception/error.html.twig')) {
            self::fail('The generic backoffice error template must exist.');
        }

        $html = $twig->render('@Twig/Exception/error.html.twig', [
            'status_code' => 500,
            'status_text' => 'Internal Server Error',
        ]);

        self::assertStringContainsString('data-testid="backoffice-layout"', $html);
        self::assertStringContainsString('data-testid="backoffice-navbar"', $html);
        self::assertStringContainsString('data-testid="backoffice-error-page"', $html);
        self::assertStringContainsString('data-testid="backoffice-error-code">500', $html);
        self::assertStringContainsString('Le tirage a déraillé', $html);
        self::assertStringContainsString('data-testid="backoffice-error-home-action"', $html);
    }
}
