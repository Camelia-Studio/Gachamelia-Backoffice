<?php

declare(strict_types=1);

namespace App\Progression;

final class ProgressionSettings
{
    public const array DEFAULTS = [
        'rank_ids' => [null, null, null, null, null],
        'thresholds' => [10000, 150000, 450000, 960000],
        'quarter_percentages' => [20, 40, 60, 80],
        'constellation_threshold' => 1111111,
        'message_xp' => 7,
        'voice_xp' => 15,
        'voice_interval_minutes' => 5,
    ];

    /**
     * @param array<string, mixed> $input
     *
     * @return array{rank_ids: list<int|null>, thresholds: list<int>, quarter_percentages: list<int>, constellation_threshold: int, message_xp: int, voice_xp: int, voice_interval_minutes: int}|null
     */
    public static function validate(array $input): ?array
    {
        $rankIds = $input['rank_ids'] ?? null;
        $thresholds = $input['thresholds'] ?? null;
        $quarters = $input['quarter_percentages'] ?? null;
        if (!\is_array($rankIds) || 5 !== \count($rankIds) || !\is_array($thresholds) || 4 !== \count($thresholds)
            || !\is_array($quarters) || 4 !== \count($quarters)) {
            return null;
        }

        $rankIds = array_values($rankIds);
        foreach ($rankIds as $rankId) {
            if (!\is_int($rankId) && null !== $rankId) {
                return null;
            }
            if (\is_int($rankId) && $rankId <= 0) {
                return null;
            }
        }
        $configured = array_filter($rankIds, static fn (?int $id): bool => null !== $id);
        if (\count(array_unique($configured)) !== \count($configured)) {
            return null;
        }

        foreach ($thresholds as $threshold) {
            if (!\is_int($threshold) || $threshold <= 0) {
                return null;
            }
        }
        $previous = 0;
        foreach ($quarters as $quarter) {
            if (!\is_int($quarter) || $quarter <= $previous || $quarter >= 100) {
                return null;
            }
            $previous = $quarter;
        }
        foreach (['constellation_threshold', 'message_xp', 'voice_xp', 'voice_interval_minutes'] as $key) {
            if (!\is_int($input[$key] ?? null) || $input[$key] <= 0) {
                return null;
            }
        }

        return [
            'rank_ids' => $rankIds,
            'thresholds' => array_values($thresholds),
            'quarter_percentages' => array_values($quarters),
            'constellation_threshold' => $input['constellation_threshold'],
            'message_xp' => $input['message_xp'],
            'voice_xp' => $input['voice_xp'],
            'voice_interval_minutes' => $input['voice_interval_minutes'],
        ];
    }
}
