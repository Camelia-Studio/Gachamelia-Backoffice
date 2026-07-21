<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'rank_stats')]
class RankStat
{
    #[ORM\ManyToOne(targetEntity: DiscordServer::class)]
    #[ORM\JoinColumn(name: 'server_id', nullable: false, onDelete: 'CASCADE')]
    private DiscordServer $server;

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
        if ($rank->server() !== $stat->server()) {
            throw new \InvalidArgumentException('A rank stat must belong to one server.');
        }

        $this->server = $rank->server();
        $this->rank = $rank;
        $this->stat = $stat;
        $this->percentage = $percentage;
    }

    public function server(): DiscordServer
    {
        return $this->server;
    }

    public function rank(): Rank
    {
        return $this->rank;
    }

    public function stat(): Stat
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
