<?php

namespace App\Controller;

use App\Enum\GachaElementEnum;
use App\Enum\GachaRoleEnum;
use App\Enum\GachaStatEnum;
use Random\RandomException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    /**
     * @throws RandomException
     */
    #[Route('/', name: 'app_home')]
    public function index(): Response
    {
        $gachaRole = GachaRoleEnum::random();
        $gachaElement = GachaElementEnum::random();
        $gachaStat = GachaStatEnum::random();
        $gachaRarity = random_int(1,5);

        return $this->render('home/index.html.twig', [
            'gachaRole' => $gachaRole,
            'gachaElement' => $gachaElement,
            'gachaStat' => $gachaStat,
            'gachaRarity' => $gachaRarity,
        ]);
    }

    #[Route('/robots.txt', name: 'app_robots', methods: ['GET'])]
    public function robots(Request $request): Response
    {
        $baseUrl = $this->getBaseUrl($request);

        return $this->render('seo/robots.txt.twig', [
            'baseUrl' => $baseUrl,
        ], new Response(headers: ['Content-Type' => 'text/plain; charset=UTF-8']));
    }

    #[Route('/sitemap.xml', name: 'app_sitemap', methods: ['GET'])]
    public function sitemap(Request $request): Response
    {
        $homeUrl = htmlspecialchars($this->getBaseUrl($request), ENT_XML1);

        // On va render le seo/sitemap.xml.twig
        return $this->render('seo/sitemap.xml.twig', [
            'homeUrl' => $homeUrl,
        ], new Response(headers: ['Content-Type' => 'application/xml; charset=UTF-8']));
    }

    private function getBaseUrl(Request $request): string
    {
        return rtrim($request->getSchemeAndHttpHost().$request->getBasePath(), '/').'/';
    }
}
