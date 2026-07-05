<?php

namespace App\Tests\Discord;

use App\Discord\DiscordGuildAccessResolver;
use PHPUnit\Framework\TestCase;

final class DiscordGuildAccessResolverTest extends TestCase
{
    public function testItKeepsOnlyGuildsSharedByUserAndBot(): void
    {
        $resolver = new DiscordGuildAccessResolver();

        $guilds = $resolver->resolveAccessibleGuilds(
            [
                ['id' => '1', 'name' => 'Avec bot', 'icon' => null, 'owner' => false, 'permissions' => '0'],
                ['id' => '2', 'name' => 'Sans bot', 'icon' => null, 'owner' => false, 'permissions' => '8'],
            ],
            [
                ['id' => '1', 'name' => 'Avec bot', 'icon' => null],
            ],
        );

        self::assertCount(1, $guilds);
        self::assertSame('1', $guilds[0]['id']);
        self::assertSame('Avec bot', $guilds[0]['name']);
    }

    public function testItGrantsConfigurationAccessToOwnersAndAdministrators(): void
    {
        $resolver = new DiscordGuildAccessResolver();

        $guilds = $resolver->resolveAccessibleGuilds(
            [
                ['id' => 'admin', 'name' => 'Admin', 'icon' => null, 'owner' => false, 'permissions' => '8'],
                ['id' => 'owner', 'name' => 'Owner', 'icon' => null, 'owner' => true, 'permissions' => '0'],
                ['id' => 'member', 'name' => 'Member', 'icon' => null, 'owner' => false, 'permissions' => '0'],
            ],
            [
                ['id' => 'admin', 'name' => 'Admin', 'icon' => null],
                ['id' => 'owner', 'name' => 'Owner', 'icon' => null],
                ['id' => 'member', 'name' => 'Member', 'icon' => null],
            ],
        );

        self::assertTrue($guilds[0]['canManageConfiguration']);
        self::assertTrue($guilds[1]['canManageConfiguration']);
        self::assertFalse($guilds[2]['canManageConfiguration']);
    }
}
