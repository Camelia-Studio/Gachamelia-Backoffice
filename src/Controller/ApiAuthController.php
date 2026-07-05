<?php

namespace App\Controller;

use App\Security\BotTokenIssuer;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;

final readonly class ApiAuthController
{
    #[Route('/api/auth/token', name: 'api_auth_token', methods: ['POST'])]
    public function token(Request $request, BotTokenIssuer $tokenIssuer): JsonResponse
    {
        try {
            return new JsonResponse($tokenIssuer->issueFromAuthorizationHeader($request->headers->get('Authorization')));
        } catch (BadCredentialsException) {
            return new JsonResponse(
                ['error' => 'invalid_client'],
                Response::HTTP_UNAUTHORIZED,
                ['WWW-Authenticate' => 'Basic realm="Gachamelia API"'],
            );
        }
    }
}
