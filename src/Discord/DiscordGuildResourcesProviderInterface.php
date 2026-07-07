<?php

namespace App\Discord;

interface DiscordGuildResourcesProviderInterface
{
    /**
     * @return array{
     *     channels: list<array{id: string, name: string, label: string, type: int}>,
     *     roles: list<array{id: string, name: string, label: string, position: int, managed: bool}>
     * }
     */
    public function resourcesForGuild(string $guildId): array;
}
