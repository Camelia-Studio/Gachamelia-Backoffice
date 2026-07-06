<?php

namespace App\Controller;

use App\Entity\CharacterRole;
use App\Entity\DiscordServer;
use App\Entity\Element;
use App\Entity\GachaUser;
use App\Entity\Rank;
use App\Entity\Stat;
use App\Entity\UserStat;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ApiDiscordServerUserController extends AbstractController
{
    #[Route('/api/discord-servers/{discordId}/users/{userDiscordId}', name: 'api_discord_server_users_ensure', methods: ['PUT'])]
    public function ensure(
        string $discordId,
        string $userDiscordId,
        Request $request,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        $payload = $this->jsonPayload($request);
        if (null === $payload) {
            return $this->json(['error' => 'invalid_json'], Response::HTTP_BAD_REQUEST);
        }

        $server = $this->serverOr404($entityManager, $discordId);
        if (!$server instanceof DiscordServer) {
            return $this->json(['error' => 'server_not_found'], Response::HTTP_NOT_FOUND);
        }

        $user = $entityManager->getRepository(GachaUser::class)->findOneBy(['server' => $server, 'discordId' => $userDiscordId]);
        $created = false;
        if (!$user instanceof GachaUser) {
            $user = new GachaUser($server, $userDiscordId);
            $entityManager->persist($user);
            $created = true;
        }

        $assignmentError = $this->applyEnsureAssignments($entityManager, $server, $user, $payload);
        if (null !== $assignmentError) {
            return $assignmentError;
        }

        if (false !== ($payload['initialize_stats'] ?? true)) {
            $this->initializeMissingStats($entityManager, $server, $user);
        }

        $entityManager->flush();

        return $this->json([
            'user' => $this->userPayload($entityManager, $user),
        ], $created ? Response::HTTP_CREATED : Response::HTTP_OK);
    }

    #[Route('/api/discord-servers/{discordId}/users/{userDiscordId}', name: 'api_discord_server_users_update', methods: ['PATCH'])]
    public function update(
        string $discordId,
        string $userDiscordId,
        Request $request,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        $payload = $this->jsonPayload($request);
        if (null === $payload) {
            return $this->json(['error' => 'invalid_json'], Response::HTTP_BAD_REQUEST);
        }

        $server = $this->serverOr404($entityManager, $discordId);
        if (!$server instanceof DiscordServer) {
            return $this->json(['error' => 'server_not_found'], Response::HTTP_NOT_FOUND);
        }

        $user = $this->userOr404($entityManager, $server, $userDiscordId);
        if (!$user instanceof GachaUser) {
            return $this->json(['error' => 'user_not_found'], Response::HTTP_NOT_FOUND);
        }

        $assignmentError = $this->applyExplicitAssignments($entityManager, $server, $user, $payload);
        if (null !== $assignmentError) {
            return $assignmentError;
        }

        $entityManager->flush();

        return $this->json([
            'user' => $this->userPayload($entityManager, $user),
        ]);
    }

    #[Route('/api/discord-servers/{discordId}/users/{userDiscordId}/stats', name: 'api_discord_server_users_stats_upsert', methods: ['PUT'])]
    public function upsertStats(
        string $discordId,
        string $userDiscordId,
        Request $request,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        $payload = $this->jsonPayload($request);
        if (null === $payload) {
            return $this->json(['error' => 'invalid_json'], Response::HTTP_BAD_REQUEST);
        }

        $server = $this->serverOr404($entityManager, $discordId);
        if (!$server instanceof DiscordServer) {
            return $this->json(['error' => 'server_not_found'], Response::HTTP_NOT_FOUND);
        }

        $user = $this->userOr404($entityManager, $server, $userDiscordId);
        if (!$user instanceof GachaUser) {
            return $this->json(['error' => 'user_not_found'], Response::HTTP_NOT_FOUND);
        }

        $stats = $payload['stats'] ?? null;
        if (!\is_array($stats)) {
            return $this->json(['error' => 'invalid_payload'], Response::HTTP_BAD_REQUEST);
        }

        foreach ($stats as $statPayload) {
            if (!\is_array($statPayload)) {
                return $this->json(['error' => 'invalid_payload'], Response::HTTP_BAD_REQUEST);
            }

            $statId = $this->positiveInt($statPayload, 'id');
            $value = $this->intValue($statPayload, 'value');
            if (null === $statId || null === $value) {
                return $this->json(['error' => 'invalid_payload'], Response::HTTP_BAD_REQUEST);
            }

            $stat = $this->statOr404($entityManager, $server, $statId);
            if (!$stat instanceof Stat) {
                return $this->json(['error' => 'stat_not_found'], Response::HTTP_NOT_FOUND);
            }

            $userStat = $entityManager->getRepository(UserStat::class)->findOneBy(['user' => $user, 'stat' => $stat]);
            if (!$userStat instanceof UserStat) {
                $entityManager->persist(new UserStat($user, $stat, $value));
            } else {
                $userStat->updateValue($value);
            }
        }

        $entityManager->flush();

        return $this->json([
            'user' => $this->userPayload($entityManager, $user),
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function applyEnsureAssignments(
        EntityManagerInterface $entityManager,
        DiscordServer $server,
        GachaUser $user,
        array $payload,
    ): ?JsonResponse {
        $assignmentError = $this->applyExplicitAssignments($entityManager, $server, $user, $payload);
        if (null !== $assignmentError) {
            return $assignmentError;
        }

        if (null === $user->rank()) {
            $rank = $this->defaultRank($entityManager, $server);
            if (!$rank instanceof Rank) {
                return $this->json(['error' => 'rank_catalogue_empty'], Response::HTTP_CONFLICT);
            }
            $user->updateRank($rank);
        }

        if (null === $user->role()) {
            $role = $this->defaultRole($entityManager, $server);
            if (!$role instanceof CharacterRole) {
                return $this->json(['error' => 'role_catalogue_empty'], Response::HTTP_CONFLICT);
            }
            $user->updateRole($role);
        }

        if ($user->elements()->isEmpty()) {
            $element = $this->defaultElement($entityManager, $server);
            if (!$element instanceof Element) {
                return $this->json(['error' => 'element_catalogue_empty'], Response::HTTP_CONFLICT);
            }
            $user->addElement($element);
        }

        return null;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function applyExplicitAssignments(
        EntityManagerInterface $entityManager,
        DiscordServer $server,
        GachaUser $user,
        array $payload,
    ): ?JsonResponse {
        if (true === ($payload['staff'] ?? false)) {
            $rank = $this->staffRank($entityManager, $server);
            if (!$rank instanceof Rank) {
                return $this->json(['error' => 'staff_rank_not_found'], Response::HTTP_CONFLICT);
            }
            $user->updateRank($rank);
        } elseif (\array_key_exists('rank_id', $payload)) {
            $rankId = $this->positiveInt($payload, 'rank_id');
            if (null === $rankId) {
                return $this->json(['error' => 'invalid_payload'], Response::HTTP_BAD_REQUEST);
            }

            $rank = $this->rankOr404($entityManager, $server, $rankId);
            if (!$rank instanceof Rank) {
                return $this->json(['error' => 'rank_not_found'], Response::HTTP_NOT_FOUND);
            }
            $user->updateRank($rank);
        }

        if (\array_key_exists('role_id', $payload)) {
            $roleId = $this->positiveInt($payload, 'role_id');
            if (null === $roleId) {
                return $this->json(['error' => 'invalid_payload'], Response::HTTP_BAD_REQUEST);
            }

            $role = $this->roleOr404($entityManager, $server, $roleId);
            if (!$role instanceof CharacterRole) {
                return $this->json(['error' => 'role_not_found'], Response::HTTP_NOT_FOUND);
            }
            $user->updateRole($role);
        }

        if (\array_key_exists('element_ids', $payload)) {
            $elementIds = $payload['element_ids'];
            if (!\is_array($elementIds)) {
                return $this->json(['error' => 'invalid_payload'], Response::HTTP_BAD_REQUEST);
            }

            $elements = [];
            foreach ($elementIds as $elementId) {
                if (!\is_int($elementId) && !(\is_string($elementId) && ctype_digit($elementId))) {
                    return $this->json(['error' => 'invalid_payload'], Response::HTTP_BAD_REQUEST);
                }

                $element = $this->elementOr404($entityManager, $server, (int) $elementId);
                if (!$element instanceof Element) {
                    return $this->json(['error' => 'element_not_found'], Response::HTTP_NOT_FOUND);
                }
                $elements[] = $element;
            }
            $user->replaceElements($elements);
        }

        return null;
    }

    private function initializeMissingStats(EntityManagerInterface $entityManager, DiscordServer $server, GachaUser $user): void
    {
        foreach ($entityManager->getRepository(Stat::class)->findBy(['server' => $server], ['name' => 'ASC']) as $stat) {
            if (!$stat instanceof Stat) {
                continue;
            }

            $userStat = $entityManager->getRepository(UserStat::class)->findOneBy(['user' => $user, 'stat' => $stat]);
            if (!$userStat instanceof UserStat) {
                $entityManager->persist(new UserStat($user, $stat, 0));
            }
        }
    }

    private function serverOr404(EntityManagerInterface $entityManager, string $discordId): ?DiscordServer
    {
        $server = $entityManager->getRepository(DiscordServer::class)->findOneBy(['discordId' => $discordId]);

        return $server instanceof DiscordServer ? $server : null;
    }

    private function userOr404(EntityManagerInterface $entityManager, DiscordServer $server, string $discordId): ?GachaUser
    {
        $user = $entityManager->getRepository(GachaUser::class)->findOneBy(['server' => $server, 'discordId' => $discordId]);

        return $user instanceof GachaUser ? $user : null;
    }

    private function rankOr404(EntityManagerInterface $entityManager, DiscordServer $server, int $rankId): ?Rank
    {
        $rank = $entityManager->getRepository(Rank::class)->findOneBy(['id' => $rankId, 'server' => $server]);

        return $rank instanceof Rank ? $rank : null;
    }

    private function roleOr404(EntityManagerInterface $entityManager, DiscordServer $server, int $roleId): ?CharacterRole
    {
        $role = $entityManager->getRepository(CharacterRole::class)->findOneBy(['id' => $roleId, 'server' => $server]);

        return $role instanceof CharacterRole ? $role : null;
    }

    private function elementOr404(EntityManagerInterface $entityManager, DiscordServer $server, int $elementId): ?Element
    {
        $element = $entityManager->getRepository(Element::class)->findOneBy(['id' => $elementId, 'server' => $server]);

        return $element instanceof Element ? $element : null;
    }

    private function statOr404(EntityManagerInterface $entityManager, DiscordServer $server, int $statId): ?Stat
    {
        $stat = $entityManager->getRepository(Stat::class)->findOneBy(['id' => $statId, 'server' => $server]);

        return $stat instanceof Stat ? $stat : null;
    }

    private function staffRank(EntityManagerInterface $entityManager, DiscordServer $server): ?Rank
    {
        $ranks = $entityManager->getRepository(Rank::class)->findBy(['server' => $server, 'staff' => true], ['percentage' => 'ASC', 'name' => 'ASC'], 1);
        $rank = $ranks[0] ?? null;

        return $rank instanceof Rank ? $rank : null;
    }

    private function defaultRank(EntityManagerInterface $entityManager, DiscordServer $server): ?Rank
    {
        $ranks = array_values(array_filter(
            $entityManager->getRepository(Rank::class)->findBy(['server' => $server], ['percentage' => 'ASC', 'name' => 'ASC']),
            static fn (Rank $rank): bool => !$rank->isStaff(),
        ));

        if ([] === $ranks) {
            return null;
        }

        return $this->weightedPick($ranks, static fn (Rank $rank): int => $rank->percentage());
    }

    private function defaultRole(EntityManagerInterface $entityManager, DiscordServer $server): ?CharacterRole
    {
        $roles = $entityManager->getRepository(CharacterRole::class)->findBy(['server' => $server], ['percentage' => 'ASC', 'name' => 'ASC']);
        if ([] === $roles) {
            return null;
        }

        return $this->weightedPick($roles, static fn (CharacterRole $role): int => $role->percentage());
    }

    private function defaultElement(EntityManagerInterface $entityManager, DiscordServer $server): ?Element
    {
        $element = $entityManager->getRepository(Element::class)->findOneBy(['server' => $server], ['name' => 'ASC']);

        return $element instanceof Element ? $element : null;
    }

    /**
     * @template T of object
     *
     * @param list<T> $items
     * @param callable(T): int $weight
     *
     * @return T
     */
    private function weightedPick(array $items, callable $weight): object
    {
        $total = array_sum(array_map(static fn (object $item): int => max(0, $weight($item)), $items));
        if ($total <= 0) {
            return $items[0];
        }

        $point = random_int(1, $total);
        $cumulative = 0;
        foreach ($items as $item) {
            $cumulative += max(0, $weight($item));
            if ($point <= $cumulative) {
                return $item;
            }
        }

        return $items[array_key_last($items)];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function jsonPayload(Request $request): ?array
    {
        if ('' === trim($request->getContent())) {
            return [];
        }

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
    private function positiveInt(array $payload, string $key): ?int
    {
        $value = $payload[$key] ?? null;
        if (\is_int($value) && $value > 0) {
            return $value;
        }

        if (\is_string($value) && ctype_digit($value) && (int) $value > 0) {
            return (int) $value;
        }

        return null;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function intValue(array $payload, string $key): ?int
    {
        $value = $payload[$key] ?? null;
        if (\is_int($value)) {
            return $value;
        }

        if (\is_string($value) && preg_match('/^-?\d+$/', $value)) {
            return (int) $value;
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function userPayload(EntityManagerInterface $entityManager, GachaUser $user): array
    {
        return [
            'id' => (int) $user->id(),
            'discord_id' => $user->discordId(),
            'rank' => $this->rankPayload($user->rank()),
            'role' => $this->rolePayload($user->role()),
            'elements' => array_map(
                static fn (Element $element): array => ['id' => (int) $element->id(), 'name' => $element->name()],
                $user->elements()->toArray(),
            ),
            'stats' => $this->userStatsPayload($entityManager, $user),
        ];
    }

    /**
     * @return array{id: int, discord_id: string, name: string, is_staff: bool}|null
     */
    private function rankPayload(?Rank $rank): ?array
    {
        if (!$rank instanceof Rank) {
            return null;
        }

        return [
            'id' => (int) $rank->id(),
            'discord_id' => $rank->discordId(),
            'name' => $rank->name(),
            'is_staff' => $rank->isStaff(),
        ];
    }

    /**
     * @return array{id: int, name: string}|null
     */
    private function rolePayload(?CharacterRole $role): ?array
    {
        if (!$role instanceof CharacterRole) {
            return null;
        }

        return [
            'id' => (int) $role->id(),
            'name' => $role->name(),
        ];
    }

    /**
     * @return list<array{id: int, name: string, value: int}>
     */
    private function userStatsPayload(EntityManagerInterface $entityManager, GachaUser $user): array
    {
        /** @var list<UserStat> $userStats */
        $userStats = $entityManager->createQueryBuilder()
            ->select('userStat')
            ->from(UserStat::class, 'userStat')
            ->innerJoin('userStat.stat', 'statEntity')
            ->andWhere('userStat.user = :user')
            ->setParameter('user', $user)
            ->orderBy('statEntity.name', 'ASC')
            ->getQuery()
            ->getResult();

        return array_map(
            static fn (UserStat $userStat): array => [
                'id' => (int) $userStat->stat()->id(),
                'name' => $userStat->stat()->name(),
                'value' => $userStat->value(),
            ],
            $userStats,
        );
    }
}
