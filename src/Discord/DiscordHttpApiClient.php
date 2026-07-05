<?php

namespace App\Discord;

final readonly class DiscordHttpApiClient implements DiscordApiClientInterface
{
    public function __construct(
        private string $apiBaseUri,
        private string $clientId,
        private string $clientSecret,
        private string $redirectUri,
        private string $botToken,
    ) {
    }

    public function exchangeCodeForAccessToken(string $code): string
    {
        $payload = $this->requestJson('POST', '/oauth2/token', [
            'Authorization: Basic '.base64_encode($this->clientId.':'.$this->clientSecret),
            'Content-Type: application/x-www-form-urlencoded',
        ], http_build_query([
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $this->redirectUri,
        ], '', '&', PHP_QUERY_RFC3986));

        $accessToken = $payload['access_token'] ?? null;
        if (!\is_string($accessToken) || '' === $accessToken) {
            throw new \RuntimeException('Discord OAuth response did not include an access token.');
        }

        return $accessToken;
    }

    public function fetchCurrentUser(string $accessToken): array
    {
        return $this->requestJson('GET', '/users/@me', [
            'Authorization: Bearer '.$accessToken,
        ]);
    }

    public function fetchCurrentUserGuilds(string $accessToken): array
    {
        return $this->listPayload($this->requestJson('GET', '/users/@me/guilds', [
            'Authorization: Bearer '.$accessToken,
        ]));
    }

    public function fetchBotGuilds(): array
    {
        return $this->listPayload($this->requestJson('GET', '/users/@me/guilds', [
            'Authorization: Bot '.$this->botToken,
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    private function requestJson(string $method, string $path, array $headers, ?string $body = null): array
    {
        $url = rtrim($this->apiBaseUri, '/').$path;
        $requestHeaders = array_merge([
            'Accept: application/json',
            'User-Agent: Gachamelia-Backoffice',
        ], $headers);

        $context = stream_context_create([
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $requestHeaders),
                'content' => $body ?? '',
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
