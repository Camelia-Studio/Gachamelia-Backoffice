<?php

declare(strict_types=1);

namespace App\Progression;

use App\Entity\DailyXp;
use App\Entity\DiscordServer;
use App\Entity\GachaUser;
use App\Entity\ProgressionEvent;
use App\Entity\Rank;
use App\Enum\ProgressionEventType;
use Doctrine\ORM\EntityManagerInterface;

final readonly class ProgressionService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private string $timezone = 'Europe/Paris',
    ) {
    }

    /** @return array{gain: int, rank_ups: list<int>, new_constellations: int, quarter: int} */
    public function grant(GachaUser $user, string $source, string $channelId): array
    {
        $server = $user->server();
        $settings = ProgressionSettings::validate($server->progressionSettings());
        if (null === $settings || \in_array(null, $settings['rank_ids'], true)) {
            throw new \DomainException('progression_not_configured');
        }
        if (!\in_array($source, ['message', 'voice'], true)) {
            throw new \InvalidArgumentException('invalid_xp_source');
        }
        $allowedChannels = 'message' === $source ? $settings['message_channel_ids'] : $settings['voice_channel_ids'];
        if (!\in_array($channelId, $allowedChannels, true)) {
            throw new \DomainException('xp_channel_not_allowed');
        }
        $ranks = $this->ranks($server, $settings['rank_ids']);
        $rank = $user->rank();
        $index = $rank instanceof Rank ? array_search((int) $rank->id(), $settings['rank_ids'], true) : false;
        if (false === $index) {
            throw new \DomainException('user_rank_not_in_progression');
        }

        $now = new \DateTimeImmutable('now', new \DateTimeZone($this->timezone));
        $quarterBefore = $this->quarter($user->progressionXp(), $index, $settings);

        $gain = 'message' === $source ? $settings['message_xp'] : $settings['voice_xp'];
        $user->addProgressionXp($gain);
        $this->recordDailyXp($user, $source, $gain, $now);

        $rankUps = [];
        $newConstellations = 0;
        while ($index < 4 && $user->progressionXp() >= $settings['thresholds'][$index]) {
            if ($quarterBefore < 4) {
                $this->record($ranks, $user, ProgressionEventType::Quarter, $index + 1, $quarterBefore, 4, $now);
            }
            $user->advanceProgression($settings['thresholds'][$index], $ranks[$index + 1]);
            ++$index;
            $rankUps[] = $index + 1;
            $this->record($ranks, $user, ProgressionEventType::Rank, $index + 1, $index, $index + 1, $now);
            $quarterBefore = 0;
        }
        if (4 === $index) {
            while ($user->progressionXp() >= $settings['constellation_threshold']) {
                $user->addConstellation($settings['constellation_threshold']);
                ++$newConstellations;
                $this->record($ranks, $user, ProgressionEventType::Constellation, 5, $user->constellations() - 1, $user->constellations(), $now);
            }
        }

        $quarter = $this->quarter($user->progressionXp(), $index, $settings);
        if ($quarter !== $quarterBefore) {
            $this->record($ranks, $user, ProgressionEventType::Quarter, $index + 1, $quarterBefore, $quarter, $now);
        }

        return ['gain' => $gain, 'rank_ups' => $rankUps, 'new_constellations' => $newConstellations, 'quarter' => $quarter];
    }

    /** @param array{thresholds: list<int>, quarter_percentages: list<int>} $settings */
    private function quarter(int $progressionXp, int $index, array $settings): int
    {
        if ($index >= 4) {
            return 0;
        }

        $quarter = 0;
        foreach ($settings['quarter_percentages'] as $percentage) {
            if ($progressionXp / $settings['thresholds'][$index] < $percentage / 100) {
                break;
            }
            ++$quarter;
        }

        return $quarter;
    }

    /** @param list<Rank> $ranks rangs de la progression, du palier 1 au palier 5 */
    private function record(array $ranks, GachaUser $user, ProgressionEventType $type, int $tier, int $previous, int $new, \DateTimeImmutable $now): void
    {
        $this->entityManager->persist(new ProgressionEvent($user, $type, $tier, $ranks[$tier - 1], $previous, $new, $now));
    }

    private function recordDailyXp(GachaUser $user, string $source, int $gain, \DateTimeImmutable $now): void
    {
        $day = $now->setTime(0, 0);
        $dailyXp = $this->entityManager->getRepository(DailyXp::class)->findOneBy(['user' => $user, 'day' => $day]);
        if (!$dailyXp instanceof DailyXp) {
            $dailyXp = new DailyXp($user, $day);
            $this->entityManager->persist($dailyXp);
        }
        $dailyXp->add($source, $gain);
    }

    /** @param list<int> $ids
     * @return list<Rank>
     */
    private function ranks(DiscordServer $server, array $ids): array
    {
        $ranks = [];
        foreach ($ids as $id) {
            $rank = $this->entityManager->getRepository(Rank::class)->findOneBy(['server' => $server, 'id' => $id]);
            if (!$rank instanceof Rank || $rank->isStaff()) {
                throw new \DomainException('progression_rank_unavailable');
            }
            $ranks[] = $rank;
        }

        return $ranks;
    }
}
