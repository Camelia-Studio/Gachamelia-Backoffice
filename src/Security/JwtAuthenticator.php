<?php

namespace App\Security;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

final class JwtAuthenticator extends AbstractAuthenticator implements AuthenticationEntryPointInterface
{
    public function __construct(
        private readonly string $jwtSecret,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        $authorizationHeader = $request->headers->get('Authorization');

        return \is_string($authorizationHeader) && 1 === preg_match('/^Bearer(?:\s+|$)/i', $authorizationHeader);
    }

    public function authenticate(Request $request): Passport
    {
        $authorizationHeader = $request->headers->get('Authorization');
        if (!\is_string($authorizationHeader) || 1 !== preg_match('/^Bearer\s+(.+)$/i', $authorizationHeader, $matches)) {
            throw new CustomUserMessageAuthenticationException('Bearer token required.');
        }

        try {
            $payload = JWT::decode($matches[1], new Key($this->jwtSecret, 'HS256'));
        } catch (\Throwable $exception) {
            throw new CustomUserMessageAuthenticationException('Invalid Bearer token.', previous: $exception);
        }

        $clientId = $payload->sub ?? null;
        if (!\is_string($clientId) || '' === $clientId) {
            throw new CustomUserMessageAuthenticationException('Invalid Bearer token subject.');
        }

        $roles = $this->extractRoles($payload->roles ?? []);
        if (!\in_array('ROLE_BOT', $roles, true)) {
            throw new CustomUserMessageAuthenticationException('Bearer token is missing the bot role.');
        }

        return new SelfValidatingPassport(new UserBadge(
            $clientId,
            static fn (): BotApiUser => new BotApiUser($clientId, $roles),
        ));
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return $this->unauthorized();
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return $this->unauthorized();
    }

    /**
     * @return list<string>
     */
    private function extractRoles(mixed $roles): array
    {
        if (!\is_array($roles)) {
            return [];
        }

        return array_values(array_filter(
            $roles,
            static fn (mixed $role): bool => \is_string($role) && '' !== $role,
        ));
    }

    private function unauthorized(): JsonResponse
    {
        return new JsonResponse(
            ['error' => 'unauthorized'],
            Response::HTTP_UNAUTHORIZED,
            ['WWW-Authenticate' => 'Bearer'],
        );
    }
}
