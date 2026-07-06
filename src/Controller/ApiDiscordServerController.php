<?php

namespace App\Controller;

use App\Entity\DiscordServer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ApiDiscordServerController extends AbstractController
{
    #[Route('/api/discord-servers', name: 'api_discord_servers_upsert', methods: ['POST'])]
    public function upsert(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $payload = $this->jsonPayload($request);
        if (null === $payload) {
            return $this->json(['error' => 'invalid_json'], Response::HTTP_BAD_REQUEST);
        }

        $discordId = $this->requiredString($payload, 'discord_id');
        $name = $this->requiredString($payload, 'name');
        $icon = $this->nullableString($payload, 'icon');
        if (null === $discordId || null === $name) {
            return $this->json(['error' => 'invalid_payload'], Response::HTTP_BAD_REQUEST);
        }

        $repository = $entityManager->getRepository(DiscordServer::class);
        $server = $repository->findOneBy(['discordId' => $discordId]);
        $created = false;

        if (!$server instanceof DiscordServer) {
            $server = new DiscordServer($discordId, $name, $icon);
            $entityManager->persist($server);
            $created = true;
        } else {
            $server->refreshCache($name, $icon);
        }

        $entityManager->flush();

        return $this->json([
            'server' => [
                'discord_id' => $server->discordId(),
                'name' => $server->name(),
                'icon' => $server->icon(),
            ],
        ], $created ? Response::HTTP_CREATED : Response::HTTP_OK);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function jsonPayload(Request $request): ?array
    {
        try {
            $payload = $request->toArray();
        } catch (\Throwable) {
            return null;
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function requiredString(array $payload, string $key): ?string
    {
        $value = $payload[$key] ?? null;

        if (!\is_string($value)) {
            return null;
        }

        $value = trim($value);

        return '' !== $value ? $value : null;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function nullableString(array $payload, string $key): ?string
    {
        $value = $payload[$key] ?? null;

        if (null === $value) {
            return null;
        }

        if (!\is_string($value)) {
            return null;
        }

        $value = trim($value);

        return '' !== $value ? $value : null;
    }
}
