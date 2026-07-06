<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'user_stats')]
class UserStat
{
    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: GachaUser::class)]
    #[ORM\JoinColumn(name: 'user_id', nullable: false, onDelete: 'CASCADE')]
    private GachaUser $user;

    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: Stat::class)]
    #[ORM\JoinColumn(name: 'stat_id', nullable: false, onDelete: 'CASCADE')]
    private Stat $stat;

    #[ORM\Column(options: ['default' => 0])]
    private int $value;

    public function __construct(GachaUser $user, Stat $stat, int $value = 0)
    {
        $this->user = $user;
        $this->stat = $stat;
        $this->value = $value;
    }

    public function user(): GachaUser
    {
        return $this->user;
    }

    public function stat(): Stat
    {
        return $this->stat;
    }

    public function value(): int
    {
        return $this->value;
    }

    public function updateValue(int $value): void
    {
        $this->value = $value;
    }
}
