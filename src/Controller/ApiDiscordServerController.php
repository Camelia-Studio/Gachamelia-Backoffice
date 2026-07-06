<?php

namespace App\Controller;

use App\Entity\ByeMessage;
use App\Entity\CharacterRole;
use App\Entity\DiscordServer;
use App\Entity\Element;
use App\Entity\Rank;
use App\Entity\RankStat;
use App\Entity\Stat;
use App\Entity\WelcomeMessage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ApiDiscordServerController extends AbstractController
{
    #[Route('/api/discord-servers/{discordId}/catalogue', name: 'api_discord_servers_catalogue', methods: ['GET'])]
    public function catalogue(string $discordId, EntityManagerInterface $entityManager): JsonResponse
    {
        $server = $entityManager->getRepository(DiscordServer::class)->findOneBy(['discordId' => $discordId]);
        if (!$server instanceof DiscordServer) {
            return $this->json(['error' => 'server_not_found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'server' => $this->serverPayload($server),
            'catalogue' => $this->cataloguePayload($entityManager, $server),
        ]);
    }

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
            'server' => $this->serverPayload($server),
        ], $created ? Response::HTTP_CREATED : Response::HTTP_OK);
    }

    #[Route('/api/discord-servers/{discordId}/settings', name: 'api_discord_servers_settings_update', methods: ['PATCH'])]
    public function updateSettings(string $discordId, Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $payload = $this->jsonPayload($request);
        if (null === $payload) {
            return $this->json(['error' => 'invalid_json'], Response::HTTP_BAD_REQUEST);
        }

        $server = $entityManager->getRepository(DiscordServer::class)->findOneBy(['discordId' => $discordId]);
        if (!$server instanceof DiscordServer) {
            return $this->json(['error' => 'server_not_found'], Response::HTTP_NOT_FOUND);
        }

        $server->updateSettings(
            $this->nullablePayloadStringOrCurrent($payload, 'welcome_channel_id', $server->welcomeChannelId()),
            $this->nullablePayloadStringOrCurrent($payload, 'bye_channel_id', $server->byeChannelId()),
            $this->nullablePayloadStringOrCurrent($payload, 'staff_role_id', $server->staffRoleId()),
        );
        $entityManager->flush();

        return $this->json([
            'server' => $this->serverPayload($server),
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

    /**
     * @param array<string, mixed> $payload
     */
    private function nullablePayloadStringOrCurrent(array $payload, string $key, ?string $current): ?string
    {
        if (!\array_key_exists($key, $payload)) {
            return $current;
        }

        return $this->nullableString($payload, $key);
    }

    /**
     * @return array{discord_id: string, name: string, icon: ?string, settings: array{welcome_channel_id: ?string, bye_channel_id: ?string, staff_role_id: ?string}}
     */
    private function serverPayload(DiscordServer $server): array
    {
        return [
            'discord_id' => $server->discordId(),
            'name' => $server->name(),
            'icon' => $server->icon(),
            'settings' => [
                'welcome_channel_id' => $server->welcomeChannelId(),
                'bye_channel_id' => $server->byeChannelId(),
                'staff_role_id' => $server->staffRoleId(),
            ],
        ];
    }

    /**
     * @return array{
     *     ranks: list<array{id: int, discord_id: string, name: string, percentage: int, bye_title: ?string, is_staff: bool, stats: list<array{id: int, name: string, percentage: int}>, welcome_messages: list<array{id: int, message: string}>, bye_messages: list<array{id: int, message: string}>}>,
     *     roles: list<array{id: int, name: string, percentage: int, emoji: array{source: string, unicode: ?string, id: ?string, name: ?string, animated: bool, markup: string, cdn_url: ?string}}>,
     *     stats: list<array{id: int, name: string}>,
     *     elements: list<array{id: int, name: string, emoji: array{source: string, unicode: ?string, id: ?string, name: ?string, animated: bool, markup: string, cdn_url: ?string}}>
     * }
     */
    private function cataloguePayload(EntityManagerInterface $entityManager, DiscordServer $server): array
    {
        return [
            'ranks' => array_map(
                fn (Rank $rank): array => [
                    'id' => (int) $rank->id(),
                    'discord_id' => $rank->discordId(),
                    'name' => $rank->name(),
                    'percentage' => $rank->percentage(),
                    'bye_title' => $rank->byeTitle(),
                    'is_staff' => $rank->isStaff(),
                    'stats' => $this->rankStatsPayload($entityManager, $rank),
                    'welcome_messages' => $this->welcomeMessagesPayload($entityManager, $server, $rank),
                    'bye_messages' => $this->byeMessagesPayload($entityManager, $server, $rank),
                ],
                $entityManager->getRepository(Rank::class)->findBy(['server' => $server], ['percentage' => 'ASC', 'name' => 'ASC']),
            ),
            'roles' => array_map(
                static fn (CharacterRole $role): array => [
                    'id' => (int) $role->id(),
                    'name' => $role->name(),
                    'percentage' => $role->percentage(),
                    'emoji' => [
                        'source' => $role->emojiSource(),
                        'unicode' => $role->emojiUnicode(),
                        'id' => $role->emojiId(),
                        'name' => $role->emojiName(),
                        'animated' => $role->emojiAnimated(),
                        'markup' => $role->emojiMarkup(),
                        'cdn_url' => $role->emojiCdnUrl(),
                    ],
                ],
                $entityManager->getRepository(CharacterRole::class)->findBy(['server' => $server], ['percentage' => 'ASC', 'name' => 'ASC']),
            ),
            'stats' => array_map(
                static fn (Stat $stat): array => [
                    'id' => (int) $stat->id(),
                    'name' => $stat->name(),
                ],
                $entityManager->getRepository(Stat::class)->findBy(['server' => $server], ['name' => 'ASC']),
            ),
            'elements' => array_map(
                static fn (Element $element): array => [
                    'id' => (int) $element->id(),
                    'name' => $element->name(),
                    'emoji' => [
                        'source' => $element->emojiSource(),
                        'unicode' => $element->emojiUnicode(),
                        'id' => $element->emojiId(),
                        'name' => $element->emojiName(),
                        'animated' => $element->emojiAnimated(),
                        'markup' => $element->emojiMarkup(),
                        'cdn_url' => $element->emojiCdnUrl(),
                    ],
                ],
                $entityManager->getRepository(Element::class)->findBy(['server' => $server], ['name' => 'ASC']),
            ),
        ];
    }

    /**
     * @return list<array{id: int, name: string, percentage: int}>
     */
    private function rankStatsPayload(EntityManagerInterface $entityManager, Rank $rank): array
    {
        return array_map(
            static fn (RankStat $rankStat): array => [
                'id' => (int) $rankStat->stat()->id(),
                'name' => $rankStat->stat()->name(),
                'percentage' => $rankStat->percentage(),
            ],
            $entityManager->getRepository(RankStat::class)->findBy(['rank' => $rank], ['percentage' => 'ASC']),
        );
    }

    /**
     * @return list<array{id: int, message: string}>
     */
    private function welcomeMessagesPayload(EntityManagerInterface $entityManager, DiscordServer $server, Rank $rank): array
    {
        return array_map(
            static fn (WelcomeMessage $message): array => [
                'id' => (int) $message->id(),
                'message' => $message->message(),
            ],
            $entityManager->getRepository(WelcomeMessage::class)->findBy(['server' => $server, 'rank' => $rank], ['id' => 'ASC']),
        );
    }

    /**
     * @return list<array{id: int, message: string}>
     */
    private function byeMessagesPayload(EntityManagerInterface $entityManager, DiscordServer $server, Rank $rank): array
    {
        return array_map(
            static fn (ByeMessage $message): array => [
                'id' => (int) $message->id(),
                'message' => $message->message(),
            ],
            $entityManager->getRepository(ByeMessage::class)->findBy(['server' => $server, 'rank' => $rank], ['id' => 'ASC']),
        );
    }
}
