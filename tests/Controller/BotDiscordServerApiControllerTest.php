<?php

namespace App\Tests\Controller;

use App\Tests\Support\DatabaseResetter;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class BotDiscordServerApiControllerTest extends WebTestCase
{
    use DatabaseResetter;

    private const BOT_CLIENT_ID = 'gachamelia-test-bot';
    private const BOT_CLIENT_SECRET = 'test-bot-secret';

    public function testDiscordServerRouteRequiresBotBearerToken(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/discord-servers', server: [
            'CONTENT_TYPE' => 'application/json',
        ], content: json_encode([
            'discord_id' => '123456789',
            'name' => 'Serveur Test',
            'icon' => 'server-icon',
        ], JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        self::assertResponseHeaderSame('www-authenticate', 'Bearer');
        $this->assertJsonPayloadContains(['error' => 'unauthorized']);
    }

    public function testBotCanCreateMinimalDiscordServer(): void
    {
        $client = static::createClient();
        $this->resetDatabase();

        $client->request('POST', '/api/discord-servers', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->requestAccessToken($client),
            'CONTENT_TYPE' => 'application/json',
        ], content: json_encode([
            'discord_id' => '123456789',
            'name' => 'Serveur Test',
            'icon' => 'server-icon',
        ], JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->assertJsonPayloadContains([
            'server' => [
                'discord_id' => '123456789',
                'name' => 'Serveur Test',
                'icon' => 'server-icon',
            ],
        ]);

        $row = $this->connection()->fetchAssociative('SELECT discord_id, name, icon FROM discord_servers WHERE discord_id = ?', ['123456789']);

        self::assertSame([
            'discord_id' => '123456789',
            'name' => 'Serveur Test',
            'icon' => 'server-icon',
        ], $row);
    }

    public function testBotCanRefreshKnownDiscordServerCache(): void
    {
        $client = static::createClient();
        $this->resetDatabase();

        $this->connection()->insert('discord_servers', [
            'discord_id' => '123456789',
            'name' => 'Ancien nom',
            'icon' => 'old-icon',
            'created_at' => '2026-07-06 10:00:00',
            'updated_at' => '2026-07-06 10:00:00',
        ]);

        $client->request('POST', '/api/discord-servers', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->requestAccessToken($client),
            'CONTENT_TYPE' => 'application/json',
        ], content: json_encode([
            'discord_id' => '123456789',
            'name' => 'Nouveau nom',
            'icon' => null,
        ], JSON_THROW_ON_ERROR));

        self::assertResponseIsSuccessful();
        $this->assertJsonPayloadContains([
            'server' => [
                'discord_id' => '123456789',
                'name' => 'Nouveau nom',
                'icon' => null,
            ],
        ]);

        $row = $this->connection()->fetchAssociative('SELECT discord_id, name, icon FROM discord_servers WHERE discord_id = ?', ['123456789']);

        self::assertSame([
            'discord_id' => '123456789',
            'name' => 'Nouveau nom',
            'icon' => null,
        ], $row);
        self::assertSame(1, (int) $this->connection()->fetchOne('SELECT COUNT(*) FROM discord_servers WHERE discord_id = ?', ['123456789']));
    }

    public function testBotCanRefreshServerEmojiCacheSnapshot(): void
    {
        $client = static::createClient();
        $this->resetDatabase();

        $this->connection()->insert('discord_servers', [
            'discord_id' => '123456789',
            'name' => 'Serveur Test',
            'icon' => null,
            'created_at' => '2026-07-06 10:00:00',
            'updated_at' => '2026-07-06 10:00:00',
        ]);

        $client->request('PUT', '/api/discord-emojis', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->requestAccessToken($client),
            'CONTENT_TYPE' => 'application/json',
        ], content: json_encode([
            'source' => 'server',
            'discord_server_id' => '123456789',
            'emojis' => [
                ['id' => '111111111111111111', 'name' => 'aube', 'animated' => false, 'available' => true],
                ['id' => '222222222222222222', 'name' => 'eclipse', 'animated' => true, 'available' => true],
            ],
        ], JSON_THROW_ON_ERROR));

        self::assertResponseIsSuccessful();
        $this->assertJsonPayloadContains([
            'cache' => [
                'source' => 'server',
                'cache_key' => 'server:123456789',
                'received' => 2,
                'available' => 2,
            ],
        ]);

        self::assertSame([
            [
                'cache_key' => 'server:123456789',
                'source' => 'server',
                'discord_id' => '111111111111111111',
                'name' => 'aube',
                'animated' => 0,
                'available' => 1,
            ],
            [
                'cache_key' => 'server:123456789',
                'source' => 'server',
                'discord_id' => '222222222222222222',
                'name' => 'eclipse',
                'animated' => 1,
                'available' => 1,
            ],
        ], $this->connection()->fetchAllAssociative(
            'SELECT cache_key, source, discord_id, name, animated, available FROM discord_emojis WHERE cache_key = ? AND source = ? ORDER BY discord_id',
            ['server:123456789', 'server'],
        ));

        $client->request('PUT', '/api/discord-emojis', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->requestAccessToken($client),
            'CONTENT_TYPE' => 'application/json',
        ], content: json_encode([
            'source' => 'server',
            'discord_server_id' => '123456789',
            'emojis' => [
                ['id' => '222222222222222222', 'name' => 'eclipse_new', 'animated' => true, 'available' => true],
            ],
        ], JSON_THROW_ON_ERROR));

        self::assertResponseIsSuccessful();

        self::assertSame([
            ['discord_id' => '111111111111111111', 'name' => 'aube', 'available' => 0],
            ['discord_id' => '222222222222222222', 'name' => 'eclipse_new', 'available' => 1],
        ], $this->connection()->fetchAllAssociative(
            'SELECT discord_id, name, available FROM discord_emojis WHERE cache_key = ? AND source = ? ORDER BY discord_id',
            ['server:123456789', 'server'],
        ));
    }

    private function requestAccessToken(KernelBrowser $client): string
    {
        $client->request('POST', '/api/auth/token', server: [
            'HTTP_AUTHORIZATION' => 'Basic '.base64_encode(self::BOT_CLIENT_ID.':'.self::BOT_CLIENT_SECRET),
        ]);

        self::assertResponseIsSuccessful();

        $payload = $this->jsonPayload($client);
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
