<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'ranks')]
#[ORM\UniqueConstraint(name: 'uniq_ranks_server_discord_id', columns: ['server_id', 'discord_id'])]
#[ORM\UniqueConstraint(name: 'uniq_ranks_server_name', columns: ['server_id', 'name'])]
class Rank
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: DiscordServer::class)]
    #[ORM\JoinColumn(name: 'server_id', nullable: false, onDelete: 'CASCADE')]
    private DiscordServer $server;

    #[ORM\Column(name: 'discord_id', length: 32)]
    private string $discordId;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column]
    private int $percentage;

    #[ORM\Column(name: 'bye_title', length: 255, nullable: true)]
    private ?string $byeTitle;

    #[ORM\Column(name: 'is_staff', options: ['default' => false])]
    private bool $staff;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function __construct(
        DiscordServer $server,
        string $discordId,
        string $name,
        int $percentage,
        ?string $byeTitle = null,
        bool $staff = false,
    ) {
        $this->server = $server;
        $this->discordId = $discordId;
        $this->name = $name;
        $this->percentage = $percentage;
        $this->byeTitle = $byeTitle;
        $this->staff = $staff;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function server(): DiscordServer
    {
        return $this->server;
    }

    public function discordId(): string
    {
        return $this->discordId;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function percentage(): int
    {
        return $this->percentage;
    }

    public function byeTitle(): ?string
    {
        return $this->byeTitle;
    }

    public function isStaff(): bool
    {
        return $this->staff;
    }

    public function updateConfiguration(string $discordId, string $name, int $percentage, ?string $byeTitle, bool $staff): void
    {
        $this->discordId = $discordId;
        $this->name = $name;
        $this->percentage = $percentage;
        $this->byeTitle = $byeTitle;
        $this->staff = $staff;
        $this->updatedAt = new \DateTimeImmutable();
    }
}
