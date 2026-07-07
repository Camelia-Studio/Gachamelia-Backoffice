<?php

namespace App\Tests\Discord;

use App\Discord\DiscordGuildResourcesClientInterface;
use App\Discord\DiscordGuildResourcesProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

final class DiscordGuildResourcesProviderTest extends TestCase
{
    public function testItCachesFilteredGuildChannelsAndRoles(): void
    {
        $client = new FakeDiscordGuildResourcesClient(
            [
                ['id' => '100000000000000001', 'name' => 'bienvenue', 'type' => 0],
                ['id' => '100000000000000002', 'name' => 'annonces', 'type' => 5],
                ['id' => '100000000000000003', 'name' => 'vocal', 'type' => 2],
            ],
            [
                ['id' => 'admin', 'name' => '@everyone', 'position' => 0, 'managed' => false],
                ['id' => '200000000000000001', 'name' => 'Membre', 'position' => 1, 'managed' => false],
                ['id' => '200000000000000002', 'name' => 'Staff', 'position' => 8, 'managed' => false],
            ],
        );
        $provider = new DiscordGuildResourcesProvider($client, new ArrayAdapter(), 60);

        $firstPayload = $provider->resourcesForGuild('admin');
        $secondPayload = $provider->resourcesForGuild('admin');

        self::assertSame($firstPayload, $secondPayload);
        self::assertSame(1, $client->channelsCalls);
        self::assertSame(1, $client->rolesCalls);
        self::assertSame([
            ['id' => '100000000000000002', 'name' => 'annonces', 'label' => '#annonces', 'type' => 5],
            ['id' => '100000000000000001', 'name' => 'bienvenue', 'label' => '#bienvenue', 'type' => 0],
        ], $firstPayload['channels']);
        self::assertSame([
            ['id' => '200000000000000002', 'name' => 'Staff', 'label' => '@Staff', 'position' => 8, 'managed' => false],
            ['id' => '200000000000000001', 'name' => 'Membre', 'label' => '@Membre', 'position' => 1, 'managed' => false],
        ], $firstPayload['roles']);
    }

    public function testItKeepsTheBackofficeUsableWhenDiscordFails(): void
    {
        $provider = new DiscordGuildResourcesProvider(new FailingDiscordGuildResourcesClient(), new ArrayAdapter(), 60);

        self::assertSame([
            'channels' => [],
            'roles' => [],
        ], $provider->resourcesForGuild('admin'));
    }
}

final class FakeDiscordGuildResourcesClient implements DiscordGuildResourcesClientInterface
{
    public int $channelsCalls = 0;

    public int $rolesCalls = 0;

    /**
     * @param list<array<string, mixed>> $channels
     * @param list<array<string, mixed>> $roles
     */
    public function __construct(
        private readonly array $channels,
        private readonly array $roles,
    ) {
    }

    public function fetchGuildChannels(string $guildId): array
    {
        ++$this->channelsCalls;

        return $this->channels;
    }

    public function fetchGuildRoles(string $guildId): array
    {
        ++$this->rolesCalls;

        return $this->roles;
    }
}

final class FailingDiscordGuildResourcesClient implements DiscordGuildResourcesClientInterface
{
    public function fetchGuildChannels(string $guildId): array
    {
        throw new \RuntimeException('Discord is unavailable.');
    }

    public function fetchGuildRoles(string $guildId): array
    {
        throw new \RuntimeException('Discord is unavailable.');
    }
}
