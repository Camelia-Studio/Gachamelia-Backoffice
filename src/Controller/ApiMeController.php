<?php

namespace App\Controller;

use App\Security\BotApiUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class ApiMeController extends AbstractController
{
    #[Route('/api/me', name: 'api_me', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof BotApiUser) {
            throw new AccessDeniedHttpException('Authenticated bot client required.');
        }

        return $this->json([
            'client_id' => $user->getUserIdentifier(),
            'roles' => $user->getRoles(),
        ]);
    }
}
