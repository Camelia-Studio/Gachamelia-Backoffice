<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'users')]
#[ORM\UniqueConstraint(name: 'uniq_users_server_discord_id', columns: ['server_id', 'discord_id'])]
class GachaUser
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

    #[ORM\ManyToOne(targetEntity: Rank::class)]
    #[ORM\JoinColumn(name: 'rank_id', nullable: true, onDelete: 'SET NULL')]
    private ?Rank $rank = null;

    #[ORM\ManyToOne(targetEntity: CharacterRole::class)]
    #[ORM\JoinColumn(name: 'role_id', nullable: true, onDelete: 'SET NULL')]
    private ?CharacterRole $role = null;

    /**
     * @var Collection<int, Element>
     */
    #[ORM\ManyToMany(targetEntity: Element::class)]
    #[ORM\JoinTable(
        name: 'users_elements',
        joinColumns: [new ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')],
        inverseJoinColumns: [new ORM\JoinColumn(name: 'element_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')],
    )]
    private Collection $elements;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function __construct(DiscordServer $server, string $discordId, ?Rank $rank = null, ?CharacterRole $role = null)
    {
        $this->server = $server;
        $this->discordId = $discordId;
        $this->rank = $rank;
        $this->role = $role;
        $this->elements = new ArrayCollection();
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

    public function addElement(Element $element): void
    {
        if (!$this->elements->contains($element)) {
            $this->elements->add($element);
        }
    }
}
