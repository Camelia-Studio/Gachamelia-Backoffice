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
#[ORM\UniqueConstraint(name: 'uniq_users_id_server', columns: ['id', 'server_id'])]
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
     * @var Collection<int, UserElement>
     */
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: UserElement::class, cascade: ['persist'], orphanRemoval: true)]
    private Collection $elements;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function __construct(DiscordServer $server, string $discordId, ?Rank $rank = null, ?CharacterRole $role = null)
    {
        $this->server = $server;
        $this->assertCatalogScope($rank, $role);
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

    public function discordId(): string
    {
        return $this->discordId;
    }

    public function rank(): ?Rank
    {
        return $this->rank;
    }

    public function role(): ?CharacterRole
    {
        return $this->role;
    }

    /**
     * @return Collection<int, Element>
     */
    public function elements(): Collection
    {
        return new ArrayCollection(array_map(
            static fn (UserElement $userElement): Element => $userElement->element(),
            $this->elements->toArray(),
        ));
    }

    public function updateRank(?Rank $rank): void
    {
        if (null !== $rank && $rank->server() !== $this->server) {
            throw new \InvalidArgumentException('A user rank must belong to the user server.');
        }

        $this->rank = $rank;
        $this->touch();
    }

    public function updateRole(?CharacterRole $role): void
    {
        if (null !== $role && $role->server() !== $this->server) {
            throw new \InvalidArgumentException('A user role must belong to the user server.');
        }

        $this->role = $role;
        $this->touch();
    }

    public function addElement(Element $element): void
    {
        if ($element->server() !== $this->server) {
            throw new \InvalidArgumentException('A user element must belong to the user server.');
        }

        foreach ($this->elements as $userElement) {
            if ($userElement->element() === $element) {
                return;
            }
        }

        $this->elements->add(new UserElement($this, $element));
        $this->touch();
    }

    /**
     * @param iterable<Element> $elements
     */
    public function replaceElements(iterable $elements): void
    {
        $this->elements->clear();
        foreach ($elements as $element) {
            $this->addElement($element);
        }
        $this->touch();
    }

    private function assertCatalogScope(?Rank $rank, ?CharacterRole $role): void
    {
        if (null !== $rank && $rank->server() !== $this->server) {
            throw new \InvalidArgumentException('A user rank must belong to the user server.');
        }
        if (null !== $role && $role->server() !== $this->server) {
            throw new \InvalidArgumentException('A user role must belong to the user server.');
        }
    }

    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
