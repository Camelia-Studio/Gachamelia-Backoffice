<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\ProgressionEventType;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Passage de palier d'un joueur : rang (valeurs = rang 1 à 5), quart de ☽ dans un rang
 * (valeurs = quarts 0 à 4) ou constellation (valeurs = nombre de constellations).
 * Le rang associé est celui du palier `tier` (le nouveau rang pour un changement de rang).
 */
#[ORM\Entity]
#[ORM\Table(name: 'progression_events')]
#[ORM\Index(name: 'idx_progression_events_user_occurred', columns: ['user_id', 'occurred_at'])]
class ProgressionEvent
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: DiscordServer::class)]
    #[ORM\JoinColumn(name: 'server_id', nullable: false, onDelete: 'CASCADE')]
    private DiscordServer $server;

    /** Rang du palier concerné ; null si ce rang a été supprimé depuis. */
    #[ORM\ManyToOne(targetEntity: Rank::class)]
    #[ORM\JoinColumn(name: 'rank_id', nullable: true, onDelete: 'SET NULL')]
    private ?Rank $rank;

    /** Nom du rang au moment du passage, conservé pour l'affichage. */
    #[ORM\Column(name: 'rank_name', length: 255)]
    private string $rankName;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: GachaUser::class)]
        #[ORM\JoinColumn(name: 'user_id', nullable: false, onDelete: 'CASCADE')]
        private GachaUser $user,
        #[ORM\Column(length: 16, enumType: ProgressionEventType::class)]
        private ProgressionEventType $type,
        #[ORM\Column(type: Types::SMALLINT)]
        private int $tier,
        Rank $rank,
        #[ORM\Column(name: 'previous_value')]
        private int $previousValue,
        #[ORM\Column(name: 'new_value')]
        private int $newValue,
        #[ORM\Column(name: 'occurred_at', type: Types::DATETIME_IMMUTABLE)]
        private \DateTimeImmutable $occurredAt,
    ) {
        if ($rank->server() !== $user->server()) {
            throw new \InvalidArgumentException('A progression event rank must belong to the user server.');
        }

        $this->server = $user->server();
        $this->rank = $rank;
        $this->rankName = $rank->name();
    }

    public function rank(): ?Rank
    {
        return $this->rank;
    }

    public function rankName(): string
    {
        return $this->rankName;
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

    public function type(): ProgressionEventType
    {
        return $this->type;
    }

    public function tier(): int
    {
        return $this->tier;
    }

    public function previousValue(): int
    {
        return $this->previousValue;
    }

    public function newValue(): int
    {
        return $this->newValue;
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
