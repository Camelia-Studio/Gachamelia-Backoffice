<?php

namespace App\Discord;

use Psr\Cache\CacheItemPoolInterface;

final readonly class DiscordGuildResourcesProvider implements DiscordGuildResourcesProviderInterface
{
    /**
     * @var list<int>
     */
    private const array MESSAGE_CHANNEL_TYPES = [0, 5];

    public function __construct(
        private DiscordGuildResourcesClientInterface $client,
        private CacheItemPoolInterface $cache,
        private int $ttlSeconds,
    ) {
    }

    public function resourcesForGuild(string $guildId): array
    {
        try {
            if ($this->ttlSeconds <= 0) {
                return $this->freshResourcesForGuild($guildId);
            }

            $item = $this->cache->getItem('discord_guild_resources_'.sha1($guildId));
            if ($item->isHit()) {
                $payload = $item->get();
                if ($this->isResourcesPayload($payload)) {
                    return $payload;
                }
            }

            $payload = $this->freshResourcesForGuild($guildId);
            $item->set($payload);
            $item->expiresAfter($this->ttlSeconds);
            $this->cache->save($item);

            return $payload;
        } catch (\Throwable) {
            return $this->emptyResources();
        }
    }

    /**
     * @return array{
     *     channels: list<array{id: string, name: string, label: string, type: int}>,
     *     roles: list<array{id: string, name: string, label: string, position: int, managed: bool}>
     * }
     */
    private function freshResourcesForGuild(string $guildId): array
    {
        return [
            'channels' => $this->channelsPayload($this->client->fetchGuildChannels($guildId)),
            'roles' => $this->rolesPayload($guildId, $this->client->fetchGuildRoles($guildId)),
        ];
    }

    /**
     * @param list<array<string, mixed>> $channels
     *
     * @return list<array{id: string, name: string, label: string, type: int}>
     */
    private function channelsPayload(array $channels): array
    {
        $payload = [];
        foreach ($channels as $channel) {
            $id = $channel['id'] ?? null;
            $name = $channel['name'] ?? null;
            $type = $channel['type'] ?? null;
            if (!\is_string($id) || !\is_string($name) || !\is_int($type) || !\in_array($type, self::MESSAGE_CHANNEL_TYPES, true)) {
                continue;
            }

            $payload[] = [
                'id' => $id,
                'name' => $name,
                'label' => '#'.$name,
                'type' => $type,
            ];
        }

        usort($payload, static fn (array $left, array $right): int => strnatcasecmp($left['name'], $right['name']));

        return $payload;
    }

    /**
     * @param list<array<string, mixed>> $roles
     *
     * @return list<array{id: string, name: string, label: string, position: int, managed: bool}>
     */
    private function rolesPayload(string $guildId, array $roles): array
    {
        $payload = [];
        foreach ($roles as $role) {
            $id = $role['id'] ?? null;
            $name = $role['name'] ?? null;
            if (!\is_string($id) || !\is_string($name) || $id === $guildId) {
                continue;
            }

            $payload[] = [
                'id' => $id,
                'name' => $name,
                'label' => '@'.$name,
                'position' => \is_int($role['position'] ?? null) ? $role['position'] : 0,
                'managed' => true === ($role['managed'] ?? false),
            ];
        }

        usort($payload, static function (array $left, array $right): int {
            return $right['position'] <=> $left['position']
                ?: strnatcasecmp($left['name'], $right['name']);
        });

        return $payload;
    }

    private function isResourcesPayload(mixed $payload): bool
    {
        return \is_array($payload)
            && isset($payload['channels'], $payload['roles'])
            && \is_array($payload['channels'])
            && \is_array($payload['roles']);
    }

    /**
     * @return array{channels: array{}, roles: array{}}
     */
    private function emptyResources(): array
    {
        return [
            'channels' => [],
            'roles' => [],
        ];
    }
}
