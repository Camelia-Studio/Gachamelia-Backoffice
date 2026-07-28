<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'catalog_template_role_stats')]
class CatalogTemplateRoleStat
{
    #[ORM\ManyToOne(targetEntity: CatalogTemplate::class)]
    #[ORM\JoinColumn(name: 'template_id', nullable: false, onDelete: 'CASCADE')]
    private CatalogTemplate $template;

    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: CatalogTemplateRole::class)]
    #[ORM\JoinColumn(name: 'role_id', nullable: false, onDelete: 'CASCADE')]
    private CatalogTemplateRole $role;

    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: CatalogTemplateStat::class)]
    #[ORM\JoinColumn(name: 'stat_id', nullable: false, onDelete: 'CASCADE')]
    private CatalogTemplateStat $stat;

    public function __construct(CatalogTemplateRole $role, CatalogTemplateStat $stat, #[ORM\Column]
        private int $percentage)
    {
        if ($role->template() !== $stat->template()) {
            throw new \InvalidArgumentException('A template role stat must belong to one template.');
        }

        $this->template = $role->template();
        $this->role = $role;
        $this->stat = $stat;
    }

    public function template(): CatalogTemplate
    {
        return $this->template;
    }

    public function role(): CatalogTemplateRole
    {
        return $this->role;
    }

    public function stat(): CatalogTemplateStat
    {
        return $this->stat;
    }

    public function percentage(): int
    {
        return $this->percentage;
    }

    public function updatePercentage(int $percentage): void
    {
        $this->percentage = $percentage;
    }
}
