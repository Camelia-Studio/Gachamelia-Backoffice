<?php

declare(strict_types=1);

namespace App\Tests\Discord;

use App\Discord\DiscordCdnUrlGenerator;
use PHPUnit\Framework\TestCase;

final class DiscordCdnUrlGeneratorTest extends TestCase
{
    public function testGuildIconUrlUsesDiscordCdnWebpEndpoint(): void
    {
        $generator = new DiscordCdnUrlGenerator();

        self::assertSame(
            'https://cdn.discordapp.com/icons/123456789/static-icon.webp?size=64',
            $generator->guildIconUrl('123456789', 'static-icon'),
        );
    }

    public function testGuildIconUrlKeepsAnimatedGuildIconsAnimated(): void
    {
        $generator = new DiscordCdnUrlGenerator();

        self::assertSame(
            'https://cdn.discordapp.com/icons/123456789/a_animated-icon.webp?size=64&animated=true',
            $generator->guildIconUrl('123456789', 'a_animated-icon'),
        );
    }

    public function testGuildIconUrlReturnsNullWithoutIconHash(): void
    {
        $generator = new DiscordCdnUrlGenerator();

        self::assertNull($generator->guildIconUrl('123456789', null));
        self::assertNull($generator->guildIconUrl('123456789', ''));
    }

    public function testUserAvatarUrlUsesDiscordCdnWebpEndpoint(): void
    {
        $generator = new DiscordCdnUrlGenerator();

        self::assertSame(
            'https://cdn.discordapp.com/avatars/42/avatar-hash.webp?size=128',
            $generator->userAvatarUrl('42', 'avatar-hash'),
        );
    }

    public function testUserAvatarUrlKeepsAnimatedAvatarsAnimated(): void
    {
        $generator = new DiscordCdnUrlGenerator();

        self::assertSame(
            'https://cdn.discordapp.com/avatars/42/a_animated-avatar.webp?size=128&animated=true',
            $generator->userAvatarUrl('42', 'a_animated-avatar'),
        );
    }

    public function testUserAvatarUrlReturnsNullWithoutAvatarHash(): void
    {
        $generator = new DiscordCdnUrlGenerator();

        self::assertNull($generator->userAvatarUrl('42', null));
        self::assertNull($generator->userAvatarUrl('42', ''));
    }
}
