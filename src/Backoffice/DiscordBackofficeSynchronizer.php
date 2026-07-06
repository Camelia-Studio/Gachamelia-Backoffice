<?php

namespace App\Backoffice;

use App\Discord\DiscordGuildAccessResolver;
use App\Entity\DiscordServer;
use App\Entity\DiscordServerMember;
use App\Entity\DiscordUser;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DiscordBackofficeSynchronizer
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DiscordGuildAccessResolver $guildAccessResolver,
    ) {
    }

    /**
     * @param array{id: string, username: string, global_name: ?string, avatar: ?string} $profile
     * @param list<array<string, mixed>>                                                $userGuilds
     */
    public function synchronize(array $profile, array $userGuilds): DiscordUser
    {
        $user = $this->upsertUser($profile);
        $knownServers = $this->knownServersByDiscordId();
        $accessibleGuilds = $this->guildAccessResolver->resolveAccessibleGuilds($userGuilds, $this->knownServerPayloads($knownServers));
        $accessibleServerIds = [];

        foreach ($accessibleGuilds as $guild) {
            $server = $knownServers[$guild['id']] ?? null;
            if (!$server instanceof DiscordServer) {
                continue;
            }

            $server->refreshCache($guild['name'], $guild['icon']);
            $accessibleServerIds[$server->discordId()] = true;
            $this->upsertMembership($user, $server, $guild);
        }

        foreach ($this->entityManager->getRepository(DiscordServerMember::class)->findBy(['user' => $user]) as $membership) {
            if (!$membership instanceof DiscordServerMember) {
                continue;
            }

            if (!isset($accessibleServerIds[$membership->server()->discordId()])) {
                $this->entityManager->remove($membership);
            }
        }

        $this->entityManager->flush();

        return $user;
    }

    /**
     * @param array{id: string, username: string, global_name: ?string, avatar: ?string} $profile
     */
    private function upsertUser(array $profile): DiscordUser
    {
        $repository = $this->entityManager->getRepository(DiscordUser::class);
        $user = $repository->findOneBy(['discordId' => $profile['id']]);

        if (!$user instanceof DiscordUser) {
            $user = new DiscordUser($profile['id'], $profile['username'], $profile['global_name'], $profile['avatar']);
            $this->entityManager->persist($user);

            return $user;
        }

        $user->refreshProfile($profile['username'], $profile['global_name'], $profile['avatar']);

        return $user;
    }

    /**
     * @return array<string, DiscordServer>
     */
    private function knownServersByDiscordId(): array
    {
        $servers = [];

        foreach ($this->entityManager->getRepository(DiscordServer::class)->findAll() as $server) {
            if ($server instanceof DiscordServer) {
                $servers[$server->discordId()] = $server;
            }
        }

        return $servers;
    }

    /**
     * @param array<string, DiscordServer> $knownServers
     *
     * @return list<array{id: string}>
     */
    private function knownServerPayloads(array $knownServers): array
    {
        return array_map(
            static fn (DiscordServer $server): array => ['id' => $server->discordId()],
            array_values($knownServers),
        );
    }

    /**
     * @param array{
     *     id: string,
     *     name: string,
     *     icon: ?string,
     *     owner: bool,
     *     permissions: string,
     *     canManageConfiguration: bool
     * } $guild
     */
    private function upsertMembership(DiscordUser $user, DiscordServer $server, array $guild): void
    {
        $repository = $this->entityManager->getRepository(DiscordServerMember::class);
        $membership = $repository->findOneBy([
            'user' => $user,
            'server' => $server,
        ]);

        if (!$membership instanceof DiscordServerMember) {
            $membership = new DiscordServerMember(
                $user,
                $server,
                $guild['owner'],
                $guild['permissions'],
                $guild['canManageConfiguration'],
            );
            $this->entityManager->persist($membership);

            return;
        }

        $membership->refreshAccess($guild['owner'], $guild['permissions'], $guild['canManageConfiguration']);
    }
}
