<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'catalog_template_rank_stats')]
class CatalogTemplateRankStat
{
    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: CatalogTemplateRank::class)]
    #[ORM\JoinColumn(name: 'rank_id', nullable: false, onDelete: 'CASCADE')]
    private CatalogTemplateRank $rank;

    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: CatalogTemplateStat::class)]
    #[ORM\JoinColumn(name: 'stat_id', nullable: false, onDelete: 'CASCADE')]
    private CatalogTemplateStat $stat;

    #[ORM\Column]
    private int $percentage;

    public function __construct(CatalogTemplateRank $rank, CatalogTemplateStat $stat, int $percentage)
    {
        $this->rank = $rank;
        $this->stat = $stat;
        $this->percentage = $percentage;
    }

    public function rank(): CatalogTemplateRank
    {
        return $this->rank;
    }

    public function stat(): CatalogTemplateStat
    {
        return $this->stat;
    }

    public function percentage(): int
    {
        return $this->percentage;
    }

    public function updatePercentage(int $percentage): void
    {
        $this->percentage = $percentage;
    }
}
