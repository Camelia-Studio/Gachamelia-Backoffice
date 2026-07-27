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

    #[ORM\ManyToOne(targetEntity: Rank::class)]
    #[ORM\JoinColumn(name: 'rank_id', nullable: true, onDelete: 'SET NULL')]
    private ?Rank $rank = null;

    #[ORM\ManyToOne(targetEntity: CharacterRole::class)]
    #[ORM\JoinColumn(name: 'role_id', nullable: true, onDelete: 'SET NULL')]
    private ?CharacterRole $role = null;

    /**
     * @var Collection<int, UserElement>
     */
    #[ORM\OneToMany(targetEntity: UserElement::class, mappedBy: 'user', cascade: ['persist'], orphanRemoval: true)]
    private Collection $elements;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function __construct(#[ORM\ManyToOne(targetEntity: DiscordServer::class)]
        #[ORM\JoinColumn(name: 'server_id', nullable: false, onDelete: 'CASCADE')]
        private DiscordServer $server, #[ORM\Column(name: 'discord_id', length: 32)]
        private string $discordId, ?Rank $rank = null, ?CharacterRole $role = null)
    {
        $this->assertCatalogScope($rank, $role);
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
        return new ArrayCollection(array_values(array_map(
            static fn (UserElement $userElement): Element => $userElement->element(),
            $this->elements->toArray(),
        )));
    }

    public function updateRank(?Rank $rank): void
    {
        if ($rank instanceof Rank && $rank->server() !== $this->server) {
            throw new \InvalidArgumentException('A user rank must belong to the user server.');
        }

        $this->rank = $rank;
        $this->touch();
    }

    public function updateRole(?CharacterRole $role): void
    {
        if ($role instanceof CharacterRole && $role->server() !== $this->server) {
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
        $desiredElements = [];
        foreach ($elements as $element) {
            if ($element->server() !== $this->server) {
                throw new \InvalidArgumentException('A user element must belong to the user server.');
            }

            $desiredElements[spl_object_id($element)] = $element;
        }

        $changed = false;
        foreach ($this->elements->toArray() as $userElement) {
            if (!isset($desiredElements[spl_object_id($userElement->element())])) {
                $this->elements->removeElement($userElement);
                $changed = true;
                continue;
            }

            unset($desiredElements[spl_object_id($userElement->element())]);
        }

        foreach ($desiredElements as $element) {
            $this->elements->add(new UserElement($this, $element));
            $changed = true;
        }

        if ($changed) {
            $this->touch();
        }
    }

    private function assertCatalogScope(?Rank $rank, ?CharacterRole $role): void
    {
        if ($rank instanceof Rank && $rank->server() !== $this->server) {
            throw new \InvalidArgumentException('A user rank must belong to the user server.');
        }
        if ($role instanceof CharacterRole && $role->server() !== $this->server) {
            throw new \InvalidArgumentException('A user role must belong to the user server.');
        }
    }

    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
