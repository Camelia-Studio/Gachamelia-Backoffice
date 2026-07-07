<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'discord_users')]
#[ORM\UniqueConstraint(name: 'uniq_discord_users_discord_id', columns: ['discord_id'])]
class DiscordUser
{
    public const string GLOBAL_ROLE_BOT_OWNER = 'ROLE_BOT_OWNER';
    public const string GLOBAL_ROLE_TEMPLATE_ADMIN = 'ROLE_TEMPLATE_ADMIN';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT)]
    private ?int $id = null;

    #[ORM\Column(name: 'discord_id', length: 32)]
    private string $discordId;

    #[ORM\Column(length: 255)]
    private string $username;

    #[ORM\Column(name: 'global_name', length: 255, nullable: true)]
    private ?string $globalName;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $avatar;

    /**
     * @var list<string>
     */
    #[ORM\Column(name: 'global_roles', type: Types::JSON)]
    private array $globalRoles = [];

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function __construct(string $discordId, string $username, ?string $globalName, ?string $avatar)
    {
        $this->discordId = $discordId;
        $this->username = $username;
        $this->globalName = $globalName;
        $this->avatar = $avatar;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function discordId(): string
    {
        return $this->discordId;
    }

    public function username(): string
    {
        return $this->username;
    }

    public function globalName(): ?string
    {
        return $this->globalName;
    }

    public function avatar(): ?string
    {
        return $this->avatar;
    }

    /**
     * @return list<string>
     */
    public function globalRoles(): array
    {
        return $this->globalRoles;
    }

    public function hasGlobalRole(string $role): bool
    {
        return \in_array($role, $this->globalRoles, true);
    }

    /**
     * @param list<string> $roles
     */
    public function replaceGlobalRoles(array $roles): void
    {
        $normalizedRoles = [];
        foreach ($roles as $role) {
            $role = trim($role);
            if ('' === $role) {
                continue;
            }

            $normalizedRoles[$role] = $role;
        }

        $this->globalRoles = array_values($normalizedRoles);
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function refreshProfile(string $username, ?string $globalName, ?string $avatar): void
    {
        if ($this->username === $username && $this->globalName === $globalName && $this->avatar === $avatar) {
            return;
        }

        $this->username = $username;
        $this->globalName = $globalName;
        $this->avatar = $avatar;
        $this->updatedAt = new \DateTimeImmutable();
    }
}
