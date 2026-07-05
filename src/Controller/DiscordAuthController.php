<?php

namespace App\Controller;

use App\Backoffice\BackofficeSession;
use App\Discord\DiscordApiClientInterface;
use App\Discord\DiscordGuildAccessResolver;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DiscordAuthController extends AbstractController
{
    private const STATE_KEY = 'gachamelia.discord_oauth_state';

    public function __construct(
        private readonly string $discordClientId,
        private readonly string $discordRedirectUri,
    ) {
    }

    #[Route('/connexion/discord', name: 'app_discord_login', methods: ['GET'])]
    public function login(Request $request): Response
    {
        $state = bin2hex(random_bytes(32));
        $request->getSession()->set(self::STATE_KEY, $state);

        return new RedirectResponse('https://discord.com/oauth2/authorize?'.http_build_query([
            'client_id' => $this->discordClientId,
            'redirect_uri' => $this->discordRedirectUri,
            'response_type' => 'code',
            'scope' => 'identify guilds',
            'state' => $state,
        ]));
    }

    #[Route('/connexion/discord/retour', name: 'app_discord_callback', methods: ['GET'])]
    public function callback(
        Request $request,
        DiscordApiClientInterface $discordApiClient,
        DiscordGuildAccessResolver $guildAccessResolver,
        BackofficeSession $backofficeSession,
    ): Response {
        $session = $request->getSession();
        $expectedState = $session->get(self::STATE_KEY);
        $session->remove(self::STATE_KEY);

        $state = $request->query->get('state');
        $code = $request->query->get('code');
        if (
            !\is_string($expectedState)
            || !\is_string($state)
            || !hash_equals($expectedState, $state)
            || !\is_string($code)
            || '' === $code
        ) {
            $this->addFlash('error', 'La connexion Discord a expiré.');

            return $this->redirectToRoute('app_home');
        }

        try {
            $accessToken = $discordApiClient->exchangeCodeForAccessToken($code);
            $profile = $discordApiClient->fetchCurrentUser($accessToken);
            $userGuilds = $discordApiClient->fetchCurrentUserGuilds($accessToken);
            $botGuilds = $discordApiClient->fetchBotGuilds();
        } catch (\Throwable) {
            $this->addFlash('error', 'Discord ne répond pas pour le moment.');

            return $this->redirectToRoute('app_home');
        }

        $backofficeSession->login($this->normalizeProfile($profile), $guildAccessResolver->resolveAccessibleGuilds($userGuilds, $botGuilds));

        return $this->redirectToRoute('app_dashboard');
    }

    #[Route('/deconnexion', name: 'app_logout', methods: ['POST'])]
    public function logout(BackofficeSession $backofficeSession): Response
    {
        $backofficeSession->logout();

        return $this->redirectToRoute('app_home');
    }

    /**
     * @param array<string, mixed> $profile
     *
     * @return array{id: string, username: string, global_name: ?string, avatar: ?string}
     */
    private function normalizeProfile(array $profile): array
    {
        $id = $profile['id'] ?? '';
        $username = $profile['username'] ?? 'Utilisateur Discord';
        $globalName = $profile['global_name'] ?? null;
        $avatar = $profile['avatar'] ?? null;

        return [
            'id' => \is_string($id) ? $id : '',
            'username' => \is_string($username) && '' !== $username ? $username : 'Utilisateur Discord',
            'global_name' => \is_string($globalName) && '' !== $globalName ? $globalName : null,
            'avatar' => \is_string($avatar) && '' !== $avatar ? $avatar : null,
        ];
    }
}
