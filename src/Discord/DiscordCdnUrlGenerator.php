<?php

namespace App\Discord;

final class DiscordCdnUrlGenerator
{
    public function guildIconUrl(string $guildId, ?string $icon, int $size = 64): ?string
    {
        if (null === $icon || '' === trim($icon)) {
            return null;
        }

        $icon = trim($icon);
        $url = sprintf(
            'https://cdn.discordapp.com/icons/%s/%s.webp?size=%d',
            rawurlencode($guildId),
            rawurlencode($icon),
            $size,
        );

        if (str_starts_with($icon, 'a_')) {
            $url .= '&animated=true';
        }

        return $url;
    }

    public function userAvatarUrl(string $userId, ?string $avatar, int $size = 128): ?string
    {
        if (null === $avatar || '' === trim($avatar)) {
            return null;
        }

        $avatar = trim($avatar);
        $url = sprintf(
            'https://cdn.discordapp.com/avatars/%s/%s.webp?size=%d',
            rawurlencode($userId),
            rawurlencode($avatar),
            $size,
        );

        if (str_starts_with($avatar, 'a_')) {
            $url .= '&animated=true';
        }

        return $url;
    }
}
