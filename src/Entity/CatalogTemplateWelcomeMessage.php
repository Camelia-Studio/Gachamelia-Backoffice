<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'catalog_template_welcome_messages')]
class CatalogTemplateWelcomeMessage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: CatalogTemplate::class)]
    #[ORM\JoinColumn(name: 'template_id', nullable: false, onDelete: 'CASCADE')]
    private CatalogTemplate $template;

    #[ORM\ManyToOne(targetEntity: CatalogTemplateRank::class)]
    #[ORM\JoinColumn(name: 'rank_id', nullable: false, onDelete: 'CASCADE')]
    private CatalogTemplateRank $rank;

    #[ORM\Column(length: 255)]
    private string $message;

    public function __construct(CatalogTemplate $template, CatalogTemplateRank $rank, string $message)
    {
        $this->template = $template;
        $this->rank = $rank;
        $this->message = $message;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function template(): CatalogTemplate
    {
        return $this->template;
    }

    public function rank(): CatalogTemplateRank
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
