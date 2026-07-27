<?php

declare(strict_types=1);

namespace App\Backoffice\Csv;

use App\Entity\ByeMessage;
use App\Entity\CatalogTemplate;
use App\Entity\CharacterRole;
use App\Entity\DiscordServer;
use App\Entity\Element;
use App\Entity\Rank;
use App\Entity\RankStat;
use App\Entity\Stat;
use App\Entity\WelcomeMessage;

final class ServerCatalogCsvTargetAdapter extends AbstractCatalogCsvTargetAdapter
{
    public function supports(DiscordServer|CatalogTemplate $target): bool
    {
        return $target instanceof DiscordServer;
    }

    protected function entityClass(CatalogCsvSection $section): string
    {
        return match ($section) {
            CatalogCsvSection::Ranks => Rank::class,
            CatalogCsvSection::RankStats => RankStat::class,
            CatalogCsvSection::WelcomeMessages => WelcomeMessage::class,
            CatalogCsvSection::ByeMessages => ByeMessage::class,
            CatalogCsvSection::Roles => CharacterRole::class,
            CatalogCsvSection::Stats => Stat::class,
            CatalogCsvSection::Elements => Element::class,
        };
    }

    protected function scopeField(): string
    {
        return 'server';
    }

    public function validateApply(
        DiscordServer|CatalogTemplate $target,
        CatalogCsvSection $section,
        CatalogCsvPreview $preview,
        array $discordRoleIdsByLine,
    ): void {
        if (!$target instanceof DiscordServer || CatalogCsvSection::Ranks !== $section) {
            return;
        }

        $usedDiscordIds = [];
        foreach ($this->entities($target, CatalogCsvSection::Ranks) as $rank) {
            if ($rank instanceof Rank) {
                $usedDiscordIds[$rank->discordId()] = true;
            }
        }
        foreach ($preview->discordRoleLines() as $line) {
            $discordId = trim($discordRoleIdsByLine[$line] ?? '');
            if ('' === $discordId) {
                throw new \InvalidArgumentException(\sprintf('discord_role_mapping_required:%d', $line));
            }
            if (isset($usedDiscordIds[$discordId])) {
                throw new \InvalidArgumentException(\sprintf('discord_role_mapping_conflict:%d', $line));
            }
            $usedDiscordIds[$discordId] = true;
        }
    }

    protected function createRank(
        DiscordServer|CatalogTemplate $target,
        array $values,
        int $line,
        array $discordRoleIdsByLine,
        array $existingRanks,
    ): object {
        if (!$target instanceof DiscordServer) {
            throw new \LogicException('Invalid server catalogue target.');
        }
        $discordId = trim($discordRoleIdsByLine[$line] ?? '');
        if ('' === $discordId) {
            throw new \InvalidArgumentException(\sprintf('discord_role_mapping_required:%d', $line));
        }
        foreach ($existingRanks as $rank) {
            if ($rank instanceof Rank && $rank->discordId() === $discordId) {
                throw new \InvalidArgumentException(\sprintf('discord_role_mapping_conflict:%d', $line));
            }
        }

        return new Rank(
            $target,
            $discordId,
            (string) $values['nom'],
            (int) $values['pourcentage'],
            $values['titre_depart'] instanceof \Stringable || \is_string($values['titre_depart'])
                ? (string) $values['titre_depart']
                : null,
            (bool) $values['est_staff'],
        );
    }

    protected function updateRank(object $rank, array $values): void
    {
        if (!$rank instanceof Rank) {
            throw new \LogicException('Invalid server catalogue rank.');
        }
        $rank->updateConfiguration(
            $rank->discordId(),
            (string) $values['nom'],
            (int) $values['pourcentage'],
            \is_string($values['titre_depart']) ? $values['titre_depart'] : null,
            (bool) $values['est_staff'],
        );
    }
}
