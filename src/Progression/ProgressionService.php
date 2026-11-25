<?php

declare(strict_types=1);

namespace App\Progression;

use App\Entity\DiscordServer;
use App\Entity\GachaUser;
use App\Entity\Rank;
use Doctrine\ORM\EntityManagerInterface;

final readonly class ProgressionService
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
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

        $gain = 'message' === $source ? $settings['message_xp'] : $settings['voice_xp'];
        $user->addProgressionXp($gain);
        $rankUps = [];
        $newConstellations = 0;
        while ($index < 4 && $user->progressionXp() >= $settings['thresholds'][$index]) {
            $user->advanceProgression($settings['thresholds'][$index], $ranks[$index + 1]);
            ++$index;
            $rankUps[] = $index + 1;
        }
        if (4 === $index) {
            while ($user->progressionXp() >= $settings['constellation_threshold']) {
                $user->addConstellation($settings['constellation_threshold']);
                ++$newConstellations;
            }
        }

        $threshold = $index < 4 ? $settings['thresholds'][$index] : $settings['constellation_threshold'];
        $quarter = 0;
        if ($index < 4) {
            foreach ($settings['quarter_percentages'] as $percentage) {
                if ($user->progressionXp() / $threshold < $percentage / 100) {
                    break;
                }
                ++$quarter;
            }
        }

        return ['gain' => $gain, 'rank_ups' => $rankUps, 'new_constellations' => $newConstellations, 'quarter' => $quarter];
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
