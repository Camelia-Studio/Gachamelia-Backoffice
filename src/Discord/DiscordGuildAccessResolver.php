<?php

namespace App\Discord;

final class DiscordGuildAccessResolver
{
    private const ADMINISTRATOR_PERMISSION = 0x8;

    /**
     * @param list<array<string, mixed>> $userGuilds
     * @param list<array<string, mixed>> $botGuilds
     *
     * @return list<array{
     *     id: string,
     *     name: string,
     *     icon: ?string,
     *     owner: bool,
     *     permissions: string,
     *     canManageConfiguration: bool
     * }>
     */
    public function resolveAccessibleGuilds(array $userGuilds, array $botGuilds): array
    {
        $botGuildIds = [];
        foreach ($botGuilds as $botGuild) {
            $id = $botGuild['id'] ?? null;
            if (\is_string($id) && '' !== $id) {
                $botGuildIds[$id] = true;
            }
        }

        $accessibleGuilds = [];
        foreach ($userGuilds as $userGuild) {
            $id = $userGuild['id'] ?? null;
            if (!\is_string($id) || '' === $id || !isset($botGuildIds[$id])) {
                continue;
            }

            $name = $userGuild['name'] ?? $id;
            $icon = $userGuild['icon'] ?? null;
            $owner = true === ($userGuild['owner'] ?? false);
            $permissions = (string) ($userGuild['permissions'] ?? '0');

            $accessibleGuilds[] = [
                'id' => $id,
                'name' => \is_string($name) && '' !== $name ? $name : $id,
                'icon' => \is_string($icon) && '' !== $icon ? $icon : null,
                'owner' => $owner,
                'permissions' => $permissions,
                'canManageConfiguration' => $owner || self::hasAdministratorPermission($permissions),
            ];
        }

        return $accessibleGuilds;
    }

    private static function hasAdministratorPermission(string $permissions): bool
    {
        return (((int) $permissions) & self::ADMINISTRATOR_PERMISSION) === self::ADMINISTRATOR_PERMISSION;
    }
}
