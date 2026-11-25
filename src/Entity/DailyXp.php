<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Cumul journalier de l'XP gagnée par un joueur, séparé entre messages et vocal.
 */
#[ORM\Entity]
#[ORM\Table(name: 'user_daily_xp')]
#[ORM\UniqueConstraint(name: 'uniq_user_daily_xp_user_day', columns: ['user_id', 'day'])]
class DailyXp
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: DiscordServer::class)]
    #[ORM\JoinColumn(name: 'server_id', nullable: false, onDelete: 'CASCADE')]
    private DiscordServer $server;

    #[ORM\Column(name: 'message_xp', type: Types::BIGINT, options: ['default' => 0])]
    private int $messageXp = 0;

    #[ORM\Column(name: 'voice_xp', type: Types::BIGINT, options: ['default' => 0])]
    private int $voiceXp = 0;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: GachaUser::class)]
        #[ORM\JoinColumn(name: 'user_id', nullable: false, onDelete: 'CASCADE')]
        private GachaUser $user,
        #[ORM\Column(type: Types::DATE_IMMUTABLE)]
        private \DateTimeImmutable $day,
    ) {
        $this->server = $user->server();
        $this->day = $day->setTime(0, 0);
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function server(): DiscordServer
    {
        return $this->server;
    }

    public function user(): GachaUser
    {
        return $this->user;
    }

    public function day(): \DateTimeImmutable
    {
        return $this->day;
    }

    public function messageXp(): int
    {
        return $this->messageXp;
    }

    public function voiceXp(): int
    {
        return $this->voiceXp;
    }

    public function add(string $source, int $amount): void
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Invalid XP gain.');
        }

        match ($source) {
            'message' => $this->messageXp += $amount,
            'voice' => $this->voiceXp += $amount,
            default => throw new \InvalidArgumentException('invalid_xp_source'),
        };
    }
}
