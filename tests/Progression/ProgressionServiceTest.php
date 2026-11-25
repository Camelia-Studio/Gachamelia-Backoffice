<?php

declare(strict_types=1);

namespace App\Tests\Progression;

use App\Entity\DailyXp;
use App\Entity\DiscordServer;
use App\Entity\GachaUser;
use App\Entity\ProgressionEvent;
use App\Entity\Rank;
use App\Enum\ProgressionEventType;
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
            'message_channel_ids' => ['123'],
            'voice_channel_ids' => ['456'],
        ]);

        $rankRepository = self::createStub(EntityRepository::class);
        $rankRepository->method('findOneBy')->willReturnCallback(static fn (array $criteria): ?Rank => $ranks[$criteria['id']] ?? null);
        /** @var list<object> $persisted */
        $persisted = [];
        $dailyRepository = self::createStub(EntityRepository::class);
        $dailyRepository->method('findOneBy')->willReturnCallback(static function (array $criteria) use (&$persisted): ?DailyXp {
            foreach ($persisted as $entity) {
                if ($entity instanceof DailyXp && $entity->user() === $criteria['user'] && $entity->day()->format('Y-m-d') === $criteria['day']->format('Y-m-d')) {
                    return $entity;
                }
            }

            return null;
        });
        $manager = self::createStub(EntityManagerInterface::class);
        $manager->method('getRepository')->willReturnCallback(static fn (string $class): EntityRepository => DailyXp::class === $class ? $dailyRepository : $rankRepository);
        $manager->method('persist')->willReturnCallback(static function (object $entity) use (&$persisted): void {
            $persisted[] = $entity;
        });
        $progression = new ProgressionService($manager);
        $user = new GachaUser($server, 'user-1', $ranks[1]);

        try {
            $progression->grant($user, 'message', '999');
            self::fail('An unlisted channel must not grant XP.');
        } catch (\DomainException $exception) {
            self::assertSame('xp_channel_not_allowed', $exception->getMessage());
            self::assertSame(0, $user->totalXp());
        }

        self::assertSame(['gain' => 26, 'rank_ups' => [2, 3], 'new_constellations' => 0, 'quarter' => 3], $progression->grant($user, 'message', '123'));
        self::assertSame($ranks[3], $user->rank());
        self::assertSame(6, $user->progressionXp());
        self::assertSame(26, $user->totalXp());

        self::assertSame(['gain' => 26, 'rank_ups' => [4, 5], 'new_constellations' => 1, 'quarter' => 0], $progression->grant($user, 'message', '123'));
        self::assertSame($ranks[5], $user->rank());
        self::assertSame(1, $user->constellations());
        self::assertSame(0, $user->progressionXp());
        self::assertSame(52, $user->totalXp());

        $progression->grant($user, 'voice', '456');

        $events = array_values(array_map(
            static fn (ProgressionEvent $event): array => [$event->type(), $event->tier(), $event->previousValue(), $event->newValue(), $event->rankName()],
            array_filter($persisted, static fn (object $entity): bool => $entity instanceof ProgressionEvent),
        ));
        self::assertSame([
            [ProgressionEventType::Quarter, 1, 0, 4, '1★'],
            [ProgressionEventType::Rank, 2, 1, 2, '2★'],
            [ProgressionEventType::Quarter, 2, 0, 4, '2★'],
            [ProgressionEventType::Rank, 3, 2, 3, '3★'],
            [ProgressionEventType::Quarter, 3, 0, 3, '3★'],
            [ProgressionEventType::Quarter, 3, 3, 4, '3★'],
            [ProgressionEventType::Rank, 4, 3, 4, '4★'],
            [ProgressionEventType::Quarter, 4, 0, 4, '4★'],
            [ProgressionEventType::Rank, 5, 4, 5, '5★'],
            [ProgressionEventType::Constellation, 5, 0, 1, '5★'],
        ], $events);
        $rankEvent = array_values(array_filter($persisted, static fn (object $entity): bool => $entity instanceof ProgressionEvent && ProgressionEventType::Rank === $entity->type()))[0];
        self::assertSame($ranks[2], $rankEvent->rank());

        $dailyXp = array_values(array_filter($persisted, static fn (object $entity): bool => $entity instanceof DailyXp));
        self::assertCount(1, $dailyXp);
        self::assertSame(52, $dailyXp[0]->messageXp());
        self::assertSame(7, $dailyXp[0]->voiceXp());
    }

    public function testUnconfiguredServerCannotGrantXp(): void
    {
        $server = new DiscordServer('guild-2', 'Guild');
        $user = new GachaUser($server, 'user-2');
        $manager = self::createStub(EntityManagerInterface::class);
        $this->expectExceptionMessage('progression_not_configured');
        (new ProgressionService($manager))->grant($user, 'message', '123');
    }
}
