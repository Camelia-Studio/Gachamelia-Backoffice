<?php

namespace App\Tests\Backoffice;

use App\Entity\DiscordUser;
use PHPUnit\Framework\TestCase;

final class DiscordUserGlobalRolesTest extends TestCase
{
    public function testDiscordUsersHaveEmptyGlobalRolesByDefault(): void
    {
        $user = new DiscordUser('42', 'melaine', 'Melaine', null);

        self::assertSame([], $user->globalRoles());
        self::assertFalse($user->hasGlobalRole(DiscordUser::GLOBAL_ROLE_TEMPLATE_ADMIN));
    }

    public function testDiscordUsersCanBeGrantedGlobalBackofficeRoles(): void
    {
        $user = new DiscordUser('42', 'melaine', 'Melaine', null);

        $user->replaceGlobalRoles([
            DiscordUser::GLOBAL_ROLE_TEMPLATE_ADMIN,
            DiscordUser::GLOBAL_ROLE_TEMPLATE_ADMIN,
            ' ',
        ]);

        self::assertSame([DiscordUser::GLOBAL_ROLE_TEMPLATE_ADMIN], $user->globalRoles());
        self::assertTrue($user->hasGlobalRole(DiscordUser::GLOBAL_ROLE_TEMPLATE_ADMIN));
    }
}
