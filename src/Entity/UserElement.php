<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'users_elements')]
class UserElement
{
    #[ORM\ManyToOne(targetEntity: DiscordServer::class)]
    #[ORM\JoinColumn(name: 'server_id', nullable: false, onDelete: 'CASCADE')]
    private DiscordServer $server;

    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: GachaUser::class, inversedBy: 'elements')]
    #[ORM\JoinColumn(name: 'user_id', nullable: false, onDelete: 'CASCADE')]
    private GachaUser $user;

    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: Element::class)]
    #[ORM\JoinColumn(name: 'element_id', nullable: false, onDelete: 'CASCADE')]
    private Element $element;

    public function __construct(GachaUser $user, Element $element)
    {
        if ($user->server() !== $element->server()) {
            throw new \InvalidArgumentException('A user element must belong to one server.');
        }

        $this->server = $user->server();
        $this->user = $user;
        $this->element = $element;
    }

    public function server(): DiscordServer
    {
        return $this->server;
    }

    public function user(): GachaUser
    {
        return $this->user;
    }

    public function element(): Element
    {
        return $this->element;
    }
}
