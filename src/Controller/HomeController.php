<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(): Response
    {
        return $this->render('home/index.html.twig');
    }

    #[Route('/robots.txt', name: 'app_robots', methods: ['GET'])]
    public function robots(Request $request): Response
    {
        $baseUrl = $this->getBaseUrl($request);

        return new Response(
            <<<ROBOTS
            User-agent: *
            Allow: /

            Sitemap: {$baseUrl}sitemap.xml

            ROBOTS,
            headers: ['Content-Type' => 'text/plain; charset=UTF-8'],
        );
    }

    #[Route('/sitemap.xml', name: 'app_sitemap', methods: ['GET'])]
    public function sitemap(Request $request): Response
    {
        $homeUrl = htmlspecialchars($this->getBaseUrl($request), ENT_XML1);

        return new Response(
            <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
                <url>
                    <loc>{$homeUrl}</loc>
                    <changefreq>monthly</changefreq>
                    <priority>1.0</priority>
                </url>
            </urlset>

            XML,
            headers: ['Content-Type' => 'application/xml; charset=UTF-8'],
        );
    }

    private function getBaseUrl(Request $request): string
    {
        return rtrim($request->getSchemeAndHttpHost().$request->getBasePath(), '/').'/';
    }
}
