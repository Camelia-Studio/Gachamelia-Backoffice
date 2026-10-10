<?php

declare(strict_types=1);

namespace App\Tests\Progression;

use App\Entity\DiscordServer;
use App\Entity\GachaUser;
use App\Entity\Rank;
use App\Progression\ProgressionService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;

final class ProgressionServiceTest extends TestCase
{
    public function testConfiguredGainCrossesRanksAndThenCreatesConstellations(): void
    {
        $server = new DiscordServer('guild-1', 'Guild');
        $ranks = [];
        for ($i = 1; $i <= 5; ++$i) {
            $rank = new Rank($server, 'discord-'.$i, $i.'★', 1 === $i ? 100 : 0);
            (new \ReflectionProperty(Rank::class, 'id'))->setValue($rank, $i);
            $ranks[$i] = $rank;
        }
        $server->updateProgressionSettings([
            'rank_ids' => [1, 2, 3, 4, 5],
            'thresholds' => [10, 10, 10, 10],
            'quarter_percentages' => [20, 40, 60, 80],
            'constellation_threshold' => 12,
            'message_xp' => 26,
            'voice_xp' => 7,
            'voice_interval_minutes' => 5,
        ]);

        $repository = self::createStub(EntityRepository::class);
        $repository->method('findOneBy')->willReturnCallback(static fn (array $criteria): ?Rank => $ranks[$criteria['id']] ?? null);
        $manager = self::createStub(EntityManagerInterface::class);
        $manager->method('getRepository')->willReturn($repository);
        $progression = new ProgressionService($manager);
        $user = new GachaUser($server, 'user-1', $ranks[1]);

        self::assertSame(['gain' => 26, 'rank_ups' => [2, 3], 'new_constellations' => 0, 'quarter' => 3], $progression->grant($user, 'message'));
        self::assertSame($ranks[3], $user->rank());
        self::assertSame(6, $user->progressionXp());
        self::assertSame(26, $user->totalXp());

        self::assertSame(['gain' => 26, 'rank_ups' => [4, 5], 'new_constellations' => 1, 'quarter' => 0], $progression->grant($user, 'message'));
        self::assertSame($ranks[5], $user->rank());
        self::assertSame(1, $user->constellations());
        self::assertSame(0, $user->progressionXp());
        self::assertSame(52, $user->totalXp());
    }

    public function testUnconfiguredServerCannotGrantXp(): void
    {
        $server = new DiscordServer('guild-2', 'Guild');
        $user = new GachaUser($server, 'user-2');
        $manager = self::createStub(EntityManagerInterface::class);
        $this->expectExceptionMessage('progression_not_configured');
        (new ProgressionService($manager))->grant($user, 'message');
    }
}
