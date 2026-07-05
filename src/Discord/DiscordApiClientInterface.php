<?php

namespace App\Discord;

interface DiscordApiClientInterface
{
    public function exchangeCodeForAccessToken(string $code): string;

    /**
     * @return array<string, mixed>
     */
    public function fetchCurrentUser(string $accessToken): array;

    /**
     * @return list<array<string, mixed>>
     */
    public function fetchCurrentUserGuilds(string $accessToken): array;

    /**
     * @return list<array<string, mixed>>
     */
    public function fetchBotGuilds(): array;
}
