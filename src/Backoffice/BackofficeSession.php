<?php

namespace App\Backoffice;

use App\Entity\DiscordUser;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

final readonly class BackofficeSession
{
    private const USER_ID_KEY = 'gachamelia.discord_user_id';
    private const PROFILE_KEY = 'gachamelia.discord_profile';
    private const GUILDS_KEY = 'gachamelia.discord_guilds';
    private const LOADED_AT_KEY = 'gachamelia.discord_loaded_at';

    public function __construct(
        private RequestStack $requestStack,
    ) {
    }

    public function login(DiscordUser $user): void
    {
        $userId = $user->id();
        if (null === $userId) {
            throw new \LogicException('Discord user must be persisted before opening a backoffice session.');
        }

        $session = $this->session();
        $session->set(self::USER_ID_KEY, $userId);
        $session->set(self::LOADED_AT_KEY, time());
    }

    public function logout(): void
    {
        $session = $this->session();
        $session->remove(self::USER_ID_KEY);
        $session->remove(self::PROFILE_KEY);
        $session->remove(self::GUILDS_KEY);
        $session->remove(self::LOADED_AT_KEY);
    }

    public function isAuthenticated(): bool
    {
        return null !== $this->discordUserId();
    }

    public function discordUserId(): ?int
    {
        $userId = $this->session()->get(self::USER_ID_KEY);

        if (\is_int($userId)) {
            return $userId;
        }

        return \is_string($userId) && ctype_digit($userId) ? (int) $userId : null;
    }

    private function session(): SessionInterface
    {
        $session = $this->requestStack->getSession();
        if (!$session->isStarted()) {
            $session->start();
        }

        return $session;
    }
}
