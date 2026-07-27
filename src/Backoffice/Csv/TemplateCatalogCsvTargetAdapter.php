<?php

declare(strict_types=1);

namespace App\Backoffice\Csv;

use App\Entity\CatalogTemplate;
use App\Entity\CatalogTemplateByeMessage;
use App\Entity\CatalogTemplateElement;
use App\Entity\CatalogTemplateRank;
use App\Entity\CatalogTemplateRankStat;
use App\Entity\CatalogTemplateRole;
use App\Entity\CatalogTemplateStat;
use App\Entity\CatalogTemplateWelcomeMessage;
use App\Entity\DiscordServer;
use Symfony\Component\String\Slugger\AsciiSlugger;

final class TemplateCatalogCsvTargetAdapter extends AbstractCatalogCsvTargetAdapter
{
    public function supports(DiscordServer|CatalogTemplate $target): bool
    {
        return $target instanceof CatalogTemplate;
    }

    protected function entityClass(CatalogCsvSection $section): string
    {
        return match ($section) {
            CatalogCsvSection::Ranks => CatalogTemplateRank::class,
            CatalogCsvSection::RankStats => CatalogTemplateRankStat::class,
            CatalogCsvSection::WelcomeMessages => CatalogTemplateWelcomeMessage::class,
            CatalogCsvSection::ByeMessages => CatalogTemplateByeMessage::class,
            CatalogCsvSection::Roles => CatalogTemplateRole::class,
            CatalogCsvSection::Stats => CatalogTemplateStat::class,
            CatalogCsvSection::Elements => CatalogTemplateElement::class,
        };
    }

    protected function scopeField(): string
    {
        return 'template';
    }

    protected function createRank(
        DiscordServer|CatalogTemplate $target,
        array $values,
        int $line,
        array $discordRoleIdsByLine,
        array $existingRanks,
    ): object {
        if (!$target instanceof CatalogTemplate) {
            throw new \LogicException('Invalid template catalogue target.');
        }

        $usedKeys = [];
        foreach ($existingRanks as $rank) {
            if ($rank instanceof CatalogTemplateRank) {
                $usedKeys[$rank->roleKey()] = true;
            }
        }
        $base = (new AsciiSlugger())->slug((string) $values['nom'])->lower()->toString();
        $base = '' === $base ? 'rang' : $base;
        $roleKey = $base;
        for ($suffix = 2; isset($usedKeys[$roleKey]); ++$suffix) {
            $roleKey = \sprintf('%s-%d', $base, $suffix);
        }

        return new CatalogTemplateRank(
            $target,
            $roleKey,
            (string) $values['nom'],
            (int) $values['pourcentage'],
            \is_string($values['titre_depart']) ? $values['titre_depart'] : null,
            (bool) $values['est_staff'],
        );
    }

    protected function updateRank(object $rank, array $values): void
    {
        if (!$rank instanceof CatalogTemplateRank) {
            throw new \LogicException('Invalid template catalogue rank.');
        }
        $rank->updateConfiguration(
            $rank->roleKey(),
            (string) $values['nom'],
            (int) $values['pourcentage'],
            \is_string($values['titre_depart']) ? $values['titre_depart'] : null,
            (bool) $values['est_staff'],
        );
    }
}
