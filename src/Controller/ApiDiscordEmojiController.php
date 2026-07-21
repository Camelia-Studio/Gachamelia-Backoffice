<?php

namespace App\Controller;

use App\Entity\DiscordEmoji;
use App\Entity\DiscordServer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ApiDiscordEmojiController extends AbstractController
{
    #[Route('/api/discord-emojis', name: 'api_discord_emojis_refresh', methods: ['PUT'])]
    public function refresh(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $payload = $this->jsonPayload($request);
        if (null === $payload) {
            return $this->json(['error' => 'invalid_json'], Response::HTTP_BAD_REQUEST);
        }

        $source = $this->requiredString($payload, 'source');
        if (!\in_array($source, [DiscordEmoji::SOURCE_SERVER, DiscordEmoji::SOURCE_BOT], true)) {
            return $this->json(['error' => 'invalid_source'], Response::HTTP_BAD_REQUEST);
        }

        $server = null;
        $cacheKey = DiscordEmoji::APPLICATION_CACHE_KEY;
        if (DiscordEmoji::SOURCE_SERVER === $source) {
            $discordServerId = $this->requiredString($payload, 'discord_server_id');
            if (null === $discordServerId) {
                return $this->json(['error' => 'invalid_payload'], Response::HTTP_BAD_REQUEST);
            }

            $server = $entityManager->getRepository(DiscordServer::class)->findOneBy(['discordId' => $discordServerId]);
            if (!$server instanceof DiscordServer) {
                return $this->json(['error' => 'server_not_found'], Response::HTTP_NOT_FOUND);
            }
            if (!$server->active()) {
                return $this->json(['error' => 'server_inactive'], Response::HTTP_CONFLICT);
            }

            $cacheKey = DiscordEmoji::serverCacheKey($server);
        }

        $emojiPayloads = $payload['emojis'] ?? null;
        if (!\is_array($emojiPayloads)) {
            return $this->json(['error' => 'invalid_payload'], Response::HTTP_BAD_REQUEST);
        }

        $now = new \DateTimeImmutable();
        $repository = $entityManager->getRepository(DiscordEmoji::class);
        $existingByDiscordId = [];
        foreach ($repository->findBy(['cacheKey' => $cacheKey, 'source' => $source]) as $emoji) {
            if ($emoji instanceof DiscordEmoji) {
                $existingByDiscordId[$emoji->discordId()] = $emoji;
            }
        }

        $received = 0;
        $available = 0;
        $seenDiscordIds = [];
        foreach ($emojiPayloads as $emojiPayload) {
            if (!\is_array($emojiPayload)) {
                continue;
            }

            $discordId = $this->requiredString($emojiPayload, 'id');
            $name = $this->requiredString($emojiPayload, 'name');
            if (null === $discordId || null === $name) {
                continue;
            }

            $animated = true === ($emojiPayload['animated'] ?? false);
            $isAvailable = false !== ($emojiPayload['available'] ?? true);
            $emoji = $existingByDiscordId[$discordId] ?? null;
            if (!$emoji instanceof DiscordEmoji) {
                $emoji = new DiscordEmoji($server, $cacheKey, $source, $discordId, $name, $animated, $isAvailable, $now);
                $entityManager->persist($emoji);
            } else {
                $emoji->refresh($name, $animated, $isAvailable, $now);
            }

            $received++;
            if ($isAvailable) {
                $available++;
            }
            $seenDiscordIds[$discordId] = true;
        }

        foreach ($existingByDiscordId as $discordId => $emoji) {
            if (!isset($seenDiscordIds[$discordId])) {
                $emoji->markUnavailable($now);
            }
        }

        $entityManager->flush();

        return $this->json([
            'cache' => [
                'source' => $source,
                'cache_key' => $cacheKey,
                'received' => $received,
                'available' => $available,
            ],
        ]);
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
}
