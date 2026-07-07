<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'catalog_template_elements')]
#[ORM\UniqueConstraint(name: 'uniq_catalog_template_elements_name', columns: ['template_id', 'name'])]
class CatalogTemplateElement
{
    public const string DEFAULT_EMOJI = Element::DEFAULT_EMOJI;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: CatalogTemplate::class)]
    #[ORM\JoinColumn(name: 'template_id', nullable: false, onDelete: 'CASCADE')]
    private CatalogTemplate $template;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column(name: 'emoji_source', length: 16, options: ['default' => 'unicode'])]
    private string $emojiSource;

    #[ORM\Column(name: 'emoji_unicode', length: 64, nullable: true)]
    private ?string $emojiUnicode;

    #[ORM\Column(name: 'emoji_id', length: 32, nullable: true)]
    private ?string $emojiId;

    #[ORM\Column(name: 'emoji_name', length: 255, nullable: true)]
    private ?string $emojiName;

    #[ORM\Column(name: 'emoji_animated', options: ['default' => false])]
    private bool $emojiAnimated;

    public function __construct(
        CatalogTemplate $template,
        string $name,
        string $emojiSource = 'unicode',
        ?string $emojiUnicode = self::DEFAULT_EMOJI,
        ?string $emojiId = null,
        ?string $emojiName = null,
        bool $emojiAnimated = false,
    ) {
        $this->template = $template;
        $this->name = $name;
        $this->emojiSource = $emojiSource;
        $this->emojiUnicode = $emojiUnicode;
        $this->emojiId = $emojiId;
        $this->emojiName = $emojiName;
        $this->emojiAnimated = $emojiAnimated;
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

    public function updateConfiguration(string $name, string $emojiSource, ?string $emojiUnicode, ?string $emojiId, ?string $emojiName, bool $emojiAnimated): void
    {
        $this->name = $name;
        $this->emojiSource = $emojiSource;
        $this->emojiUnicode = $emojiUnicode;
        $this->emojiId = $emojiId;
        $this->emojiName = $emojiName;
        $this->emojiAnimated = $emojiAnimated;
    }

    public function emojiSource(): string
    {
        return $this->emojiSource;
    }

    public function emojiUnicode(): ?string
    {
        return $this->emojiUnicode;
    }

    public function emojiId(): ?string
    {
        return $this->emojiId;
    }

    public function emojiName(): ?string
    {
        return $this->emojiName;
    }

    public function emojiAnimated(): bool
    {
        return $this->emojiAnimated;
    }

}
