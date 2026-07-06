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
}
