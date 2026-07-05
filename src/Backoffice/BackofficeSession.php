<?php

namespace App\Backoffice;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

final readonly class BackofficeSession
{
    private const PROFILE_KEY = 'gachamelia.discord_profile';
    private const GUILDS_KEY = 'gachamelia.discord_guilds';
    private const LOADED_AT_KEY = 'gachamelia.discord_loaded_at';

    public function __construct(
        private RequestStack $requestStack,
    ) {
    }

    /**
     * @param array<string, mixed>        $profile
     * @param list<array<string, mixed>> $guilds
     */
    public function login(array $profile, array $guilds): void
    {
        $session = $this->session();
        $session->set(self::PROFILE_KEY, $profile);
        $session->set(self::GUILDS_KEY, array_values($guilds));
        $session->set(self::LOADED_AT_KEY, time());
    }

    public function logout(): void
    {
        $session = $this->session();
        $session->remove(self::PROFILE_KEY);
        $session->remove(self::GUILDS_KEY);
        $session->remove(self::LOADED_AT_KEY);
    }

    public function isAuthenticated(): bool
    {
        return \is_array($this->session()->get(self::PROFILE_KEY));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function profile(): ?array
    {
        $profile = $this->session()->get(self::PROFILE_KEY);

        return \is_array($profile) ? $profile : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function guilds(): array
    {
        $guilds = $this->session()->get(self::GUILDS_KEY, []);

        return \is_array($guilds) ? array_values(array_filter($guilds, \is_array(...))) : [];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findGuild(string $guildId): ?array
    {
        foreach ($this->guilds() as $guild) {
            if (($guild['id'] ?? null) === $guildId) {
                return $guild;
            }
        }

        return null;
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
