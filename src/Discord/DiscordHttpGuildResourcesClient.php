<?php

namespace App\Discord;

final readonly class DiscordHttpGuildResourcesClient implements DiscordGuildResourcesClientInterface
{
    public function __construct(
        private string $apiBaseUri,
        private string $botToken,
    ) {
    }

    public function fetchGuildChannels(string $guildId): array
    {
        return $this->listPayload($this->requestJson('/guilds/'.rawurlencode($guildId).'/channels'));
    }

    public function fetchGuildRoles(string $guildId): array
    {
        return $this->listPayload($this->requestJson('/guilds/'.rawurlencode($guildId).'/roles'));
    }

    /**
     * @return array<string, mixed>
     */
    private function requestJson(string $path): array
    {
        $botToken = trim($this->botToken);
        if ('' === $botToken) {
            throw new \RuntimeException('Discord bot token is not configured.');
        }

        $url = rtrim($this->apiBaseUri, '/').$path;
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => implode("\r\n", [
                    'Accept: application/json',
                    'Authorization: Bot '.$botToken,
                    'User-Agent: Gachamelia-Backoffice',
                ]),
                'ignore_errors' => true,
                'timeout' => 10,
            ],
        ]);

        $response = @file_get_contents($url, false, $context);
        if (false === $response) {
            throw new \RuntimeException('Discord API request failed.');
        }

        $statusCode = $this->extractStatusCode($http_response_header ?? []);
        $payload = json_decode($response, true);
        if (!\is_array($payload)) {
            throw new \RuntimeException('Discord API returned an invalid JSON response.');
        }

        if ($statusCode < 200 || $statusCode >= 300) {
            throw new \RuntimeException('Discord API request returned HTTP '.$statusCode.'.');
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return list<array<string, mixed>>
     */
    private function listPayload(array $payload): array
    {
        return array_values(array_filter($payload, \is_array(...)));
    }

    /**
     * @param list<string> $headers
     */
    private function extractStatusCode(array $headers): int
    {
        $statusLine = $headers[0] ?? '';
        if (1 === preg_match('/^HTTP\/\S+\s+(\d{3})\b/', $statusLine, $matches)) {
            return (int) $matches[1];
        }

        return 0;
    }
}
