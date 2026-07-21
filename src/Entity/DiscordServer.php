<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'discord_servers')]
#[ORM\UniqueConstraint(name: 'uniq_discord_servers_discord_id', columns: ['discord_id'])]
class DiscordServer
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT)]
    private ?int $id = null;

    #[ORM\Column(name: 'discord_id', length: 32)]
    private string $discordId;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $icon;

    #[ORM\Column(name: 'welcome_channel_id', length: 32, nullable: true)]
    private ?string $welcomeChannelId = null;

    #[ORM\Column(name: 'bye_channel_id', length: 32, nullable: true)]
    private ?string $byeChannelId = null;

    #[ORM\Column(name: 'staff_role_id', length: 32, nullable: true)]
    private ?string $staffRoleId = null;

    #[ORM\Column(options: ['default' => true])]
    private bool $active = true;

    #[ORM\Column(name: 'last_seen_at', type: Types::DATETIME_IMMUTABLE, options: ['default' => 'CURRENT_TIMESTAMP'])]
    private \DateTimeImmutable $lastSeenAt;

    #[ORM\Column(name: 'inactive_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $inactiveAt = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function __construct(string $discordId, string $name, ?string $icon = null)
    {
        $this->discordId = $discordId;
        $this->name = $name;
        $this->icon = $icon;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
        $this->lastSeenAt = $this->createdAt;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function discordId(): string
    {
        return $this->discordId;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function icon(): ?string
    {
        return $this->icon;
    }

    public function welcomeChannelId(): ?string
    {
        return $this->welcomeChannelId;
    }

    public function byeChannelId(): ?string
    {
        return $this->byeChannelId;
    }

    public function staffRoleId(): ?string
    {
        return $this->staffRoleId;
    }

    public function active(): bool
    {
        return $this->active;
    }

    public function lastSeenAt(): \DateTimeImmutable
    {
        return $this->lastSeenAt;
    }

    public function inactiveAt(): ?\DateTimeImmutable
    {
        return $this->inactiveAt;
    }

    public function refreshCache(string $name, ?string $icon): void
    {
        $this->name = $name;
        $this->icon = $icon;
        $this->markSeen();
    }

    public function markSeen(?\DateTimeImmutable $at = null): void
    {
        $at ??= new \DateTimeImmutable();
        $this->active = true;
        $this->lastSeenAt = $at;
        $this->inactiveAt = null;
        $this->updatedAt = $at;
    }

    public function deactivate(?\DateTimeImmutable $at = null): void
    {
        if (!$this->active) {
            return;
        }

        $at ??= new \DateTimeImmutable();
        $this->active = false;
        $this->inactiveAt = $at;
        $this->updatedAt = $at;
    }

    public function updateSettings(?string $welcomeChannelId, ?string $byeChannelId, ?string $staffRoleId): void
    {
        if (
            $this->welcomeChannelId === $welcomeChannelId
            && $this->byeChannelId === $byeChannelId
            && $this->staffRoleId === $staffRoleId
        ) {
            return;
        }

        $this->welcomeChannelId = $welcomeChannelId;
        $this->byeChannelId = $byeChannelId;
        $this->staffRoleId = $staffRoleId;
        $this->updatedAt = new \DateTimeImmutable();
    }
}
