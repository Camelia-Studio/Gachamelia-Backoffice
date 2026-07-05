<?php

namespace App\Security;

use Symfony\Component\Security\Core\User\UserInterface;

final readonly class BotApiUser implements UserInterface
{
    /**
     * @param list<string> $roles
     */
    public function __construct(
        private string $clientId,
        private array $roles = ['ROLE_BOT'],
    ) {
    }

    public function getUserIdentifier(): string
    {
        return $this->clientId;
    }

    /**
     * @return list<string>
     */
    public function getRoles(): array
    {
        return array_values(array_unique([...$this->roles, 'ROLE_BOT']));
    }

    public function eraseCredentials(): void
    {
    }
}
