<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'roles')]
#[ORM\UniqueConstraint(name: 'uniq_roles_server_name', columns: ['server_id', 'name'])]
class CharacterRole
{
    public const string DEFAULT_EMOJI = '🎭';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: DiscordServer::class)]
    #[ORM\JoinColumn(name: 'server_id', nullable: false, onDelete: 'CASCADE')]
    private DiscordServer $server;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column]
    private int $percentage;

    #[ORM\Column(name: 'emoji_source', length: 16, options: ['default' => 'unicode'])]
    private string $emojiSource;

    #[ORM\Column(name: 'emoji_unicode', length: 64, nullable: true)]
    private ?string $emojiUnicode;

    #[ORM\Column(name: 'emoji_id', length: 32, nullable: true)]
    private ?string $emojiId;

    #[ORM\Column(name: 'emoji_name', length: 255, nullable: true)]
    private ?string $emojiName;

    #[ORM\Column(name: 'emoji_animated', options: ['default' => false])]
    private bool $emojiAnimated;

    public function __construct(
        DiscordServer $server,
        string $name,
        int $percentage,
        string $emojiSource = 'unicode',
        ?string $emojiUnicode = self::DEFAULT_EMOJI,
        ?string $emojiId = null,
        ?string $emojiName = null,
        bool $emojiAnimated = false,
    ) {
        $this->server = $server;
        $this->name = $name;
        $this->percentage = $percentage;
        $this->emojiSource = $emojiSource;
        $this->emojiUnicode = $emojiUnicode;
        $this->emojiId = $emojiId;
        $this->emojiName = $emojiName;
        $this->emojiAnimated = $emojiAnimated;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function server(): DiscordServer
    {
        return $this->server;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function percentage(): int
    {
        return $this->percentage;
    }

    public function updateConfiguration(
        string $name,
        int $percentage,
        string $emojiSource,
        ?string $emojiUnicode,
        ?string $emojiId,
        ?string $emojiName,
        bool $emojiAnimated,
    ): void {
        $this->name = $name;
        $this->percentage = $percentage;
        $this->emojiSource = $emojiSource;
        $this->emojiUnicode = $emojiUnicode;
        $this->emojiId = $emojiId;
        $this->emojiName = $emojiName;
        $this->emojiAnimated = $emojiAnimated;
    }

    public function emojiSource(): string
    {
        return $this->emojiSource;
    }

    public function emojiUnicode(): ?string
    {
        return $this->emojiUnicode;
    }

    public function emojiId(): ?string
    {
        return $this->emojiId;
    }

    public function emojiName(): ?string
    {
        return $this->emojiName;
    }

    public function emojiAnimated(): bool
    {
        return $this->emojiAnimated;
    }

    public function emojiMarkup(): string
    {
        if (null !== $this->emojiId && null !== $this->emojiName) {
            return sprintf('<%s:%s:%s>', $this->emojiAnimated ? 'a' : '', $this->emojiName, $this->emojiId);
        }

        return $this->emojiUnicode ?? self::DEFAULT_EMOJI;
    }

    public function emojiCdnUrl(): ?string
    {
        if (null === $this->emojiId) {
            return null;
        }

        $extension = $this->emojiAnimated ? 'gif' : 'webp';

        return sprintf('https://cdn.discordapp.com/emojis/%s.%s?size=64&quality=lossless', $this->emojiId, $extension);
    }
}
