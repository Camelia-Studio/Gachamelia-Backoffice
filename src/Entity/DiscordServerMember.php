<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'discord_server_members')]
#[ORM\UniqueConstraint(name: 'uniq_discord_server_members_user_server', columns: ['user_id', 'server_id'])]
class DiscordServerMember
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: DiscordUser::class)]
    #[ORM\JoinColumn(name: 'user_id', nullable: false, onDelete: 'CASCADE')]
    private DiscordUser $user;

    #[ORM\ManyToOne(targetEntity: DiscordServer::class)]
    #[ORM\JoinColumn(name: 'server_id', nullable: false, onDelete: 'CASCADE')]
    private DiscordServer $server;

    #[ORM\Column(options: ['default' => false])]
    private bool $owner;

    #[ORM\Column(length: 64, options: ['default' => '0'])]
    private string $permissions;

    #[ORM\Column(name: 'can_manage_configuration', options: ['default' => false])]
    private bool $canManageConfiguration;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function __construct(
        DiscordUser $user,
        DiscordServer $server,
        bool $owner,
        string $permissions,
        bool $canManageConfiguration,
    ) {
        $this->user = $user;
        $this->server = $server;
        $this->owner = $owner;
        $this->permissions = $permissions;
        $this->canManageConfiguration = $canManageConfiguration;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function user(): DiscordUser
    {
        return $this->user;
    }

    public function server(): DiscordServer
    {
        return $this->server;
    }

    public function owner(): bool
    {
        return $this->owner;
    }

    public function permissions(): string
    {
        return $this->permissions;
    }

    public function canManageConfiguration(): bool
    {
        return $this->canManageConfiguration;
    }

    public function refreshAccess(bool $owner, string $permissions, bool $canManageConfiguration): void
    {
        if (
            $this->owner === $owner
            && $this->permissions === $permissions
            && $this->canManageConfiguration === $canManageConfiguration
        ) {
            return;
        }

        $this->owner = $owner;
        $this->permissions = $permissions;
        $this->canManageConfiguration = $canManageConfiguration;
        $this->updatedAt = new \DateTimeImmutable();
    }
}
