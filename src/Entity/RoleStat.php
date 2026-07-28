<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'role_stats')]
class RoleStat
{
    #[ORM\ManyToOne(targetEntity: DiscordServer::class)]
    #[ORM\JoinColumn(name: 'server_id', nullable: false, onDelete: 'CASCADE')]
    private DiscordServer $server;

    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: CharacterRole::class)]
    #[ORM\JoinColumn(name: 'role_id', nullable: false, onDelete: 'CASCADE')]
    private CharacterRole $role;

    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: Stat::class)]
    #[ORM\JoinColumn(name: 'stat_id', nullable: false, onDelete: 'CASCADE')]
    private Stat $stat;

    public function __construct(CharacterRole $role, Stat $stat, #[ORM\Column]
        private int $percentage)
    {
        if ($role->server() !== $stat->server()) {
            throw new \InvalidArgumentException('A role stat must belong to one server.');
        }

        $this->server = $role->server();
        $this->role = $role;
        $this->stat = $stat;
    }

    public function server(): DiscordServer
    {
        return $this->server;
    }

    public function role(): CharacterRole
    {
        return $this->role;
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
