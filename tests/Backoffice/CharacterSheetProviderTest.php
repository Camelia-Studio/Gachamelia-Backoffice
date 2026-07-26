<?php

declare(strict_types=1);

namespace App\Tests\Backoffice;

use App\Backoffice\CharacterSheetProvider;
use App\Entity\CharacterRole;
use App\Entity\DiscordServer;
use App\Entity\Element;
use App\Entity\GachaUser;
use App\Entity\Rank;
use App\Entity\Stat;
use App\Entity\UserStat;
use App\Tests\Support\DatabaseResetter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class CharacterSheetProviderTest extends KernelTestCase
{
    use DatabaseResetter;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->resetDatabase();
    }

    public function testItBuildsTheRequestedMembersSheetWithDeterministicCollections(): void
    {
        $entityManager = $this->entityManager();

        $server = new DiscordServer('server-1', 'Serveur principal');
        $otherServer = new DiscordServer('server-2', 'Autre serveur');
        $rank = new Rank($server, 'rank-1', 'Floraison', 100);
        $role = new CharacterRole($server, 'Alchimiste', 100, emojiUnicode: '🧪');
        $amber = new Element(
            $server,
            'Ambre',
            'server',
            null,
            '123456789012345678',
            'ambre',
        );
        $moon = new Element($server, 'Lune', emojiUnicode: '🌙');
        $agility = new Stat($server, 'Agilité');
        $ether = new Stat($server, 'Éther');

        $user = new GachaUser($server, '42', $rank, $role);
        $user->addElement($moon);
        $user->addElement($amber);

        $decoyUser = new GachaUser($server, '99');
        $otherServerUser = new GachaUser($otherServer, '42');

        foreach ([
            $server,
            $otherServer,
            $rank,
            $role,
            $amber,
            $moon,
            $agility,
            $ether,
            $user,
            $decoyUser,
            $otherServerUser,
        ] as $entity) {
            $entityManager->persist($entity);
        }
        $entityManager->persist(new UserStat($user, $ether, 12));
        $entityManager->persist(new UserStat($user, $agility, 7));
        $entityManager->flush();

        $sheet = $this->provider()->forMember($server, '42');

        self::assertSame([
            'discord_id' => '42',
            'rank' => [
                'name' => 'Floraison',
                'is_staff' => false,
            ],
            'role' => [
                'name' => 'Alchimiste',
                'emoji' => '🧪',
                'emoji_url' => null,
            ],
            'elements' => [
                [
                    'name' => 'Ambre',
                    'emoji' => null,
                    'emoji_url' => 'https://cdn.discordapp.com/emojis/123456789012345678.webp?size=64&quality=lossless',
                ],
                [
                    'name' => 'Lune',
                    'emoji' => '🌙',
                    'emoji_url' => null,
                ],
            ],
            'stats' => [
                [
                    'name' => 'Agilité',
                    'value' => 7,
                ],
                [
                    'name' => 'Éther',
                    'value' => 12,
                ],
            ],
        ], $sheet);
    }

    public function testItReturnsAnUnassignedSheetWithoutInventingData(): void
    {
        $entityManager = $this->entityManager();
        $server = new DiscordServer('server-1', 'Serveur principal');
        $user = new GachaUser($server, 'empty');

        $entityManager->persist($server);
        $entityManager->persist($user);
        $entityManager->flush();

        self::assertSame([
            'discord_id' => 'empty',
            'rank' => null,
            'role' => null,
            'elements' => [],
            'stats' => [],
        ], $this->provider()->forMember($server, 'empty'));
    }

    public function testItReturnsNullWhenTheMemberHasNoCharacterOnTheServer(): void
    {
        $entityManager = $this->entityManager();
        $server = new DiscordServer('server-1', 'Serveur principal');

        $entityManager->persist($server);
        $entityManager->flush();

        self::assertNull($this->provider()->forMember($server, 'missing'));
    }

    private function provider(): CharacterSheetProvider
    {
        return new CharacterSheetProvider($this->entityManager());
    }

    private function entityManager(): EntityManagerInterface
    {
        return self::getContainer()->get(EntityManagerInterface::class);
    }
}
