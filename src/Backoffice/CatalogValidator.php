<?php

declare(strict_types=1);

namespace App\Backoffice;

use App\Entity\ByeMessage;
use App\Entity\CatalogTemplate;
use App\Entity\CatalogTemplateByeMessage;
use App\Entity\CatalogTemplateElement;
use App\Entity\CatalogTemplateRank;
use App\Entity\CatalogTemplateRankStat;
use App\Entity\CatalogTemplateRole;
use App\Entity\CatalogTemplateStat;
use App\Entity\CatalogTemplateWelcomeMessage;
use App\Entity\CharacterRole;
use App\Entity\DiscordServer;
use App\Entity\Element;
use App\Entity\Rank;
use App\Entity\RankStat;
use App\Entity\Stat;
use App\Entity\WelcomeMessage;
use Doctrine\ORM\EntityManagerInterface;

final readonly class CatalogValidator
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function validateServer(DiscordServer $server): CatalogValidationResult
    {
        $ranks = $this->entityManager->getRepository(Rank::class)->findBy(['server' => $server]);
        $roles = $this->entityManager->getRepository(CharacterRole::class)->findBy(['server' => $server]);
        $rankStats = $this->entityManager->getRepository(RankStat::class)->findBy(['server' => $server]);
        $nonStaffRanks = array_filter($ranks, static fn (Rank $rank): bool => !$rank->isStaff());
        $staffCount = \count($ranks) - \count($nonStaffRanks);

        $errors = $this->blockingErrors(
            \count($nonStaffRanks),
            array_sum(array_map(static fn (Rank $rank): int => $rank->percentage(), $ranks)),
            \count($roles),
            array_sum(array_map(static fn (CharacterRole $role): int => $role->percentage(), $roles)),
            $this->entityManager->getRepository(Element::class)->count(['server' => $server]),
            $staffCount,
            $this->hasInvalidServerRankStatTotal($ranks, $rankStats),
        );

        $warnings = [];
        if (0 === $this->entityManager->getRepository(Stat::class)->count(['server' => $server])) {
            $warnings[] = 'empty_stats';
        }
        if (null === $server->welcomeChannelId()) {
            $warnings[] = 'missing_welcome_channel';
        }
        if (null === $server->byeChannelId()) {
            $warnings[] = 'missing_bye_channel';
        }
        if (null !== $server->staffRoleId() && 0 === $staffCount) {
            $warnings[] = 'staff_role_without_staff_rank';
        }
        if (0 === $this->entityManager->getRepository(WelcomeMessage::class)->count(['server' => $server])) {
            $warnings[] = 'empty_welcome_messages';
        }
        if (0 === $this->entityManager->getRepository(ByeMessage::class)->count(['server' => $server])) {
            $warnings[] = 'empty_bye_messages';
        }

        return new CatalogValidationResult($errors, $warnings);
    }

    public function validateTemplate(CatalogTemplate $template): CatalogValidationResult
    {
        $ranks = $this->entityManager->getRepository(CatalogTemplateRank::class)->findBy(['template' => $template]);
        $roles = $this->entityManager->getRepository(CatalogTemplateRole::class)->findBy(['template' => $template]);
        $rankStats = $this->entityManager->getRepository(CatalogTemplateRankStat::class)->findBy(['template' => $template]);
        $nonStaffRanks = array_filter($ranks, static fn (CatalogTemplateRank $rank): bool => !$rank->isStaff());
        $staffCount = \count($ranks) - \count($nonStaffRanks);

        $errors = $this->blockingErrors(
            \count($nonStaffRanks),
            array_sum(array_map(static fn (CatalogTemplateRank $rank): int => $rank->percentage(), $ranks)),
            \count($roles),
            array_sum(array_map(static fn (CatalogTemplateRole $role): int => $role->percentage(), $roles)),
            $this->entityManager->getRepository(CatalogTemplateElement::class)->count(['template' => $template]),
            $staffCount,
            $this->hasInvalidTemplateRankStatTotal($ranks, $rankStats),
        );

        $warnings = [];
        if (0 === $this->entityManager->getRepository(CatalogTemplateStat::class)->count(['template' => $template])) {
            $warnings[] = 'empty_stats';
        }
        if (0 === $this->entityManager->getRepository(CatalogTemplateWelcomeMessage::class)->count(['template' => $template])) {
            $warnings[] = 'empty_welcome_messages';
        }
        if (0 === $this->entityManager->getRepository(CatalogTemplateByeMessage::class)->count(['template' => $template])) {
            $warnings[] = 'empty_bye_messages';
        }

        return new CatalogValidationResult($errors, $warnings);
    }

    /**
     * @return list<string>
     */
    private function blockingErrors(
        int $nonStaffRankCount,
        int $rankWeight,
        int $roleCount,
        int $roleWeight,
        int $elementCount,
        int $staffCount,
        bool $invalidRankStatTotal,
    ): array {
        $errors = [];
        if (0 === $nonStaffRankCount) {
            $errors[] = 'missing_non_staff_rank';
        }
        if (0 === $roleCount) {
            $errors[] = 'empty_roles';
        }
        if (0 === $elementCount) {
            $errors[] = 'empty_elements';
        }
        if ($nonStaffRankCount + $staffCount > 0 && 100 !== $rankWeight) {
            $errors[] = 'invalid_rank_percentage_total';
        }
        if ($roleCount > 0 && 100 !== $roleWeight) {
            $errors[] = 'invalid_role_percentage_total';
        }
        if ($invalidRankStatTotal) {
            $errors[] = 'invalid_rank_stat_percentage_total';
        }
        if ($staffCount > 1) {
            $errors[] = 'multiple_staff_ranks';
        }

        return $errors;
    }

    /**
     * @param list<Rank>     $ranks
     * @param list<RankStat> $rankStats
     */
    private function hasInvalidServerRankStatTotal(array $ranks, array $rankStats): bool
    {
        $totals = [];
        foreach ($rankStats as $rankStat) {
            $rankId = $rankStat->rank()->id();
            if (null !== $rankId) {
                $totals[$rankId] = ($totals[$rankId] ?? 0) + $rankStat->percentage();
            }
        }
        foreach ($ranks as $rank) {
            $rankId = $rank->id();
            if (null === $rankId || 100 !== ($totals[$rankId] ?? 0)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<CatalogTemplateRank>     $ranks
     * @param list<CatalogTemplateRankStat> $rankStats
     */
    private function hasInvalidTemplateRankStatTotal(array $ranks, array $rankStats): bool
    {
        $totals = [];
        foreach ($rankStats as $rankStat) {
            $rankId = $rankStat->rank()->id();
            if (null !== $rankId) {
                $totals[$rankId] = ($totals[$rankId] ?? 0) + $rankStat->percentage();
            }
        }
        foreach ($ranks as $rank) {
            $rankId = $rank->id();
            if (null === $rankId || 100 !== ($totals[$rankId] ?? 0)) {
                return true;
            }
        }

        return false;
    }
}
