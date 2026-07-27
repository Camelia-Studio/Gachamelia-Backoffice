<?php

declare(strict_types=1);

namespace App\Backoffice;

use App\Entity\CharacterRole;
use App\Entity\DiscordServer;
use App\Entity\Element;
use App\Entity\GachaUser;
use App\Entity\Rank;
use App\Entity\UserStat;
use Doctrine\ORM\EntityManagerInterface;

final readonly class CharacterSheetProvider
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return array{
     *     discord_id: string,
     *     rank: array{name: string, is_staff: bool}|null,
     *     role: array{name: string, emoji: string|null, emoji_url: string|null}|null,
     *     elements: list<array{name: string, emoji: string|null, emoji_url: string|null}>,
     *     stats: list<array{name: string, value: int}>
     * }|null
     */
    public function forMember(DiscordServer $server, string $discordId): ?array
    {
        $user = $this->entityManager->getRepository(GachaUser::class)->findOneBy([
            'server' => $server,
            'discordId' => $discordId,
        ]);
        if (!$user instanceof GachaUser) {
            return null;
        }

        $elements = array_map(
            $this->elementPayload(...),
            $user->elements()->toArray(),
        );
        usort(
            $elements,
            static fn (array $left, array $right): int => strnatcasecmp($left['name'], $right['name']),
        );

        return [
            'discord_id' => $user->discordId(),
            'rank' => $this->rankPayload($user->rank()),
            'role' => $this->rolePayload($user->role()),
            'elements' => $elements,
            'stats' => $this->statsPayload($user),
        ];
    }

    /**
     * @return array{name: string, is_staff: bool}|null
     */
    private function rankPayload(?Rank $rank): ?array
    {
        if (!$rank instanceof Rank) {
            return null;
        }

        return [
            'name' => $rank->name(),
            'is_staff' => $rank->isStaff(),
        ];
    }

    /**
     * @return array{name: string, emoji: string|null, emoji_url: string|null}|null
     */
    private function rolePayload(?CharacterRole $role): ?array
    {
        if (!$role instanceof CharacterRole) {
            return null;
        }

        $emojiUrl = $role->emojiCdnUrl();

        return [
            'name' => $role->name(),
            'emoji' => null === $emojiUrl ? $role->emojiMarkup() : null,
            'emoji_url' => $emojiUrl,
        ];
    }

    /**
     * @return array{name: string, emoji: string|null, emoji_url: string|null}
     */
    private function elementPayload(Element $element): array
    {
        $emojiUrl = $element->emojiCdnUrl();

        return [
            'name' => $element->name(),
            'emoji' => null === $emojiUrl ? $element->emojiMarkup() : null,
            'emoji_url' => $emojiUrl,
        ];
    }

    /**
     * @return list<array{name: string, value: int}>
     */
    private function statsPayload(GachaUser $user): array
    {
        /** @var list<UserStat> $userStats */
        $userStats = $this->entityManager->createQueryBuilder()
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
                'name' => $userStat->stat()->name(),
                'value' => $userStat->value(),
            ],
            $userStats,
        );
    }
}
