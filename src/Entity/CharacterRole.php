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

    #[ORM\Column(name: 'image_url', length: 255, options: ['default' => 'https://placehold.co/400'])]
    private string $imageUrl;

    public function __construct(DiscordServer $server, string $name, int $percentage, string $imageUrl = 'https://placehold.co/400')
    {
        $this->server = $server;
        $this->name = $name;
        $this->percentage = $percentage;
        $this->imageUrl = $imageUrl;
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

    public function imageUrl(): string
    {
        return $this->imageUrl;
    }
}
