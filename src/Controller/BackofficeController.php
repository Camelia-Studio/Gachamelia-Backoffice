<?php

namespace App\Controller;

use App\Backoffice\BackofficeAccess;
use App\Backoffice\BackofficeSession;
use App\Discord\DiscordGuildResourcesProviderInterface;
use App\Entity\ByeMessage;
use App\Entity\CharacterRole;
use App\Entity\DiscordEmoji;
use App\Entity\DiscordServer;
use App\Entity\Element;
use App\Entity\Rank;
use App\Entity\RankStat;
use App\Entity\Stat;
use App\Entity\WelcomeMessage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class BackofficeController extends AbstractController
{
    /**
     * @var array<string, string>
     */
    private const array EMOJI_SOURCE_LABELS = [
        'unicode' => 'Emoji standard',
        'bot' => 'Emoji du bot',
        'server' => 'Emoji serveur',
    ];

    /**
     * @var list<array{value: string, name: string}>
     */
    private const array STANDARD_EMOJI_OPTIONS = [
        ['value' => '🎭', 'name' => 'Rôle'],
        ['value' => '✨', 'name' => 'Éclat'],
        ['value' => '🔥', 'name' => 'Feu'],
        ['value' => '🌙', 'name' => 'Lune'],
        ['value' => '⭐', 'name' => 'Étoile'],
        ['value' => '💧', 'name' => 'Eau'],
        ['value' => '🌿', 'name' => 'Nature'],
        ['value' => '💎', 'name' => 'Cristal'],
    ];

    /**
     * @var array<string, array{label: string, description: string, catalog_key: string, icon: string}>
     */
    private const array CONFIGURATION_SECTIONS = [
        'settings' => [
            'label' => 'Réglages',
            'description' => 'Les canaux Discord et le rôle staff utilisés par le bot.',
            'catalog_key' => 'settings',
            'icon' => 'RG',
        ],
        'ranks' => [
            'label' => 'Rangs',
            'description' => 'Les rangs Discord qui portent les probabilités principales.',
            'catalog_key' => 'ranks',
            'icon' => 'R',
        ],
        'rank-stats' => [
            'label' => 'Stats de rang',
            'description' => 'Les probabilités de caractéristiques associées à chaque rang.',
            'catalog_key' => 'rank_stats',
            'icon' => 'RS',
        ],
        'welcome-messages' => [
            'label' => 'Arrivées',
            'description' => 'Les messages d’accueil disponibles selon le rang obtenu.',
            'catalog_key' => 'welcome_messages',
            'icon' => 'IN',
        ],
        'bye-messages' => [
            'label' => 'Départs',
            'description' => 'Les messages de départ disponibles selon le rang quitté.',
            'catalog_key' => 'bye_messages',
            'icon' => 'BY',
        ],
        'roles' => [
            'label' => 'Rôles',
            'description' => 'Les rôles de personnage utilisés dans les tirages.',
            'catalog_key' => 'roles',
            'icon' => 'RO',
        ],
        'stats' => [
            'label' => 'Stats',
            'description' => 'Les caractéristiques disponibles sur les fiches personnage.',
            'catalog_key' => 'stats',
            'icon' => 'S',
        ],
        'elements' => [
            'label' => 'Éléments',
            'description' => 'Les affinités élémentaires que le bot peut attribuer.',
            'catalog_key' => 'elements',
            'icon' => 'E',
        ],
    ];

    #[Route('/app', name: 'app_dashboard', methods: ['GET'])]
    public function dashboard(BackofficeSession $backofficeSession, BackofficeAccess $backofficeAccess): Response
    {
        if (!$backofficeSession->isAuthenticated()) {
            return $this->redirectToRoute('app_discord_login');
        }

        $profile = $backofficeAccess->profile($backofficeSession->discordUserId());
        if (null === $profile) {
            $backofficeSession->logout();

            return $this->redirectToRoute('app_discord_login');
        }

        return $this->render('backoffice/dashboard.html.twig', [
            'profile' => $profile,
            'guilds' => $backofficeAccess->guilds($backofficeSession->discordUserId()),
        ]);
    }

    #[Route('/app/serveurs/{guildId}/configuration', name: 'app_server_configuration', methods: ['GET'])]
    public function configuration(
        string $guildId,
        BackofficeSession $backofficeSession,
        BackofficeAccess $backofficeAccess,
        EntityManagerInterface $entityManager,
    ): Response {
        if (!$backofficeSession->isAuthenticated()) {
            return $this->redirectToRoute('app_discord_login');
        }

        $guild = $this->findGuildOr404($backofficeSession, $backofficeAccess, $guildId);
        if (true !== ($guild['canManageConfiguration'] ?? false)) {
            throw new AccessDeniedHttpException('Administrator permission required for this server.');
        }
        $server = $this->findServerEntityOr404($entityManager, $guild['id']);
        $catalog = $this->catalogPayload($entityManager, $server);
        $discordResources = $this->emptyDiscordResourcesPayload();

        return $this->render('backoffice/server_configuration.html.twig', [
            'guild' => $guild,
            'catalog' => $catalog,
            'emoji_picker' => $this->emojiPickerPayload($entityManager, $server),
            'discord_resources' => $discordResources,
            'discord_resource_ids' => $this->discordResourceIdsPayload($discordResources),
            'discord_resource_labels' => $this->discordResourceLabelsPayload($discordResources),
            'discord_settings_labels' => $this->discordSettingsLabelsPayload($catalog['settings'], $discordResources),
            'configuration_sections' => $this->configurationSections($catalog),
            'active_section' => 'overview',
            'active_configuration_section' => null,
        ]);
    }

    #[Route('/app/serveurs/{guildId}/configuration/{section}', name: 'app_server_configuration_section', methods: ['GET'])]
    public function configurationSection(
        string $guildId,
        string $section,
        BackofficeSession $backofficeSession,
        BackofficeAccess $backofficeAccess,
        EntityManagerInterface $entityManager,
        DiscordGuildResourcesProviderInterface $discordGuildResourcesProvider,
    ): Response {
        $this->configurationSectionOr404($section);

        if (!$backofficeSession->isAuthenticated()) {
            return $this->redirectToRoute('app_discord_login');
        }

        $guild = $this->findGuildOr404($backofficeSession, $backofficeAccess, $guildId);
        if (true !== ($guild['canManageConfiguration'] ?? false)) {
            throw new AccessDeniedHttpException('Administrator permission required for this server.');
        }
        $server = $this->findServerEntityOr404($entityManager, $guild['id']);
        $catalog = $this->catalogPayload($entityManager, $server);
        $discordResources = \in_array($section, ['settings', 'ranks'], true)
            ? $discordGuildResourcesProvider->resourcesForGuild($guild['id'])
            : $this->emptyDiscordResourcesPayload();

        return $this->render('backoffice/server_configuration.html.twig', [
            'guild' => $guild,
            'catalog' => $catalog,
            'emoji_picker' => $this->emojiPickerPayload($entityManager, $server),
            'discord_resources' => $discordResources,
            'discord_resource_ids' => $this->discordResourceIdsPayload($discordResources),
            'discord_resource_labels' => $this->discordResourceLabelsPayload($discordResources),
            'discord_settings_labels' => $this->discordSettingsLabelsPayload($catalog['settings'], $discordResources),
            'configuration_sections' => $this->configurationSections($catalog),
            'active_section' => $section,
            'active_configuration_section' => $this->configurationSectionPayload($catalog, $section),
        ]);
    }

    #[Route('/app/serveurs/{guildId}/catalogue/ranks', name: 'app_server_catalog_rank_create', methods: ['POST'])]
    public function createRank(
        string $guildId,
        Request $request,
        BackofficeSession $backofficeSession,
        BackofficeAccess $backofficeAccess,
        EntityManagerInterface $entityManager,
    ): Response {
        $server = $this->manageableServerOr404($guildId, $backofficeSession, $backofficeAccess, $entityManager);

        $discordId = $this->requiredRequestString($request, 'discord_id');
        $name = $this->requiredRequestString($request, 'name');
        if (null !== $discordId && null !== $name) {
            $entityManager->persist(new Rank(
                $server,
                $discordId,
                $name,
                $this->requestPercentage($request),
                $this->optionalRequestString($request, 'bye_title'),
                $request->request->has('is_staff'),
            ));
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_server_configuration_section', ['guildId' => $guildId, 'section' => 'ranks']);
    }

    #[Route('/app/serveurs/{guildId}/catalogue/ranks/{rankId}', name: 'app_server_catalog_rank_update', requirements: ['rankId' => '\d+'], methods: ['POST'])]
    public function updateRank(
        string $guildId,
        string $rankId,
        Request $request,
        BackofficeSession $backofficeSession,
        BackofficeAccess $backofficeAccess,
        EntityManagerInterface $entityManager,
    ): Response {
        $server = $this->manageableServerOr404($guildId, $backofficeSession, $backofficeAccess, $entityManager);
        $rank = $this->rankForServerOr404($entityManager, $server, (int) $rankId);

        $discordId = $this->requiredRequestString($request, 'discord_id');
        $name = $this->requiredRequestString($request, 'name');
        if (null !== $discordId && null !== $name) {
            $rank->updateConfiguration(
                $discordId,
                $name,
                $this->requestPercentage($request),
                $this->optionalRequestString($request, 'bye_title'),
                $request->request->has('is_staff'),
            );
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_server_configuration_section', ['guildId' => $guildId, 'section' => 'ranks']);
    }

    #[Route('/app/serveurs/{guildId}/catalogue/ranks/{rankId}/supprimer', name: 'app_server_catalog_rank_delete', requirements: ['rankId' => '\d+'], methods: ['POST'])]
    public function deleteRank(
        string $guildId,
        string $rankId,
        BackofficeSession $backofficeSession,
        BackofficeAccess $backofficeAccess,
        EntityManagerInterface $entityManager,
    ): Response {
        $server = $this->manageableServerOr404($guildId, $backofficeSession, $backofficeAccess, $entityManager);
        $rank = $this->rankForServerOr404($entityManager, $server, (int) $rankId);

        $entityManager->remove($rank);
        $entityManager->flush();

        return $this->redirectToRoute('app_server_configuration_section', ['guildId' => $guildId, 'section' => 'ranks']);
    }

    #[Route('/app/serveurs/{guildId}/catalogue/ranks/{rankId}/stats', name: 'app_server_catalog_rank_stat_upsert', requirements: ['rankId' => '\d+'], methods: ['POST'])]
    public function upsertRankStat(
        string $guildId,
        string $rankId,
        Request $request,
        BackofficeSession $backofficeSession,
        BackofficeAccess $backofficeAccess,
        EntityManagerInterface $entityManager,
    ): Response {
        $server = $this->manageableServerOr404($guildId, $backofficeSession, $backofficeAccess, $entityManager);
        $rank = $this->rankForServerOr404($entityManager, $server, (int) $rankId);
        $statId = (int) $request->request->get('stat_id', 0);

        if ($statId > 0) {
            $stat = $this->statForServerOr404($entityManager, $server, $statId);
            $rankStat = $entityManager->getRepository(RankStat::class)->findOneBy(['rank' => $rank, 'stat' => $stat]);
            if (!$rankStat instanceof RankStat) {
                $rankStat = new RankStat($rank, $stat, $this->requestPercentage($request));
                $entityManager->persist($rankStat);
            } else {
                $rankStat->updatePercentage($this->requestPercentage($request));
            }

            $entityManager->flush();
        }

        return $this->redirectToRoute('app_server_configuration_section', ['guildId' => $guildId, 'section' => 'ranks']);
    }

    #[Route('/app/serveurs/{guildId}/catalogue/ranks/{rankId}/stats/{statId}/supprimer', name: 'app_server_catalog_rank_stat_delete', requirements: ['rankId' => '\d+', 'statId' => '\d+'], methods: ['POST'])]
    public function deleteRankStat(
        string $guildId,
        string $rankId,
        string $statId,
        BackofficeSession $backofficeSession,
        BackofficeAccess $backofficeAccess,
        EntityManagerInterface $entityManager,
    ): Response {
        $server = $this->manageableServerOr404($guildId, $backofficeSession, $backofficeAccess, $entityManager);
        $rank = $this->rankForServerOr404($entityManager, $server, (int) $rankId);
        $stat = $this->statForServerOr404($entityManager, $server, (int) $statId);
        $rankStat = $entityManager->getRepository(RankStat::class)->findOneBy(['rank' => $rank, 'stat' => $stat]);

        if ($rankStat instanceof RankStat) {
            $entityManager->remove($rankStat);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_server_configuration_section', ['guildId' => $guildId, 'section' => 'ranks']);
    }

    #[Route('/app/serveurs/{guildId}/catalogue/rank-stats', name: 'app_server_catalog_rank_stat_upsert_dedicated', methods: ['POST'])]
    public function upsertRankStatFromCatalogue(
        string $guildId,
        Request $request,
        BackofficeSession $backofficeSession,
        BackofficeAccess $backofficeAccess,
        EntityManagerInterface $entityManager,
    ): Response {
        $server = $this->manageableServerOr404($guildId, $backofficeSession, $backofficeAccess, $entityManager);
        $rankId = (int) $request->request->get('rank_id', 0);
        $statId = (int) $request->request->get('stat_id', 0);

        if ($rankId > 0 && $statId > 0) {
            $rank = $this->rankForServerOr404($entityManager, $server, $rankId);
            $stat = $this->statForServerOr404($entityManager, $server, $statId);
            $rankStat = $entityManager->getRepository(RankStat::class)->findOneBy(['rank' => $rank, 'stat' => $stat]);
            if (!$rankStat instanceof RankStat) {
                $rankStat = new RankStat($rank, $stat, $this->requestPercentage($request));
                $entityManager->persist($rankStat);
            } else {
                $rankStat->updatePercentage($this->requestPercentage($request));
            }

            $entityManager->flush();
        }

        return $this->redirectToRoute('app_server_configuration_section', ['guildId' => $guildId, 'section' => 'rank-stats']);
    }

    #[Route('/app/serveurs/{guildId}/catalogue/rank-stats/{rankId}/{statId}/supprimer', name: 'app_server_catalog_rank_stat_delete_dedicated', requirements: ['rankId' => '\d+', 'statId' => '\d+'], methods: ['POST'])]
    public function deleteRankStatFromCatalogue(
        string $guildId,
        string $rankId,
        string $statId,
        BackofficeSession $backofficeSession,
        BackofficeAccess $backofficeAccess,
        EntityManagerInterface $entityManager,
    ): Response {
        $server = $this->manageableServerOr404($guildId, $backofficeSession, $backofficeAccess, $entityManager);
        $rank = $this->rankForServerOr404($entityManager, $server, (int) $rankId);
        $stat = $this->statForServerOr404($entityManager, $server, (int) $statId);
        $rankStat = $entityManager->getRepository(RankStat::class)->findOneBy(['rank' => $rank, 'stat' => $stat]);

        if ($rankStat instanceof RankStat) {
            $entityManager->remove($rankStat);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_server_configuration_section', ['guildId' => $guildId, 'section' => 'rank-stats']);
    }

    #[Route('/app/serveurs/{guildId}/catalogue/ranks/{rankId}/welcome-messages', name: 'app_server_catalog_rank_welcome_message_create', requirements: ['rankId' => '\d+'], methods: ['POST'])]
    public function createWelcomeMessage(
        string $guildId,
        string $rankId,
        Request $request,
        BackofficeSession $backofficeSession,
        BackofficeAccess $backofficeAccess,
        EntityManagerInterface $entityManager,
    ): Response {
        $server = $this->manageableServerOr404($guildId, $backofficeSession, $backofficeAccess, $entityManager);
        $rank = $this->rankForServerOr404($entityManager, $server, (int) $rankId);
        $message = $this->requiredRequestString($request, 'message');

        if (null !== $message) {
            $entityManager->persist(new WelcomeMessage($server, $rank, $message));
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_server_configuration_section', ['guildId' => $guildId, 'section' => 'ranks']);
    }

    #[Route('/app/serveurs/{guildId}/catalogue/ranks/{rankId}/welcome-messages/{messageId}/supprimer', name: 'app_server_catalog_rank_welcome_message_delete', requirements: ['rankId' => '\d+', 'messageId' => '\d+'], methods: ['POST'])]
    public function deleteWelcomeMessage(
        string $guildId,
        string $rankId,
        string $messageId,
        BackofficeSession $backofficeSession,
        BackofficeAccess $backofficeAccess,
        EntityManagerInterface $entityManager,
    ): Response {
        $server = $this->manageableServerOr404($guildId, $backofficeSession, $backofficeAccess, $entityManager);
        $rank = $this->rankForServerOr404($entityManager, $server, (int) $rankId);
        $message = $this->welcomeMessageForServerOr404($entityManager, $server, $rank, (int) $messageId);

        $entityManager->remove($message);
        $entityManager->flush();

        return $this->redirectToRoute('app_server_configuration_section', ['guildId' => $guildId, 'section' => 'ranks']);
    }

    #[Route('/app/serveurs/{guildId}/catalogue/ranks/{rankId}/bye-messages', name: 'app_server_catalog_rank_bye_message_create', requirements: ['rankId' => '\d+'], methods: ['POST'])]
    public function createByeMessage(
        string $guildId,
        string $rankId,
        Request $request,
        BackofficeSession $backofficeSession,
        BackofficeAccess $backofficeAccess,
        EntityManagerInterface $entityManager,
    ): Response {
        $server = $this->manageableServerOr404($guildId, $backofficeSession, $backofficeAccess, $entityManager);
        $rank = $this->rankForServerOr404($entityManager, $server, (int) $rankId);
        $message = $this->requiredRequestString($request, 'message');

        if (null !== $message) {
            $entityManager->persist(new ByeMessage($server, $rank, $message));
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_server_configuration_section', ['guildId' => $guildId, 'section' => 'ranks']);
    }

    #[Route('/app/serveurs/{guildId}/catalogue/ranks/{rankId}/bye-messages/{messageId}/supprimer', name: 'app_server_catalog_rank_bye_message_delete', requirements: ['rankId' => '\d+', 'messageId' => '\d+'], methods: ['POST'])]
    public function deleteByeMessage(
        string $guildId,
        string $rankId,
        string $messageId,
        BackofficeSession $backofficeSession,
        BackofficeAccess $backofficeAccess,
        EntityManagerInterface $entityManager,
    ): Response {
        $server = $this->manageableServerOr404($guildId, $backofficeSession, $backofficeAccess, $entityManager);
        $rank = $this->rankForServerOr404($entityManager, $server, (int) $rankId);
        $message = $this->byeMessageForServerOr404($entityManager, $server, $rank, (int) $messageId);

        $entityManager->remove($message);
        $entityManager->flush();

        return $this->redirectToRoute('app_server_configuration_section', ['guildId' => $guildId, 'section' => 'ranks']);
    }

    #[Route('/app/serveurs/{guildId}/catalogue/welcome-messages', name: 'app_server_catalog_welcome_message_create', methods: ['POST'])]
    public function createWelcomeMessageFromCatalogue(
        string $guildId,
        Request $request,
        BackofficeSession $backofficeSession,
        BackofficeAccess $backofficeAccess,
        EntityManagerInterface $entityManager,
    ): Response {
        $server = $this->manageableServerOr404($guildId, $backofficeSession, $backofficeAccess, $entityManager);
        $rankId = (int) $request->request->get('rank_id', 0);
        $message = $this->requiredRequestString($request, 'message');

        if ($rankId > 0 && null !== $message) {
            $entityManager->persist(new WelcomeMessage($server, $this->rankForServerOr404($entityManager, $server, $rankId), $message));
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_server_configuration_section', ['guildId' => $guildId, 'section' => 'welcome-messages']);
    }

    #[Route('/app/serveurs/{guildId}/catalogue/welcome-messages/{messageId}', name: 'app_server_catalog_welcome_message_update', requirements: ['messageId' => '\d+'], methods: ['POST'])]
    public function updateWelcomeMessageFromCatalogue(
        string $guildId,
        string $messageId,
        Request $request,
        BackofficeSession $backofficeSession,
        BackofficeAccess $backofficeAccess,
        EntityManagerInterface $entityManager,
    ): Response {
        $server = $this->manageableServerOr404($guildId, $backofficeSession, $backofficeAccess, $entityManager);
        $message = $this->requiredRequestString($request, 'message');

        if (null !== $message) {
            $this->welcomeMessageByServerOr404($entityManager, $server, (int) $messageId)->updateMessage($message);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_server_configuration_section', ['guildId' => $guildId, 'section' => 'welcome-messages']);
    }

    #[Route('/app/serveurs/{guildId}/catalogue/welcome-messages/{messageId}/supprimer', name: 'app_server_catalog_welcome_message_delete', requirements: ['messageId' => '\d+'], methods: ['POST'])]
    public function deleteWelcomeMessageFromCatalogue(
        string $guildId,
        string $messageId,
        BackofficeSession $backofficeSession,
        BackofficeAccess $backofficeAccess,
        EntityManagerInterface $entityManager,
    ): Response {
        $server = $this->manageableServerOr404($guildId, $backofficeSession, $backofficeAccess, $entityManager);
        $entityManager->remove($this->welcomeMessageByServerOr404($entityManager, $server, (int) $messageId));
        $entityManager->flush();

        return $this->redirectToRoute('app_server_configuration_section', ['guildId' => $guildId, 'section' => 'welcome-messages']);
    }

    #[Route('/app/serveurs/{guildId}/catalogue/bye-messages', name: 'app_server_catalog_bye_message_create', methods: ['POST'])]
    public function createByeMessageFromCatalogue(
        string $guildId,
        Request $request,
        BackofficeSession $backofficeSession,
        BackofficeAccess $backofficeAccess,
        EntityManagerInterface $entityManager,
    ): Response {
        $server = $this->manageableServerOr404($guildId, $backofficeSession, $backofficeAccess, $entityManager);
        $rankId = (int) $request->request->get('rank_id', 0);
        $message = $this->requiredRequestString($request, 'message');

        if ($rankId > 0 && null !== $message) {
            $entityManager->persist(new ByeMessage($server, $this->rankForServerOr404($entityManager, $server, $rankId), $message));
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_server_configuration_section', ['guildId' => $guildId, 'section' => 'bye-messages']);
    }

    #[Route('/app/serveurs/{guildId}/catalogue/bye-messages/{messageId}', name: 'app_server_catalog_bye_message_update', requirements: ['messageId' => '\d+'], methods: ['POST'])]
    public function updateByeMessageFromCatalogue(
        string $guildId,
        string $messageId,
        Request $request,
        BackofficeSession $backofficeSession,
        BackofficeAccess $backofficeAccess,
        EntityManagerInterface $entityManager,
    ): Response {
        $server = $this->manageableServerOr404($guildId, $backofficeSession, $backofficeAccess, $entityManager);
        $message = $this->requiredRequestString($request, 'message');

        if (null !== $message) {
            $this->byeMessageByServerOr404($entityManager, $server, (int) $messageId)->updateMessage($message);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_server_configuration_section', ['guildId' => $guildId, 'section' => 'bye-messages']);
    }

    #[Route('/app/serveurs/{guildId}/catalogue/bye-messages/{messageId}/supprimer', name: 'app_server_catalog_bye_message_delete', requirements: ['messageId' => '\d+'], methods: ['POST'])]
    public function deleteByeMessageFromCatalogue(
        string $guildId,
        string $messageId,
        BackofficeSession $backofficeSession,
        BackofficeAccess $backofficeAccess,
        EntityManagerInterface $entityManager,
    ): Response {
        $server = $this->manageableServerOr404($guildId, $backofficeSession, $backofficeAccess, $entityManager);
        $entityManager->remove($this->byeMessageByServerOr404($entityManager, $server, (int) $messageId));
        $entityManager->flush();

        return $this->redirectToRoute('app_server_configuration_section', ['guildId' => $guildId, 'section' => 'bye-messages']);
    }

    #[Route('/app/serveurs/{guildId}/catalogue/roles', name: 'app_server_catalog_role_create', methods: ['POST'])]
    public function createRole(
        string $guildId,
        Request $request,
        BackofficeSession $backofficeSession,
        BackofficeAccess $backofficeAccess,
        EntityManagerInterface $entityManager,
    ): Response {
        $server = $this->manageableServerOr404($guildId, $backofficeSession, $backofficeAccess, $entityManager);

        $name = $this->requiredRequestString($request, 'name');
        if (null !== $name) {
            $emoji = $this->requestEmoji($request, CharacterRole::DEFAULT_EMOJI);
            $entityManager->persist(new CharacterRole(
                $server,
                $name,
                $this->requestPercentage($request),
                $emoji['source'],
                $emoji['unicode'],
                $emoji['id'],
                $emoji['name'],
                $emoji['animated'],
            ));
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_server_configuration_section', ['guildId' => $guildId, 'section' => 'roles']);
    }

    #[Route('/app/serveurs/{guildId}/catalogue/roles/{roleId}', name: 'app_server_catalog_role_update', requirements: ['roleId' => '\d+'], methods: ['POST'])]
    public function updateRole(
        string $guildId,
        string $roleId,
        Request $request,
        BackofficeSession $backofficeSession,
        BackofficeAccess $backofficeAccess,
        EntityManagerInterface $entityManager,
    ): Response {
        $server = $this->manageableServerOr404($guildId, $backofficeSession, $backofficeAccess, $entityManager);
        $role = $this->roleForServerOr404($entityManager, $server, (int) $roleId);

        $name = $this->requiredRequestString($request, 'name');
        if (null !== $name) {
            $emoji = $this->requestEmoji($request, CharacterRole::DEFAULT_EMOJI);
            $role->updateConfiguration(
                $name,
                $this->requestPercentage($request),
                $emoji['source'],
                $emoji['unicode'],
                $emoji['id'],
                $emoji['name'],
                $emoji['animated'],
            );
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_server_configuration_section', ['guildId' => $guildId, 'section' => 'roles']);
    }

    #[Route('/app/serveurs/{guildId}/catalogue/roles/{roleId}/supprimer', name: 'app_server_catalog_role_delete', requirements: ['roleId' => '\d+'], methods: ['POST'])]
    public function deleteRole(
        string $guildId,
        string $roleId,
        BackofficeSession $backofficeSession,
        BackofficeAccess $backofficeAccess,
        EntityManagerInterface $entityManager,
    ): Response {
        $server = $this->manageableServerOr404($guildId, $backofficeSession, $backofficeAccess, $entityManager);
        $role = $this->roleForServerOr404($entityManager, $server, (int) $roleId);

        $entityManager->remove($role);
        $entityManager->flush();

        return $this->redirectToRoute('app_server_configuration_section', ['guildId' => $guildId, 'section' => 'roles']);
    }

    #[Route('/app/serveurs/{guildId}/catalogue/stats', name: 'app_server_catalog_stat_create', methods: ['POST'])]
    public function createStat(
        string $guildId,
        Request $request,
        BackofficeSession $backofficeSession,
        BackofficeAccess $backofficeAccess,
        EntityManagerInterface $entityManager,
    ): Response {
        $server = $this->manageableServerOr404($guildId, $backofficeSession, $backofficeAccess, $entityManager);

        $name = $this->requiredRequestString($request, 'name');
        if (null !== $name) {
            $entityManager->persist(new Stat($server, $name));
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_server_configuration_section', ['guildId' => $guildId, 'section' => 'stats']);
    }

    #[Route('/app/serveurs/{guildId}/catalogue/stats/{statId}', name: 'app_server_catalog_stat_update', requirements: ['statId' => '\d+'], methods: ['POST'])]
    public function updateStat(
        string $guildId,
        string $statId,
        Request $request,
        BackofficeSession $backofficeSession,
        BackofficeAccess $backofficeAccess,
        EntityManagerInterface $entityManager,
    ): Response {
        $server = $this->manageableServerOr404($guildId, $backofficeSession, $backofficeAccess, $entityManager);
        $stat = $this->statForServerOr404($entityManager, $server, (int) $statId);

        $name = $this->requiredRequestString($request, 'name');
        if (null !== $name) {
            $stat->updateName($name);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_server_configuration_section', ['guildId' => $guildId, 'section' => 'stats']);
    }

    #[Route('/app/serveurs/{guildId}/catalogue/stats/{statId}/supprimer', name: 'app_server_catalog_stat_delete', requirements: ['statId' => '\d+'], methods: ['POST'])]
    public function deleteStat(
        string $guildId,
        string $statId,
        BackofficeSession $backofficeSession,
        BackofficeAccess $backofficeAccess,
        EntityManagerInterface $entityManager,
    ): Response {
        $server = $this->manageableServerOr404($guildId, $backofficeSession, $backofficeAccess, $entityManager);
        $stat = $this->statForServerOr404($entityManager, $server, (int) $statId);

        $entityManager->remove($stat);
        $entityManager->flush();

        return $this->redirectToRoute('app_server_configuration_section', ['guildId' => $guildId, 'section' => 'stats']);
    }

    #[Route('/app/serveurs/{guildId}/catalogue/elements', name: 'app_server_catalog_element_create', methods: ['POST'])]
    public function createElement(
        string $guildId,
        Request $request,
        BackofficeSession $backofficeSession,
        BackofficeAccess $backofficeAccess,
        EntityManagerInterface $entityManager,
    ): Response {
        $server = $this->manageableServerOr404($guildId, $backofficeSession, $backofficeAccess, $entityManager);

        $name = $this->requiredRequestString($request, 'name');
        if (null !== $name) {
            $emoji = $this->requestEmoji($request, Element::DEFAULT_EMOJI);
            $entityManager->persist(new Element(
                $server,
                $name,
                $emoji['source'],
                $emoji['unicode'],
                $emoji['id'],
                $emoji['name'],
                $emoji['animated'],
            ));
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_server_configuration_section', ['guildId' => $guildId, 'section' => 'elements']);
    }

    #[Route('/app/serveurs/{guildId}/catalogue/elements/{elementId}', name: 'app_server_catalog_element_update', requirements: ['elementId' => '\d+'], methods: ['POST'])]
    public function updateElement(
        string $guildId,
        string $elementId,
        Request $request,
        BackofficeSession $backofficeSession,
        BackofficeAccess $backofficeAccess,
        EntityManagerInterface $entityManager,
    ): Response {
        $server = $this->manageableServerOr404($guildId, $backofficeSession, $backofficeAccess, $entityManager);
        $element = $this->elementForServerOr404($entityManager, $server, (int) $elementId);

        $name = $this->requiredRequestString($request, 'name');
        if (null !== $name) {
            $emoji = $this->requestEmoji($request, Element::DEFAULT_EMOJI);
            $element->updateConfiguration(
                $name,
                $emoji['source'],
                $emoji['unicode'],
                $emoji['id'],
                $emoji['name'],
                $emoji['animated'],
            );
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_server_configuration_section', ['guildId' => $guildId, 'section' => 'elements']);
    }

    #[Route('/app/serveurs/{guildId}/catalogue/elements/{elementId}/supprimer', name: 'app_server_catalog_element_delete', requirements: ['elementId' => '\d+'], methods: ['POST'])]
    public function deleteElement(
        string $guildId,
        string $elementId,
        BackofficeSession $backofficeSession,
        BackofficeAccess $backofficeAccess,
        EntityManagerInterface $entityManager,
    ): Response {
        $server = $this->manageableServerOr404($guildId, $backofficeSession, $backofficeAccess, $entityManager);
        $element = $this->elementForServerOr404($entityManager, $server, (int) $elementId);

        $entityManager->remove($element);
        $entityManager->flush();

        return $this->redirectToRoute('app_server_configuration_section', ['guildId' => $guildId, 'section' => 'elements']);
    }

    #[Route('/app/serveurs/{guildId}/configuration/settings', name: 'app_server_settings_update', methods: ['POST'])]
    public function updateServerSettings(
        string $guildId,
        Request $request,
        BackofficeSession $backofficeSession,
        BackofficeAccess $backofficeAccess,
        EntityManagerInterface $entityManager,
    ): Response {
        $server = $this->manageableServerOr404($guildId, $backofficeSession, $backofficeAccess, $entityManager);

        $server->updateSettings(
            $this->optionalDiscordReferenceString($request, 'welcome_channel_id'),
            $this->optionalDiscordReferenceString($request, 'bye_channel_id'),
            $this->optionalDiscordReferenceString($request, 'staff_role_id'),
        );
        $entityManager->flush();

        return $this->redirectToRoute('app_server_configuration_section', ['guildId' => $guildId, 'section' => 'settings']);
    }

    #[Route('/app/serveurs/{guildId}/fiche-personnage', name: 'app_character_sheet', methods: ['GET'])]
    public function characterSheet(string $guildId, BackofficeSession $backofficeSession, BackofficeAccess $backofficeAccess): Response
    {
        if (!$backofficeSession->isAuthenticated()) {
            return $this->redirectToRoute('app_discord_login');
        }

        return $this->render('backoffice/character_sheet.html.twig', [
            'guild' => $this->findGuildOr404($backofficeSession, $backofficeAccess, $guildId),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function findGuildOr404(BackofficeSession $backofficeSession, BackofficeAccess $backofficeAccess, string $guildId): array
    {
        $guild = $backofficeAccess->findGuild($backofficeSession->discordUserId(), $guildId);
        if (null === $guild) {
            throw new NotFoundHttpException('Server is not available in this backoffice session.');
        }

        return $guild;
    }

    private function manageableServerOr404(
        string $guildId,
        BackofficeSession $backofficeSession,
        BackofficeAccess $backofficeAccess,
        EntityManagerInterface $entityManager,
    ): DiscordServer {
        if (!$backofficeSession->isAuthenticated()) {
            throw new AccessDeniedHttpException('Backoffice authentication required.');
        }

        $guild = $this->findGuildOr404($backofficeSession, $backofficeAccess, $guildId);
        if (true !== ($guild['canManageConfiguration'] ?? false)) {
            throw new AccessDeniedHttpException('Administrator permission required for this server.');
        }

        return $this->findServerEntityOr404($entityManager, $guild['id']);
    }

    private function findServerEntityOr404(EntityManagerInterface $entityManager, string $guildId): DiscordServer
    {
        $server = $entityManager->getRepository(DiscordServer::class)->findOneBy(['discordId' => $guildId]);
        if (!$server instanceof DiscordServer) {
            throw new NotFoundHttpException('Server is not available in this backoffice session.');
        }

        return $server;
    }

    private function roleForServerOr404(EntityManagerInterface $entityManager, DiscordServer $server, int $roleId): CharacterRole
    {
        $role = $entityManager->getRepository(CharacterRole::class)->findOneBy(['id' => $roleId, 'server' => $server]);
        if (!$role instanceof CharacterRole) {
            throw new NotFoundHttpException('Role is not available on this server.');
        }

        return $role;
    }

    private function rankForServerOr404(EntityManagerInterface $entityManager, DiscordServer $server, int $rankId): Rank
    {
        $rank = $entityManager->getRepository(Rank::class)->findOneBy(['id' => $rankId, 'server' => $server]);
        if (!$rank instanceof Rank) {
            throw new NotFoundHttpException('Rank is not available on this server.');
        }

        return $rank;
    }

    private function statForServerOr404(EntityManagerInterface $entityManager, DiscordServer $server, int $statId): Stat
    {
        $stat = $entityManager->getRepository(Stat::class)->findOneBy(['id' => $statId, 'server' => $server]);
        if (!$stat instanceof Stat) {
            throw new NotFoundHttpException('Stat is not available on this server.');
        }

        return $stat;
    }

    private function elementForServerOr404(EntityManagerInterface $entityManager, DiscordServer $server, int $elementId): Element
    {
        $element = $entityManager->getRepository(Element::class)->findOneBy(['id' => $elementId, 'server' => $server]);
        if (!$element instanceof Element) {
            throw new NotFoundHttpException('Element is not available on this server.');
        }

        return $element;
    }

    private function welcomeMessageForServerOr404(
        EntityManagerInterface $entityManager,
        DiscordServer $server,
        Rank $rank,
        int $messageId,
    ): WelcomeMessage {
        $message = $entityManager->getRepository(WelcomeMessage::class)->findOneBy([
            'id' => $messageId,
            'server' => $server,
            'rank' => $rank,
        ]);
        if (!$message instanceof WelcomeMessage) {
            throw new NotFoundHttpException('Welcome message is not available on this server.');
        }

        return $message;
    }

    private function byeMessageForServerOr404(
        EntityManagerInterface $entityManager,
        DiscordServer $server,
        Rank $rank,
        int $messageId,
    ): ByeMessage {
        $message = $entityManager->getRepository(ByeMessage::class)->findOneBy([
            'id' => $messageId,
            'server' => $server,
            'rank' => $rank,
        ]);
        if (!$message instanceof ByeMessage) {
            throw new NotFoundHttpException('Bye message is not available on this server.');
        }

        return $message;
    }

    private function welcomeMessageByServerOr404(EntityManagerInterface $entityManager, DiscordServer $server, int $messageId): WelcomeMessage
    {
        $message = $entityManager->getRepository(WelcomeMessage::class)->findOneBy(['id' => $messageId, 'server' => $server]);
        if (!$message instanceof WelcomeMessage) {
            throw new NotFoundHttpException('Welcome message is not available on this server.');
        }

        return $message;
    }

    private function byeMessageByServerOr404(EntityManagerInterface $entityManager, DiscordServer $server, int $messageId): ByeMessage
    {
        $message = $entityManager->getRepository(ByeMessage::class)->findOneBy(['id' => $messageId, 'server' => $server]);
        if (!$message instanceof ByeMessage) {
            throw new NotFoundHttpException('Bye message is not available on this server.');
        }

        return $message;
    }

    private function configurationSectionOr404(string $section): void
    {
        if (!isset(self::CONFIGURATION_SECTIONS[$section])) {
            throw new NotFoundHttpException('Configuration section is not available.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function catalogPayload(EntityManagerInterface $entityManager, DiscordServer $server): array
    {
        $ranks = $entityManager->getRepository(Rank::class)->findBy(['server' => $server], ['percentage' => 'ASC', 'name' => 'ASC']);

        return [
            'settings' => $this->serverSettingsPayload($server),
            'ranks' => array_map(
                fn (Rank $rank): array => [
                    'id' => (int) $rank->id(),
                    'discord_id' => $rank->discordId(),
                    'name' => $rank->name(),
                    'percentage' => $rank->percentage(),
                    'bye_title' => $rank->byeTitle(),
                    'is_staff' => $rank->isStaff(),
                    'rank_stats' => $this->rankStatsPayload($entityManager, $rank),
                    'welcome_messages' => $this->welcomeMessagesPayload($entityManager, $server, $rank),
                    'bye_messages' => $this->byeMessagesPayload($entityManager, $server, $rank),
                ],
                $ranks,
            ),
            'rank_stats' => $this->rankStatsListPayload($entityManager, $server),
            'welcome_messages' => $this->welcomeMessagesListPayload($entityManager, $server),
            'bye_messages' => $this->byeMessagesListPayload($entityManager, $server),
            'roles' => array_map(
                static fn (CharacterRole $role): array => [
                    'id' => (int) $role->id(),
                    'name' => $role->name(),
                    'percentage' => $role->percentage(),
                    'emoji_source' => $role->emojiSource(),
                    'emoji_source_label' => self::EMOJI_SOURCE_LABELS[$role->emojiSource()] ?? self::EMOJI_SOURCE_LABELS['unicode'],
                    'emoji_unicode' => $role->emojiUnicode(),
                    'emoji_id' => $role->emojiId(),
                    'emoji_name' => $role->emojiName(),
                    'emoji_animated' => $role->emojiAnimated(),
                    'emoji_markup' => $role->emojiMarkup(),
                    'emoji_cdn_url' => $role->emojiCdnUrl(),
                ],
                $entityManager->getRepository(CharacterRole::class)->findBy(['server' => $server], ['percentage' => 'ASC', 'name' => 'ASC']),
            ),
            'stats' => array_map(
                static fn (Stat $stat): array => [
                    'id' => (int) $stat->id(),
                    'name' => $stat->name(),
                ],
                $entityManager->getRepository(Stat::class)->findBy(['server' => $server], ['name' => 'ASC']),
            ),
            'elements' => array_map(
                static fn (Element $element): array => [
                    'id' => (int) $element->id(),
                    'name' => $element->name(),
                    'emoji_source' => $element->emojiSource(),
                    'emoji_source_label' => self::EMOJI_SOURCE_LABELS[$element->emojiSource()] ?? self::EMOJI_SOURCE_LABELS['unicode'],
                    'emoji_unicode' => $element->emojiUnicode(),
                    'emoji_id' => $element->emojiId(),
                    'emoji_name' => $element->emojiName(),
                    'emoji_animated' => $element->emojiAnimated(),
                    'emoji_markup' => $element->emojiMarkup(),
                    'emoji_cdn_url' => $element->emojiCdnUrl(),
                ],
                $entityManager->getRepository(Element::class)->findBy(['server' => $server], ['name' => 'ASC']),
            ),
        ];
    }

    /**
     * @return array{welcome_channel_id: ?string, bye_channel_id: ?string, staff_role_id: ?string}
     */
    private function serverSettingsPayload(DiscordServer $server): array
    {
        return [
            'welcome_channel_id' => $server->welcomeChannelId(),
            'bye_channel_id' => $server->byeChannelId(),
            'staff_role_id' => $server->staffRoleId(),
        ];
    }

    /**
     * @return array{channels: array{}, roles: array{}}
     */
    private function emptyDiscordResourcesPayload(): array
    {
        return [
            'channels' => [],
            'roles' => [],
        ];
    }

    /**
     * @param array{
     *     channels: list<array{id: string, name: string, label: string, type: int}>,
     *     roles: list<array{id: string, name: string, label: string, position: int, managed: bool}>
     * } $discordResources
     *
     * @return array{channels: list<string>, roles: list<string>}
     */
    private function discordResourceIdsPayload(array $discordResources): array
    {
        return [
            'channels' => array_map(static fn (array $channel): string => $channel['id'], $discordResources['channels']),
            'roles' => array_map(static fn (array $role): string => $role['id'], $discordResources['roles']),
        ];
    }

    /**
     * @param array{
     *     channels: list<array{id: string, name: string, label: string, type: int}>,
     *     roles: list<array{id: string, name: string, label: string, position: int, managed: bool}>
     * } $discordResources
     *
     * @return array{channels: array<string, string>, roles: array<string, string>}
     */
    private function discordResourceLabelsPayload(array $discordResources): array
    {
        return [
            'channels' => $this->resourceLabelsById($discordResources['channels']),
            'roles' => $this->resourceLabelsById($discordResources['roles']),
        ];
    }

    /**
     * @param array{welcome_channel_id: ?string, bye_channel_id: ?string, staff_role_id: ?string} $settings
     * @param array{
     *     channels: list<array{id: string, name: string, label: string, type: int}>,
     *     roles: list<array{id: string, name: string, label: string, position: int, managed: bool}>
     * } $discordResources
     *
     * @return array{welcome_channel_id: ?string, bye_channel_id: ?string, staff_role_id: ?string}
     */
    private function discordSettingsLabelsPayload(array $settings, array $discordResources): array
    {
        $channelLabels = $this->resourceLabelsById($discordResources['channels']);
        $roleLabels = $this->resourceLabelsById($discordResources['roles']);

        return [
            'welcome_channel_id' => $this->discordSettingLabel($settings['welcome_channel_id'], $channelLabels),
            'bye_channel_id' => $this->discordSettingLabel($settings['bye_channel_id'], $channelLabels),
            'staff_role_id' => $this->discordSettingLabel($settings['staff_role_id'], $roleLabels),
        ];
    }

    /**
     * @param list<array{id: string, label: string}> $resources
     *
     * @return array<string, string>
     */
    private function resourceLabelsById(array $resources): array
    {
        $labels = [];
        foreach ($resources as $resource) {
            $labels[$resource['id']] = $resource['label'];
        }

        return $labels;
    }

    /**
     * @param array<string, string> $labels
     */
    private function discordSettingLabel(?string $value, array $labels): ?string
    {
        if (null === $value) {
            return null;
        }

        return $labels[$value] ?? $value;
    }

    /**
     * @return list<array{rank_id: int, rank_name: string, stat_id: int, stat_name: string, percentage: int}>
     */
    private function rankStatsListPayload(EntityManagerInterface $entityManager, DiscordServer $server): array
    {
        /** @var list<RankStat> $rankStats */
        $rankStats = $entityManager->createQueryBuilder()
            ->select('rankStat')
            ->from(RankStat::class, 'rankStat')
            ->innerJoin('rankStat.rank', 'rankEntity')
            ->innerJoin('rankStat.stat', 'statEntity')
            ->andWhere('rankEntity.server = :server')
            ->andWhere('statEntity.server = :server')
            ->setParameter('server', $server)
            ->orderBy('rankEntity.percentage', 'ASC')
            ->addOrderBy('rankEntity.name', 'ASC')
            ->addOrderBy('rankStat.percentage', 'ASC')
            ->getQuery()
            ->getResult();

        return array_map(
            static fn (RankStat $rankStat): array => [
                'rank_id' => (int) $rankStat->rank()->id(),
                'rank_name' => $rankStat->rank()->name(),
                'stat_id' => (int) $rankStat->stat()->id(),
                'stat_name' => $rankStat->stat()->name(),
                'percentage' => $rankStat->percentage(),
            ],
            $rankStats,
        );
    }

    /**
     * @return list<array{id: int, rank_id: int, rank_name: string, message: string}>
     */
    private function welcomeMessagesListPayload(EntityManagerInterface $entityManager, DiscordServer $server): array
    {
        /** @var list<WelcomeMessage> $messages */
        $messages = $entityManager->createQueryBuilder()
            ->select('welcomeMessage')
            ->from(WelcomeMessage::class, 'welcomeMessage')
            ->innerJoin('welcomeMessage.rank', 'rankEntity')
            ->andWhere('welcomeMessage.server = :server')
            ->andWhere('rankEntity.server = :server')
            ->setParameter('server', $server)
            ->orderBy('rankEntity.percentage', 'ASC')
            ->addOrderBy('rankEntity.name', 'ASC')
            ->addOrderBy('welcomeMessage.id', 'ASC')
            ->getQuery()
            ->getResult();

        return array_map(
            static fn (WelcomeMessage $message): array => [
                'id' => (int) $message->id(),
                'rank_id' => (int) $message->rank()->id(),
                'rank_name' => $message->rank()->name(),
                'message' => $message->message(),
            ],
            $messages,
        );
    }

    /**
     * @return list<array{id: int, rank_id: int, rank_name: string, message: string}>
     */
    private function byeMessagesListPayload(EntityManagerInterface $entityManager, DiscordServer $server): array
    {
        /** @var list<ByeMessage> $messages */
        $messages = $entityManager->createQueryBuilder()
            ->select('byeMessage')
            ->from(ByeMessage::class, 'byeMessage')
            ->innerJoin('byeMessage.rank', 'rankEntity')
            ->andWhere('byeMessage.server = :server')
            ->andWhere('rankEntity.server = :server')
            ->setParameter('server', $server)
            ->orderBy('rankEntity.percentage', 'ASC')
            ->addOrderBy('rankEntity.name', 'ASC')
            ->addOrderBy('byeMessage.id', 'ASC')
            ->getQuery()
            ->getResult();

        return array_map(
            static fn (ByeMessage $message): array => [
                'id' => (int) $message->id(),
                'rank_id' => (int) $message->rank()->id(),
                'rank_name' => $message->rank()->name(),
                'message' => $message->message(),
            ],
            $messages,
        );
    }

    /**
     * @return list<array{stat_id: int, stat_name: string, percentage: int}>
     */
    private function rankStatsPayload(EntityManagerInterface $entityManager, Rank $rank): array
    {
        return array_map(
            static fn (RankStat $rankStat): array => [
                'stat_id' => (int) $rankStat->stat()->id(),
                'stat_name' => $rankStat->stat()->name(),
                'percentage' => $rankStat->percentage(),
            ],
            $entityManager->getRepository(RankStat::class)->findBy(['rank' => $rank], ['percentage' => 'ASC']),
        );
    }

    /**
     * @return list<array{id: int, message: string}>
     */
    private function welcomeMessagesPayload(EntityManagerInterface $entityManager, DiscordServer $server, Rank $rank): array
    {
        return array_map(
            static fn (WelcomeMessage $message): array => [
                'id' => (int) $message->id(),
                'message' => $message->message(),
            ],
            $entityManager->getRepository(WelcomeMessage::class)->findBy(['server' => $server, 'rank' => $rank], ['id' => 'ASC']),
        );
    }

    /**
     * @return list<array{id: int, message: string}>
     */
    private function byeMessagesPayload(EntityManagerInterface $entityManager, DiscordServer $server, Rank $rank): array
    {
        return array_map(
            static fn (ByeMessage $message): array => [
                'id' => (int) $message->id(),
                'message' => $message->message(),
            ],
            $entityManager->getRepository(ByeMessage::class)->findBy(['server' => $server, 'rank' => $rank], ['id' => 'ASC']),
        );
    }

    /**
     * @param array<string, mixed> $catalog
     *
     * @return list<array{id: string, label: string, description: string, icon: string, count: int}>
     */
    private function configurationSections(array $catalog): array
    {
        $sections = [];

        foreach (self::CONFIGURATION_SECTIONS as $id => $section) {
            $catalogKey = $section['catalog_key'];
            $sections[] = [
                'id' => $id,
                'label' => $section['label'],
                'description' => $section['description'],
                'icon' => $section['icon'],
                'count' => $this->configurationSectionCount($catalog, $catalogKey),
            ];
        }

        return $sections;
    }

    /**
     * @param array<string, mixed> $catalog
     *
     * @return array{id: string, label: string, description: string, icon: string, count: int}
     */
    private function configurationSectionPayload(array $catalog, string $id): array
    {
        $section = self::CONFIGURATION_SECTIONS[$id];
        $catalogKey = $section['catalog_key'];

        return [
            'id' => $id,
            'label' => $section['label'],
            'description' => $section['description'],
            'icon' => $section['icon'],
            'count' => $this->configurationSectionCount($catalog, $catalogKey),
        ];
    }

    /**
     * @param array<string, mixed> $catalog
     */
    private function configurationSectionCount(array $catalog, string $catalogKey): int
    {
        if ('settings' === $catalogKey) {
            return \count(array_filter(
                $catalog['settings'],
                static fn (?string $value): bool => null !== $value,
            ));
        }

        return \count($catalog[$catalogKey]);
    }

    /**
     * @return array{
     *     standard: list<array{source: string, value: string, name: string, markup: string, cdn_url: ?string, discord_id: ?string}>,
     *     bot: list<array{source: string, value: string, name: string, markup: string, cdn_url: ?string, discord_id: ?string}>,
     *     server: list<array{source: string, value: string, name: string, markup: string, cdn_url: ?string, discord_id: ?string}>
     * }
     */
    private function emojiPickerPayload(EntityManagerInterface $entityManager, DiscordServer $server): array
    {
        return [
            'standard' => array_map(
                static fn (array $emoji): array => [
                    'source' => 'unicode',
                    'value' => $emoji['value'],
                    'name' => $emoji['name'],
                    'markup' => $emoji['value'],
                    'cdn_url' => null,
                    'discord_id' => null,
                ],
                self::STANDARD_EMOJI_OPTIONS,
            ),
            'bot' => $this->discordEmojiOptions($entityManager, DiscordEmoji::APPLICATION_CACHE_KEY, DiscordEmoji::SOURCE_BOT),
            'server' => $this->discordEmojiOptions($entityManager, DiscordEmoji::serverCacheKey($server), DiscordEmoji::SOURCE_SERVER),
        ];
    }

    /**
     * @return list<array{source: string, value: string, name: string, markup: string, cdn_url: ?string, discord_id: ?string}>
     */
    private function discordEmojiOptions(EntityManagerInterface $entityManager, string $cacheKey, string $source): array
    {
        return array_map(
            static fn (DiscordEmoji $emoji): array => [
                'source' => $emoji->source(),
                'value' => $emoji->markup(),
                'name' => $emoji->name(),
                'markup' => $emoji->markup(),
                'cdn_url' => $emoji->cdnUrl(),
                'discord_id' => $emoji->discordId(),
            ],
            $entityManager->getRepository(DiscordEmoji::class)->findBy(
                ['cacheKey' => $cacheKey, 'source' => $source, 'available' => true],
                ['name' => 'ASC'],
            ),
        );
    }

    private function requiredRequestString(Request $request, string $key): ?string
    {
        $value = $request->request->get($key);
        if (!\is_string($value)) {
            return null;
        }

        $value = trim($value);

        return '' !== $value ? $value : null;
    }

    private function optionalRequestString(Request $request, string $key): ?string
    {
        $value = $request->request->get($key);
        if (!\is_string($value)) {
            return null;
        }

        $value = trim($value);

        return '' !== $value ? $value : null;
    }

    private function optionalDiscordReferenceString(Request $request, string $key): ?string
    {
        $value = $this->optionalRequestString($request, $key);
        if (null === $value) {
            return null;
        }

        if (1 === preg_match('/^<#(?P<id>\d{17,22})>$/', $value, $matches)) {
            return $matches['id'];
        }

        if (1 === preg_match('/^<@&(?P<id>\d{17,22})>$/', $value, $matches)) {
            return $matches['id'];
        }

        return $value;
    }

    private function requestPercentage(Request $request): int
    {
        $percentage = (int) $request->request->get('percentage', 0);

        return max(0, min(100, $percentage));
    }

    /**
     * @return array{source: string, unicode: ?string, id: ?string, name: ?string, animated: bool}
     */
    private function requestEmoji(Request $request, string $defaultUnicode): array
    {
        $source = $this->optionalRequestString($request, 'emoji_source') ?? 'unicode';
        if (!isset(self::EMOJI_SOURCE_LABELS[$source])) {
            $source = 'unicode';
        }

        $value = $this->optionalRequestString($request, 'emoji_value') ?? $defaultUnicode;
        if (1 === preg_match('/^<(?P<animated>a?):(?P<name>[A-Za-z0-9_]{2,32}):(?P<id>\d{17,22})>$/', $value, $matches)) {
            return [
                'source' => 'unicode' === $source ? 'server' : $source,
                'unicode' => null,
                'id' => $matches['id'],
                'name' => $matches['name'],
                'animated' => 'a' === $matches['animated'],
            ];
        }

        return [
            'source' => $source,
            'unicode' => $value,
            'id' => null,
            'name' => null,
            'animated' => false,
        ];
    }
}
