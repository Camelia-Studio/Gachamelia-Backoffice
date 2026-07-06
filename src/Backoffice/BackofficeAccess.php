<?php

namespace App\Backoffice;

use App\Discord\DiscordCdnUrlGenerator;
use App\Entity\DiscordServerMember;
use App\Entity\DiscordUser;
use Doctrine\ORM\EntityManagerInterface;

final readonly class BackofficeAccess
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DiscordCdnUrlGenerator $discordCdnUrlGenerator,
    ) {
    }

    /**
     * @return array{id: string, username: string, global_name: ?string, avatar: ?string}|null
     */
    public function profile(?int $userId): ?array
    {
        $user = $this->user($userId);
        if (!$user instanceof DiscordUser) {
            return null;
        }

        return [
            'id' => $user->discordId(),
            'username' => $user->username(),
            'global_name' => $user->globalName(),
            'avatar' => $user->avatar(),
        ];
    }

    /**
     * @return list<array{
     *     id: string,
     *     name: string,
     *     icon: ?string,
     *     icon_url: ?string,
     *     owner: bool,
     *     permissions: string,
     *     canManageConfiguration: bool
     * }>
     */
    public function guilds(?int $userId): array
    {
        $user = $this->user($userId);
        if (!$user instanceof DiscordUser) {
            return [];
        }

        $memberships = $this->entityManager->getRepository(DiscordServerMember::class)->findBy(['user' => $user]);
        $guilds = [];

        foreach ($memberships as $membership) {
            if ($membership instanceof DiscordServerMember) {
                $guilds[] = $this->guildPayload($membership);
            }
        }

        usort(
            $guilds,
            static fn (array $left, array $right): int => strnatcasecmp($left['name'], $right['name']),
        );

        return $guilds;
    }

    /**
     * @return array{
     *     id: string,
     *     name: string,
     *     icon: ?string,
     *     icon_url: ?string,
     *     owner: bool,
     *     permissions: string,
     *     canManageConfiguration: bool
     * }|null
     */
    public function findGuild(?int $userId, string $guildId): ?array
    {
        $user = $this->user($userId);
        if (!$user instanceof DiscordUser) {
            return null;
        }

        foreach ($this->entityManager->getRepository(DiscordServerMember::class)->findBy(['user' => $user]) as $membership) {
            if ($membership instanceof DiscordServerMember && $membership->server()->discordId() === $guildId) {
                return $this->guildPayload($membership);
            }
        }

        return null;
    }

    private function user(?int $userId): ?DiscordUser
    {
        if (null === $userId) {
            return null;
        }

        $user = $this->entityManager->find(DiscordUser::class, $userId);

        return $user instanceof DiscordUser ? $user : null;
    }

    /**
     * @return array{
     *     id: string,
     *     name: string,
     *     icon: ?string,
     *     icon_url: ?string,
     *     owner: bool,
     *     permissions: string,
     *     canManageConfiguration: bool
     * }
     */
    private function guildPayload(DiscordServerMember $membership): array
    {
        $server = $membership->server();

        return [
            'id' => $server->discordId(),
            'name' => $server->name(),
            'icon' => $server->icon(),
            'icon_url' => $this->discordCdnUrlGenerator->guildIconUrl($server->discordId(), $server->icon()),
            'owner' => $membership->owner(),
            'permissions' => $membership->permissions(),
            'canManageConfiguration' => $membership->canManageConfiguration(),
        ];
    }
}
