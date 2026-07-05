<?php

namespace App\Controller;

use App\Backoffice\BackofficeSession;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class BackofficeController extends AbstractController
{
    #[Route('/app', name: 'app_dashboard', methods: ['GET'])]
    public function dashboard(BackofficeSession $backofficeSession): Response
    {
        if (!$backofficeSession->isAuthenticated()) {
            return $this->redirectToRoute('app_discord_login');
        }

        return $this->render('backoffice/dashboard.html.twig', [
            'profile' => $backofficeSession->profile(),
            'guilds' => $backofficeSession->guilds(),
        ]);
    }

    #[Route('/app/serveurs/{guildId}/configuration', name: 'app_server_configuration', methods: ['GET'])]
    public function configuration(string $guildId, BackofficeSession $backofficeSession): Response
    {
        if (!$backofficeSession->isAuthenticated()) {
            return $this->redirectToRoute('app_discord_login');
        }

        $guild = $this->findGuildOr404($backofficeSession, $guildId);
        if (true !== ($guild['canManageConfiguration'] ?? false)) {
            throw new AccessDeniedHttpException('Administrator permission required for this server.');
        }

        return $this->render('backoffice/server_configuration.html.twig', [
            'guild' => $guild,
        ]);
    }

    #[Route('/app/serveurs/{guildId}/fiche-personnage', name: 'app_character_sheet', methods: ['GET'])]
    public function characterSheet(string $guildId, BackofficeSession $backofficeSession): Response
    {
        if (!$backofficeSession->isAuthenticated()) {
            return $this->redirectToRoute('app_discord_login');
        }

        return $this->render('backoffice/character_sheet.html.twig', [
            'guild' => $this->findGuildOr404($backofficeSession, $guildId),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function findGuildOr404(BackofficeSession $backofficeSession, string $guildId): array
    {
        $guild = $backofficeSession->findGuild($guildId);
        if (null === $guild) {
            throw new NotFoundHttpException('Server is not available in this backoffice session.');
        }

        return $guild;
    }
}
