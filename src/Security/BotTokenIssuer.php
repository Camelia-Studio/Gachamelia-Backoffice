<?php

namespace App\Security;

use Firebase\JWT\JWT;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;

final readonly class BotTokenIssuer
{
    public function __construct(
        private string $clientId,
        private string $clientSecret,
        private string $jwtSecret,
        private int $ttlSeconds,
    ) {
    }

    /**
     * @return array{token_type: string, access_token: string, expires_in: int}
     */
    public function issueFromAuthorizationHeader(?string $authorizationHeader): array
    {
        $this->assertConfigured();

        [$clientId, $clientSecret] = $this->parseBasicAuthorization($authorizationHeader);

        if (
            !hash_equals($this->clientId, $clientId)
            || !hash_equals($this->clientSecret, $clientSecret)
        ) {
            throw new BadCredentialsException('Invalid bot client credentials.');
        }

        $issuedAt = time();

        return [
            'token_type' => 'Bearer',
            'access_token' => JWT::encode([
                'sub' => $this->clientId,
                'roles' => ['ROLE_BOT'],
                'iat' => $issuedAt,
                'exp' => $issuedAt + $this->ttlSeconds,
            ], $this->jwtSecret, 'HS256'),
            'expires_in' => $this->ttlSeconds,
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function parseBasicAuthorization(?string $authorizationHeader): array
    {
        if (null === $authorizationHeader || 1 !== preg_match('/^Basic\s+(.+)$/i', $authorizationHeader, $matches)) {
            throw new BadCredentialsException('Missing bot client credentials.');
        }

        $decoded = base64_decode($matches[1], true);
        if (false === $decoded || !str_contains($decoded, ':')) {
            throw new BadCredentialsException('Malformed bot client credentials.');
        }

        [$clientId, $clientSecret] = explode(':', $decoded, 2);
        if ('' === $clientId || '' === $clientSecret) {
            throw new BadCredentialsException('Malformed bot client credentials.');
        }

        return [$clientId, $clientSecret];
    }

    private function assertConfigured(): void
    {
        if ('' === $this->clientId || '' === $this->clientSecret || '' === $this->jwtSecret || $this->ttlSeconds < 1) {
            throw new \LogicException('Bot API authentication is not configured.');
        }
    }
}
