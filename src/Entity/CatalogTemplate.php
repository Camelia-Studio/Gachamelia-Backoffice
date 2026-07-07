<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'catalog_templates')]
#[ORM\UniqueConstraint(name: 'uniq_catalog_templates_name', columns: ['name'])]
class CatalogTemplate
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT)]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description;

    #[ORM\Column(options: ['default' => false])]
    private bool $published = false;

    #[ORM\ManyToOne(targetEntity: DiscordUser::class)]
    #[ORM\JoinColumn(name: 'created_by_id', nullable: true, onDelete: 'SET NULL')]
    private ?DiscordUser $createdBy;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function __construct(string $name, ?string $description = null, ?DiscordUser $createdBy = null)
    {
        $this->name = $name;
        $this->description = $description;
        $this->createdBy = $createdBy;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function published(): bool
    {
        return $this->published;
    }

    public function update(string $name, ?string $description): void
    {
        $this->name = $name;
        $this->description = $description;
        $this->touch();
    }

    public function publish(): void
    {
        $this->published = true;
        $this->touch();
    }

    public function unpublish(): void
    {
        $this->published = false;
        $this->touch();
    }

    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
