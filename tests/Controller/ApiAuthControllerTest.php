<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ApiAuthControllerTest extends WebTestCase
{
    private const BOT_CLIENT_ID = 'gachamelia-test-bot';
    private const BOT_CLIENT_SECRET = 'test-bot-secret';

    public function testTokenRouteRequiresBasicAuthentication(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/auth/token');

        self::assertResponseStatusCodeSame(401);
        self::assertResponseHeaderSame('www-authenticate', 'Basic realm="Gachamelia API"');
        $this->assertJsonPayloadContains(['error' => 'invalid_client']);
    }

    public function testTokenRouteRejectsInvalidBasicCredentials(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/auth/token', server: [
            'HTTP_AUTHORIZATION' => 'Basic '.base64_encode('wrong-client:wrong-secret'),
        ]);

        self::assertResponseStatusCodeSame(401);
        self::assertResponseHeaderSame('www-authenticate', 'Basic realm="Gachamelia API"');
        $this->assertJsonPayloadContains(['error' => 'invalid_client']);
    }

    public function testTokenRouteIssuesBearerTokenForValidBasicCredentials(): void
    {
        $client = static::createClient();

        $accessToken = $this->requestAccessToken($client);

        self::assertMatchesRegularExpression('/^[^.]+\.[^.]+\.[^.]+$/', $accessToken);
    }

    public function testProtectedApiRouteRequiresBearerToken(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/me');

        self::assertResponseStatusCodeSame(401);
        self::assertResponseHeaderSame('www-authenticate', 'Bearer');
        $this->assertJsonPayloadContains(['error' => 'unauthorized']);
    }

    public function testProtectedApiRouteAcceptsIssuedBearerToken(): void
    {
        $client = static::createClient();
        $accessToken = $this->requestAccessToken($client);

        $client->request('GET', '/api/me', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$accessToken,
        ]);

        self::assertResponseIsSuccessful();
        $this->assertJsonPayloadContains([
            'client_id' => self::BOT_CLIENT_ID,
            'roles' => ['ROLE_BOT'],
        ]);
    }

    public function testUnknownApiRouteReturnsJsonError(): void
    {
        $client = static::createClient();
        $accessToken = $this->requestAccessToken($client);

        $client->request('GET', '/api/unknown', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$accessToken,
        ]);

        self::assertResponseStatusCodeSame(404);
        self::assertResponseHeaderSame('content-type', 'application/json');
        $this->assertJsonPayloadContains([
            'error' => 'not_found',
            'message' => 'Not Found',
            'status' => 404,
        ]);
    }

    public function testApiRootReturnsJsonError(): void
    {
        $client = static::createClient();
        $accessToken = $this->requestAccessToken($client);

        $client->request('GET', '/api', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$accessToken,
        ]);

        self::assertResponseStatusCodeSame(404);
        self::assertResponseHeaderSame('content-type', 'application/json');
        $this->assertJsonPayloadContains([
            'error' => 'not_found',
            'message' => 'Not Found',
            'status' => 404,
        ]);
    }

    public function testApiMethodErrorReturnsJson(): void
    {
        $client = static::createClient();
        $accessToken = $this->requestAccessToken($client);

        $client->request('POST', '/api/me', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$accessToken,
        ]);

        self::assertResponseStatusCodeSame(405);
        self::assertResponseHeaderSame('content-type', 'application/json');
        self::assertResponseHeaderSame('allow', 'GET');
        $this->assertJsonPayloadContains([
            'error' => 'method_not_allowed',
            'message' => 'Method Not Allowed',
            'status' => 405,
        ]);
    }

    private function requestAccessToken(KernelBrowser $client): string
    {
        $client->request('POST', '/api/auth/token', server: [
            'HTTP_AUTHORIZATION' => 'Basic '.base64_encode(self::BOT_CLIENT_ID.':'.self::BOT_CLIENT_SECRET),
        ]);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'application/json');

        $payload = $this->jsonPayload($client);

        self::assertSame('Bearer', $payload['token_type'] ?? null);
        self::assertSame(3600, $payload['expires_in'] ?? null);
        self::assertIsString($payload['access_token'] ?? null);

        return $payload['access_token'];
    }

    /**
     * @param array<string, mixed> $expected
     */
    private function assertJsonPayloadContains(array $expected): void
    {
        $payload = $this->jsonPayload();

        foreach ($expected as $key => $value) {
            self::assertArrayHasKey($key, $payload);
            self::assertSame($value, $payload[$key]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function jsonPayload(?KernelBrowser $client = null): array
    {
        $response = ($client ?? static::getClient())->getResponse();
        self::assertNotNull($response);

        $payload = json_decode($response->getContent() ?: '', true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($payload);

        return $payload;
    }
}
