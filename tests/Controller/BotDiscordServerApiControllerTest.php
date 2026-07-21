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
                'settings' => [
                    'welcome_channel_id' => null,
                    'bye_channel_id' => null,
                    'staff_role_id' => null,
                ],
            ],
        ]);

        $row = $this->connection()->fetchAssociative('SELECT discord_id, name, icon FROM discord_servers WHERE discord_id = ?', ['123456789']);

        self::assertSame([
            'discord_id' => '123456789',
            'name' => 'Serveur Test',
            'icon' => 'server-icon',
        ], $row);

        $client->request('GET', '/api/discord-servers/123456789/catalogue', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->requestAccessToken($client),
        ]);

        self::assertResponseIsSuccessful();
        self::assertSame([
            'ready' => false,
            'errors' => ['missing_non_staff_rank', 'empty_roles', 'empty_elements'],
            'warnings' => ['empty_stats', 'missing_welcome_channel', 'missing_bye_channel', 'empty_welcome_messages', 'empty_bye_messages'],
        ], $this->jsonPayload($client)['validation'] ?? null);
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
                'settings' => [
                    'welcome_channel_id' => null,
                    'bye_channel_id' => null,
                    'staff_role_id' => null,
                ],
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

    public function testBotCanDeactivateAndReactivateServerLifecycle(): void
    {
        $client = static::createClient();
        $this->resetDatabase();
        $token = $this->requestAccessToken($client);

        $client->request('POST', '/api/discord-servers', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            'CONTENT_TYPE' => 'application/json',
        ], content: json_encode([
            'discord_id' => '123456789',
            'name' => 'Serveur Test',
        ], JSON_THROW_ON_ERROR));

        $lifecycle = $this->jsonPayload($client)['server']['lifecycle'] ?? null;
        self::assertIsArray($lifecycle);
        self::assertTrue($lifecycle['active'] ?? false);
        self::assertIsString($lifecycle['last_seen_at'] ?? null);
        self::assertNull($lifecycle['inactive_at'] ?? null);

        $client->request('DELETE', '/api/discord-servers/123456789', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $client->request('DELETE', '/api/discord-servers/123456789', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $client->request('PATCH', '/api/discord-servers/123456789/settings', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            'CONTENT_TYPE' => 'application/json',
        ], content: '{}');
        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        $this->assertJsonPayloadContains(['error' => 'server_inactive']);

        $client->request('PUT', '/api/discord-emojis', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            'CONTENT_TYPE' => 'application/json',
        ], content: json_encode([
            'source' => 'server',
            'discord_server_id' => '123456789',
            'emojis' => [],
        ], JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        $this->assertJsonPayloadContains(['error' => 'server_inactive']);

        $client->request('PUT', '/api/discord-servers/123456789/users/42', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            'CONTENT_TYPE' => 'application/json',
        ], content: '{}');
        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        $this->assertJsonPayloadContains(['error' => 'server_inactive']);

        $client->request('GET', '/api/discord-servers/123456789/catalogue', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
        self::assertResponseIsSuccessful();
        self::assertFalse($this->jsonPayload($client)['server']['lifecycle']['active'] ?? true);

        $client->request('POST', '/api/discord-servers', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            'CONTENT_TYPE' => 'application/json',
        ], content: json_encode([
            'discord_id' => '123456789',
            'name' => 'Serveur revenu',
        ], JSON_THROW_ON_ERROR));
        self::assertResponseIsSuccessful();
        self::assertTrue($this->jsonPayload($client)['server']['lifecycle']['active'] ?? false);
        self::assertNull($this->jsonPayload($client)['server']['lifecycle']['inactive_at'] ?? null);
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
            'server_id' => $serverId,
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
        $responsePayload = $this->jsonPayload($client);
        self::assertTrue($responsePayload['server']['lifecycle']['active'] ?? false);
        self::assertIsString($responsePayload['server']['lifecycle']['last_seen_at'] ?? null);
        self::assertNull($responsePayload['server']['lifecycle']['inactive_at'] ?? null);
        unset($responsePayload['server']['lifecycle']);

        self::assertSame([
            'server' => [
                'discord_id' => '123456789',
                'name' => 'Serveur Test',
                'icon' => 'server-icon',
                'settings' => [
                    'welcome_channel_id' => null,
                    'bye_channel_id' => null,
                    'staff_role_id' => null,
                ],
            ],
            'validation' => [
                'ready' => true,
                'errors' => [],
                'warnings' => ['missing_welcome_channel', 'missing_bye_channel'],
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
        ], $responsePayload);

        $client->request('GET', '/api/discord-servers/000000000/catalogue', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->requestAccessToken($client),
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonPayloadContains(['error' => 'server_not_found']);
    }

    public function testBotCanUpdateServerSettingsAndReadThemInCatalogue(): void
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

        $token = $this->requestAccessToken($client);

        $client->request('PATCH', '/api/discord-servers/123456789/settings', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            'CONTENT_TYPE' => 'application/json',
        ], content: json_encode([
            'welcome_channel_id' => '111111111111111111',
            'bye_channel_id' => '222222222222222222',
            'staff_role_id' => '333333333333333333',
        ], JSON_THROW_ON_ERROR));

        self::assertResponseIsSuccessful();
        self::assertSame([
            'welcome_channel_id' => '111111111111111111',
            'bye_channel_id' => '222222222222222222',
            'staff_role_id' => '333333333333333333',
        ], $this->jsonPayload($client)['server']['settings']);

        self::assertSame([
            'welcome_channel_id' => '111111111111111111',
            'bye_channel_id' => '222222222222222222',
            'staff_role_id' => '333333333333333333',
        ], $this->connection()->fetchAssociative(
            'SELECT welcome_channel_id, bye_channel_id, staff_role_id FROM discord_servers WHERE discord_id = ?',
            ['123456789'],
        ));

        $client->request('PATCH', '/api/discord-servers/123456789/settings', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            'CONTENT_TYPE' => 'application/json',
        ], content: json_encode([
            'bye_channel_id' => null,
        ], JSON_THROW_ON_ERROR));

        self::assertResponseIsSuccessful();
        self::assertSame([
            'welcome_channel_id' => '111111111111111111',
            'bye_channel_id' => null,
            'staff_role_id' => '333333333333333333',
        ], $this->jsonPayload($client)['server']['settings']);

        $client->request('GET', '/api/discord-servers/123456789/catalogue', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        self::assertResponseIsSuccessful();
        self::assertSame([
            'welcome_channel_id' => '111111111111111111',
            'bye_channel_id' => null,
            'staff_role_id' => '333333333333333333',
        ], $this->jsonPayload($client)['server']['settings']);
    }

    public function testBotCanEnsureRuntimeUserWithDefaultAssignmentsAndStats(): void
    {
        $client = static::createClient();
        $this->resetDatabase();
        $catalogue = $this->seedRuntimeCatalogue();

        $client->request('PUT', '/api/discord-servers/123456789/users/42', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->requestAccessToken($client),
            'CONTENT_TYPE' => 'application/json',
        ], content: '{}');

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $payload = $this->jsonPayload($client);

        self::assertSame('42', $payload['user']['discord_id'] ?? null);
        self::assertSame([
            'id' => $catalogue['rank_id'],
            'discord_id' => 'rank-novice',
            'name' => 'Novice',
            'is_staff' => false,
        ], $payload['user']['rank']);
        self::assertSame([
            'id' => $catalogue['role_id'],
            'name' => 'Comète',
        ], $payload['user']['role']);
        self::assertSame([[
            'id' => $catalogue['element_id'],
            'name' => 'Ambre',
        ]], $payload['user']['elements']);
        self::assertSame([
            ['id' => $catalogue['aura_stat_id'], 'name' => 'Aura', 'value' => 0],
            ['id' => $catalogue['force_stat_id'], 'name' => 'Force', 'value' => 0],
        ], $payload['user']['stats']);

        $userRow = $this->connection()->fetchAssociative('SELECT id, discord_id, rank_id, role_id FROM users WHERE server_id = ?', [$catalogue['server_id']]);
        self::assertSame('42', $userRow['discord_id']);
        self::assertSame($catalogue['rank_id'], (int) $userRow['rank_id']);
        self::assertSame($catalogue['role_id'], (int) $userRow['role_id']);
        self::assertSame(1, (int) $this->connection()->fetchOne('SELECT COUNT(*) FROM users_elements WHERE user_id = ? AND element_id = ?', [$userRow['id'], $catalogue['element_id']]));
        self::assertSame(2, (int) $this->connection()->fetchOne('SELECT COUNT(*) FROM user_stats WHERE user_id = ? AND value = 0', [$userRow['id']]));
    }

    public function testBotCanForceStaffRankAndPatchRuntimeAssignments(): void
    {
        $client = static::createClient();
        $this->resetDatabase();
        $catalogue = $this->seedRuntimeCatalogue();

        $token = $this->requestAccessToken($client);
        $client->request('PUT', '/api/discord-servers/123456789/users/42', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            'CONTENT_TYPE' => 'application/json',
        ], content: '{}');

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $client->request('PUT', '/api/discord-servers/123456789/users/42', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            'CONTENT_TYPE' => 'application/json',
        ], content: json_encode([
            'staff' => true,
        ], JSON_THROW_ON_ERROR));

        self::assertResponseIsSuccessful();
        self::assertSame('Staff', $this->jsonPayload($client)['user']['rank']['name']);
        self::assertSame($catalogue['staff_rank_id'], (int) $this->connection()->fetchOne('SELECT rank_id FROM users WHERE discord_id = ?', ['42']));

        $client->request('PATCH', '/api/discord-servers/123456789/users/42', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            'CONTENT_TYPE' => 'application/json',
        ], content: json_encode([
            'rank_id' => $catalogue['rank_id'],
            'role_id' => $catalogue['second_role_id'],
            'element_ids' => [$catalogue['second_element_id']],
        ], JSON_THROW_ON_ERROR));

        self::assertResponseIsSuccessful();
        $payload = $this->jsonPayload($client);

        self::assertSame('Novice', $payload['user']['rank']['name']);
        self::assertSame('Oracle', $payload['user']['role']['name']);
        self::assertSame([[
            'id' => $catalogue['second_element_id'],
            'name' => 'Lune',
        ]], $payload['user']['elements']);
        self::assertSame(1, (int) $this->connection()->fetchOne('SELECT COUNT(*) FROM users_elements WHERE element_id = ?', [$catalogue['second_element_id']]));
        self::assertSame(0, (int) $this->connection()->fetchOne('SELECT COUNT(*) FROM users_elements WHERE element_id = ?', [$catalogue['element_id']]));
    }

    public function testBotCanUpsertRuntimeUserStats(): void
    {
        $client = static::createClient();
        $this->resetDatabase();
        $catalogue = $this->seedRuntimeCatalogue();

        $token = $this->requestAccessToken($client);
        $client->request('PUT', '/api/discord-servers/123456789/users/42', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            'CONTENT_TYPE' => 'application/json',
        ], content: '{}');

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $client->request('PUT', '/api/discord-servers/123456789/users/42/stats', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            'CONTENT_TYPE' => 'application/json',
        ], content: json_encode([
            'stats' => [
                ['id' => $catalogue['force_stat_id'], 'value' => 12],
                ['id' => $catalogue['aura_stat_id'], 'value' => 7],
            ],
        ], JSON_THROW_ON_ERROR));

        self::assertResponseIsSuccessful();
        self::assertSame([
            ['id' => $catalogue['aura_stat_id'], 'name' => 'Aura', 'value' => 7],
            ['id' => $catalogue['force_stat_id'], 'name' => 'Force', 'value' => 12],
        ], $this->jsonPayload($client)['user']['stats']);

        $userId = (int) $this->connection()->fetchOne('SELECT id FROM users WHERE discord_id = ?', ['42']);
        self::assertSame(12, (int) $this->connection()->fetchOne('SELECT value FROM user_stats WHERE user_id = ? AND stat_id = ?', [$userId, $catalogue['force_stat_id']]));
        self::assertSame(7, (int) $this->connection()->fetchOne('SELECT value FROM user_stats WHERE user_id = ? AND stat_id = ?', [$userId, $catalogue['aura_stat_id']]));
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
     * @return array<string, int>
     */
    private function seedRuntimeCatalogue(): array
    {
        $this->connection()->insert('discord_servers', [
            'discord_id' => '123456789',
            'name' => 'Serveur Test',
            'icon' => 'server-icon',
            'created_at' => '2026-07-06 10:00:00',
            'updated_at' => '2026-07-06 10:00:00',
        ]);
        $serverId = (int) $this->connection()->lastInsertId();

        $this->connection()->insert('ranks', [
            'server_id' => $serverId,
            'discord_id' => 'rank-novice',
            'name' => 'Novice',
            'percentage' => 100,
            'bye_title' => null,
            'is_staff' => 0,
            'created_at' => '2026-07-06 10:00:00',
            'updated_at' => '2026-07-06 10:00:00',
        ]);
        $rankId = (int) $this->connection()->lastInsertId();

        $this->connection()->insert('ranks', [
            'server_id' => $serverId,
            'discord_id' => 'rank-staff',
            'name' => 'Staff',
            'percentage' => 0,
            'bye_title' => null,
            'is_staff' => 1,
            'staff_scope_id' => $serverId,
            'created_at' => '2026-07-06 10:00:00',
            'updated_at' => '2026-07-06 10:00:00',
        ]);
        $staffRankId = (int) $this->connection()->lastInsertId();

        $this->connection()->insert('roles', [
            'server_id' => $serverId,
            'name' => 'Comète',
            'percentage' => 100,
            'emoji_source' => 'unicode',
            'emoji_unicode' => '🎭',
            'emoji_id' => null,
            'emoji_name' => null,
            'emoji_animated' => 0,
        ]);
        $roleId = (int) $this->connection()->lastInsertId();

        $this->connection()->insert('roles', [
            'server_id' => $serverId,
            'name' => 'Oracle',
            'percentage' => 0,
            'emoji_source' => 'unicode',
            'emoji_unicode' => '🔮',
            'emoji_id' => null,
            'emoji_name' => null,
            'emoji_animated' => 0,
        ]);
        $secondRoleId = (int) $this->connection()->lastInsertId();

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

        $this->connection()->insert('elements', [
            'server_id' => $serverId,
            'name' => 'Lune',
            'emoji_source' => 'unicode',
            'emoji_unicode' => '🌙',
            'emoji_id' => null,
            'emoji_name' => null,
            'emoji_animated' => 0,
        ]);
        $secondElementId = (int) $this->connection()->lastInsertId();

        $this->connection()->insert('stats', [
            'server_id' => $serverId,
            'name' => 'Force',
        ]);
        $forceStatId = (int) $this->connection()->lastInsertId();

        $this->connection()->insert('stats', [
            'server_id' => $serverId,
            'name' => 'Aura',
        ]);
        $auraStatId = (int) $this->connection()->lastInsertId();

        return [
            'server_id' => $serverId,
            'rank_id' => $rankId,
            'staff_rank_id' => $staffRankId,
            'role_id' => $roleId,
            'second_role_id' => $secondRoleId,
            'element_id' => $elementId,
            'second_element_id' => $secondElementId,
            'force_stat_id' => $forceStatId,
            'aura_stat_id' => $auraStatId,
        ];
    }

    /**
     * @param array<string, mixed> $expected
     */
    private function assertJsonPayloadContains(array $expected): void
    {
        $this->assertArrayContains($expected, $this->jsonPayload());
    }

    /**
     * @param array<mixed> $expected
     * @param array<mixed> $actual
     */
    private function assertArrayContains(array $expected, array $actual): void
    {
        foreach ($expected as $key => $value) {
            self::assertArrayHasKey($key, $actual);
            if (\is_array($value)) {
                self::assertIsArray($actual[$key]);
                $this->assertArrayContains($value, $actual[$key]);

                continue;
            }

            self::assertSame($value, $actual[$key]);
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
