<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'bye_messages')]
class ByeMessage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: DiscordServer::class)]
    #[ORM\JoinColumn(name: 'server_id', nullable: false, onDelete: 'CASCADE')]
    private DiscordServer $server;

    #[ORM\ManyToOne(targetEntity: Rank::class)]
    #[ORM\JoinColumn(name: 'rank_id', nullable: false, onDelete: 'CASCADE')]
    private Rank $rank;

    #[ORM\Column(length: 255)]
    private string $message;

    public function __construct(DiscordServer $server, Rank $rank, string $message)
    {
        $this->server = $server;
        $this->rank = $rank;
        $this->message = $message;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function server(): DiscordServer
    {
        return $this->server;
    }

    public function rank(): Rank
    {
        return $this->rank;
    }

    public function message(): string
    {
        return $this->message;
    }

    public function updateMessage(string $message): void
    {
        $this->message = $message;
    }
}
