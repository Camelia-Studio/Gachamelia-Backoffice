<?php

declare(strict_types=1);

namespace App\Discord;

interface DiscordGuildResourcesClientInterface
{
    /**
     * @return list<array<string, mixed>>
     */
    public function fetchGuildChannels(string $guildId): array;

    /**
     * @return list<array<string, mixed>>
     */
    public function fetchGuildRoles(string $guildId): array;
}
