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

    public function testBotCanReadCompleteServerCatalogueSnapshot(): void
    {
        $client = static::createClient();
        $this->resetDatabase();

        $this->connection()->insert('discord_servers', [
            'discord_id' => '123456789',
            'name' => 'Serveur Test',
            'icon' => 'server-icon',
            'created_at' => '2026-07-06 10:00:00',
            'updated_at' => '2026-07-06 10:00:00',
        ]);
        $serverId = (int) $this->connection()->lastInsertId();

        $this->connection()->insert('discord_servers', [
            'discord_id' => '987654321',
            'name' => 'Autre serveur',
            'icon' => null,
            'created_at' => '2026-07-06 10:00:00',
            'updated_at' => '2026-07-06 10:00:00',
        ]);
        $otherServerId = (int) $this->connection()->lastInsertId();

        $this->connection()->insert('ranks', [
            'server_id' => $serverId,
            'discord_id' => 'rank-novice',
            'name' => 'Novice',
            'percentage' => 35,
            'bye_title' => 'Novice sortant',
            'is_staff' => 0,
            'created_at' => '2026-07-06 10:00:00',
            'updated_at' => '2026-07-06 10:00:00',
        ]);
        $rankId = (int) $this->connection()->lastInsertId();

        $this->connection()->insert('ranks', [
            'server_id' => $otherServerId,
            'discord_id' => 'rank-externe',
            'name' => 'Externe',
            'percentage' => 100,
            'bye_title' => null,
            'is_staff' => 0,
            'created_at' => '2026-07-06 10:00:00',
            'updated_at' => '2026-07-06 10:00:00',
        ]);

        $this->connection()->insert('stats', [
            'server_id' => $serverId,
            'name' => 'Force',
        ]);
        $statId = (int) $this->connection()->lastInsertId();

        $this->connection()->insert('rank_stats', [
            'rank_id' => $rankId,
            'stat_id' => $statId,
            'percentage' => 70,
        ]);

        $this->connection()->insert('welcome_messages', [
            'server_id' => $serverId,
            'rank_id' => $rankId,
            'message' => 'Bienvenue parmi nous.',
        ]);
        $welcomeMessageId = (int) $this->connection()->lastInsertId();

        $this->connection()->insert('bye_messages', [
            'server_id' => $serverId,
            'rank_id' => $rankId,
            'message' => 'À bientôt.',
        ]);
        $byeMessageId = (int) $this->connection()->lastInsertId();

        $this->connection()->insert('roles', [
            'server_id' => $serverId,
            'name' => 'Comète',
            'percentage' => 45,
            'emoji_source' => 'server',
            'emoji_unicode' => null,
            'emoji_id' => '123456789012345678',
            'emoji_name' => 'comete',
            'emoji_animated' => 0,
        ]);
        $roleId = (int) $this->connection()->lastInsertId();

        $this->connection()->insert('elements', [
            'server_id' => $serverId,
            'name' => 'Ambre',
            'emoji_source' => 'unicode',
            'emoji_unicode' => '🌘',
            'emoji_id' => null,
            'emoji_name' => null,
            'emoji_animated' => 0,
        ]);
        $elementId = (int) $this->connection()->lastInsertId();

        $client->request('GET', '/api/discord-servers/123456789/catalogue', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->requestAccessToken($client),
        ]);

        self::assertResponseIsSuccessful();
        self::assertSame([
            'server' => [
                'discord_id' => '123456789',
                'name' => 'Serveur Test',
                'icon' => 'server-icon',
            ],
            'catalogue' => [
                'ranks' => [[
                    'id' => $rankId,
                    'discord_id' => 'rank-novice',
                    'name' => 'Novice',
                    'percentage' => 35,
                    'bye_title' => 'Novice sortant',
                    'is_staff' => false,
                    'stats' => [[
                        'id' => $statId,
                        'name' => 'Force',
                        'percentage' => 70,
                    ]],
                    'welcome_messages' => [[
                        'id' => $welcomeMessageId,
                        'message' => 'Bienvenue parmi nous.',
                    ]],
                    'bye_messages' => [[
                        'id' => $byeMessageId,
                        'message' => 'À bientôt.',
                    ]],
                ]],
                'roles' => [[
                    'id' => $roleId,
                    'name' => 'Comète',
                    'percentage' => 45,
                    'emoji' => [
                        'source' => 'server',
                        'unicode' => null,
                        'id' => '123456789012345678',
                        'name' => 'comete',
                        'animated' => false,
                        'markup' => '<:comete:123456789012345678>',
                        'cdn_url' => 'https://cdn.discordapp.com/emojis/123456789012345678.webp?size=64&quality=lossless',
                    ],
                ]],
                'stats' => [[
                    'id' => $statId,
                    'name' => 'Force',
                ]],
                'elements' => [[
                    'id' => $elementId,
                    'name' => 'Ambre',
                    'emoji' => [
                        'source' => 'unicode',
                        'unicode' => '🌘',
                        'id' => null,
                        'name' => null,
                        'animated' => false,
                        'markup' => '🌘',
                        'cdn_url' => null,
                    ],
                ]],
            ],
        ], $this->jsonPayload($client));

        $client->request('GET', '/api/discord-servers/000000000/catalogue', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->requestAccessToken($client),
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonPayloadContains(['error' => 'server_not_found']);
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
