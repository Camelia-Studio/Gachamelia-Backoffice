<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'rank_stats')]
class RankStat
{
    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: Rank::class)]
    #[ORM\JoinColumn(name: 'rank_id', nullable: false, onDelete: 'CASCADE')]
    private Rank $rank;

    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: Stat::class)]
    #[ORM\JoinColumn(name: 'stat_id', nullable: false, onDelete: 'CASCADE')]
    private Stat $stat;

    #[ORM\Column]
    private int $percentage;

    public function __construct(Rank $rank, Stat $stat, int $percentage)
    {
        $this->rank = $rank;
        $this->stat = $stat;
        $this->percentage = $percentage;
    }
}
