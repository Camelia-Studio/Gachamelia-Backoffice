<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'catalog_template_stats')]
#[ORM\UniqueConstraint(name: 'uniq_catalog_template_stats_name', columns: ['template_id', 'name'])]
class CatalogTemplateStat
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: CatalogTemplate::class)]
    #[ORM\JoinColumn(name: 'template_id', nullable: false, onDelete: 'CASCADE')]
    private CatalogTemplate $template;

    #[ORM\Column(length: 255)]
    private string $name;

    public function __construct(CatalogTemplate $template, string $name)
    {
        $this->template = $template;
        $this->name = $name;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function template(): CatalogTemplate
    {
        return $this->template;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function updateName(string $name): void
    {
        $this->name = $name;
    }
}
