<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'catalog_template_ranks')]
#[ORM\UniqueConstraint(name: 'uniq_catalog_template_ranks_role_key', columns: ['template_id', 'role_key'])]
#[ORM\UniqueConstraint(name: 'uniq_catalog_template_ranks_name', columns: ['template_id', 'name'])]
#[ORM\UniqueConstraint(name: 'uniq_catalog_template_ranks_id_template', columns: ['id', 'template_id'])]
#[ORM\UniqueConstraint(name: 'uniq_catalog_template_ranks_staff_scope', columns: ['staff_scope_id'])]
class CatalogTemplateRank
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: CatalogTemplate::class)]
    #[ORM\JoinColumn(name: 'template_id', nullable: false, onDelete: 'CASCADE')]
    private CatalogTemplate $template;

    #[ORM\Column(name: 'role_key', length: 255)]
    private string $roleKey;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column]
    private int $percentage;

    #[ORM\Column(name: 'bye_title', length: 255, nullable: true)]
    private ?string $byeTitle;

    #[ORM\Column(name: 'is_staff', options: ['default' => false])]
    private bool $staff;

    #[ORM\ManyToOne(targetEntity: CatalogTemplate::class)]
    #[ORM\JoinColumn(name: 'staff_scope_id', nullable: true, onDelete: 'CASCADE')]
    private ?CatalogTemplate $staffScope;

    public function __construct(CatalogTemplate $template, string $roleKey, string $name, int $percentage, ?string $byeTitle = null, bool $staff = false)
    {
        $this->template = $template;
        $this->roleKey = $roleKey;
        $this->name = $name;
        $this->percentage = $percentage;
        $this->byeTitle = $byeTitle;
        $this->staff = $staff;
        $this->staffScope = $staff ? $template : null;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function template(): CatalogTemplate
    {
        return $this->template;
    }

    public function roleKey(): string
    {
        return $this->roleKey;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function percentage(): int
    {
        return $this->percentage;
    }

    public function byeTitle(): ?string
    {
        return $this->byeTitle;
    }

    public function isStaff(): bool
    {
        return $this->staff;
    }

    public function updateConfiguration(string $roleKey, string $name, int $percentage, ?string $byeTitle, bool $staff): void
    {
        $this->roleKey = $roleKey;
        $this->name = $name;
        $this->percentage = $percentage;
        $this->byeTitle = $byeTitle;
        $this->staff = $staff;
        $this->staffScope = $staff ? $this->template : null;
    }
}
