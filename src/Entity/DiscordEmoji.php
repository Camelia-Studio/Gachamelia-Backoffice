<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'discord_emojis')]
#[ORM\UniqueConstraint(name: 'uniq_discord_emojis_cache_source_id', columns: ['cache_key', 'source', 'discord_id'])]
#[ORM\Index(name: 'idx_discord_emojis_server_source', columns: ['server_id', 'source'])]
#[ORM\Index(name: 'idx_discord_emojis_cache_available', columns: ['cache_key', 'source', 'available'])]
class DiscordEmoji
{
    public const string SOURCE_SERVER = 'server';
    public const string SOURCE_BOT = 'bot';
    public const string APPLICATION_CACHE_KEY = 'application';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: DiscordServer::class)]
    #[ORM\JoinColumn(name: 'server_id', nullable: true, onDelete: 'CASCADE')]
    private ?DiscordServer $server;

    #[ORM\Column(name: 'cache_key', length: 64)]
    private string $cacheKey;

    #[ORM\Column(length: 16)]
    private string $source;

    #[ORM\Column(name: 'discord_id', length: 32)]
    private string $discordId;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column(options: ['default' => false])]
    private bool $animated;

    #[ORM\Column(options: ['default' => true])]
    private bool $available;

    #[ORM\Column(name: 'last_seen_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $lastSeenAt;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function __construct(
        ?DiscordServer $server,
        string $cacheKey,
        string $source,
        string $discordId,
        string $name,
        bool $animated,
        bool $available = true,
        ?\DateTimeImmutable $seenAt = null,
    ) {
        $now = $seenAt ?? new \DateTimeImmutable();
        $this->server = $server;
        $this->cacheKey = $cacheKey;
        $this->source = $source;
        $this->discordId = $discordId;
        $this->name = $name;
        $this->animated = $animated;
        $this->available = $available;
        $this->lastSeenAt = $now;
        $this->updatedAt = $now;
    }

    public static function serverCacheKey(DiscordServer $server): string
    {
        return 'server:'.$server->discordId();
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function server(): ?DiscordServer
    {
        return $this->server;
    }

    public function cacheKey(): string
    {
        return $this->cacheKey;
    }

    public function source(): string
    {
        return $this->source;
    }

    public function discordId(): string
    {
        return $this->discordId;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function animated(): bool
    {
        return $this->animated;
    }

    public function available(): bool
    {
        return $this->available;
    }

    public function lastSeenAt(): \DateTimeImmutable
    {
        return $this->lastSeenAt;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function refresh(string $name, bool $animated, bool $available, \DateTimeImmutable $seenAt): void
    {
        $this->name = $name;
        $this->animated = $animated;
        $this->available = $available;
        $this->lastSeenAt = $seenAt;
        $this->updatedAt = $seenAt;
    }

    public function markUnavailable(\DateTimeImmutable $updatedAt): void
    {
        if (!$this->available) {
            return;
        }

        $this->available = false;
        $this->updatedAt = $updatedAt;
    }

    public function markup(): string
    {
        return sprintf('<%s:%s:%s>', $this->animated ? 'a' : '', $this->name, $this->discordId);
    }

    public function cdnUrl(): string
    {
        $extension = $this->animated ? 'gif' : 'webp';

        return sprintf('https://cdn.discordapp.com/emojis/%s.%s?size=64&quality=lossless', $this->discordId, $extension);
    }
}
