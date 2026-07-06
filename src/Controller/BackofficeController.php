<?php

namespace App\Controller;

use App\Backoffice\BackofficeAccess;
use App\Backoffice\BackofficeSession;
use App\Entity\CharacterRole;
use App\Entity\DiscordServer;
use App\Entity\Element;
use App\Entity\Rank;
use App\Entity\Stat;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class BackofficeController extends AbstractController
{
    private const string DEFAULT_CONFIGURATION_SECTION = 'ranks';

    /**
     * @var array<string, array{label: string, description: string, catalog_key: string}>
     */
    private const array CONFIGURATION_SECTIONS = [
        'ranks' => [
            'label' => 'Rangs',
            'description' => 'Les rangs Discord qui portent les probabilités principales.',
            'catalog_key' => 'ranks',
        ],
        'roles' => [
            'label' => 'Rôles',
            'description' => 'Les rôles de personnage utilisés dans les tirages.',
            'catalog_key' => 'roles',
        ],
        'stats' => [
            'label' => 'Stats',
            'description' => 'Les caractéristiques disponibles sur les fiches personnage.',
            'catalog_key' => 'stats',
        ],
        'elements' => [
            'label' => 'Éléments',
            'description' => 'Les affinités élémentaires que le bot peut attribuer.',
            'catalog_key' => 'elements',
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
    ): Response {
        if (!$backofficeSession->isAuthenticated()) {
            return $this->redirectToRoute('app_discord_login');
        }

        $guild = $this->findGuildOr404($backofficeSession, $backofficeAccess, $guildId);
        if (true !== ($guild['canManageConfiguration'] ?? false)) {
            throw new AccessDeniedHttpException('Administrator permission required for this server.');
        }

        return $this->redirectToRoute('app_server_configuration_section', [
            'guildId' => $guildId,
            'section' => self::DEFAULT_CONFIGURATION_SECTION,
        ]);
    }

    #[Route('/app/serveurs/{guildId}/configuration/{section}', name: 'app_server_configuration_section', methods: ['GET'])]
    public function configurationSection(
        string $guildId,
        string $section,
        BackofficeSession $backofficeSession,
        BackofficeAccess $backofficeAccess,
        EntityManagerInterface $entityManager,
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

        return $this->render('backoffice/server_configuration.html.twig', [
            'guild' => $guild,
            'catalog' => $catalog,
            'configuration_sections' => $this->configurationSections($catalog),
            'active_section' => $section,
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
            $entityManager->persist(new CharacterRole(
                $server,
                $name,
                $this->requestPercentage($request),
                $this->optionalRequestString($request, 'image_url') ?? 'https://placehold.co/400',
            ));
            $entityManager->flush();
        }

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
            $entityManager->persist(new Element($server, $name));
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_server_configuration_section', ['guildId' => $guildId, 'section' => 'elements']);
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

    private function configurationSectionOr404(string $section): void
    {
        if (!isset(self::CONFIGURATION_SECTIONS[$section])) {
            throw new NotFoundHttpException('Configuration section is not available.');
        }
    }

    /**
     * @return array{
     *     ranks: list<array{discord_id: string, name: string, percentage: int, bye_title: ?string, is_staff: bool}>,
     *     roles: list<array{name: string, percentage: int, image_url: string}>,
     *     stats: list<array{name: string}>,
     *     elements: list<array{name: string}>
     * }
     */
    private function catalogPayload(EntityManagerInterface $entityManager, DiscordServer $server): array
    {
        return [
            'ranks' => array_map(
                static fn (Rank $rank): array => [
                    'discord_id' => $rank->discordId(),
                    'name' => $rank->name(),
                    'percentage' => $rank->percentage(),
                    'bye_title' => $rank->byeTitle(),
                    'is_staff' => $rank->isStaff(),
                ],
                $entityManager->getRepository(Rank::class)->findBy(['server' => $server], ['percentage' => 'ASC', 'name' => 'ASC']),
            ),
            'roles' => array_map(
                static fn (CharacterRole $role): array => [
                    'name' => $role->name(),
                    'percentage' => $role->percentage(),
                    'image_url' => $role->imageUrl(),
                ],
                $entityManager->getRepository(CharacterRole::class)->findBy(['server' => $server], ['percentage' => 'ASC', 'name' => 'ASC']),
            ),
            'stats' => array_map(
                static fn (Stat $stat): array => ['name' => $stat->name()],
                $entityManager->getRepository(Stat::class)->findBy(['server' => $server], ['name' => 'ASC']),
            ),
            'elements' => array_map(
                static fn (Element $element): array => ['name' => $element->name()],
                $entityManager->getRepository(Element::class)->findBy(['server' => $server], ['name' => 'ASC']),
            ),
        ];
    }

    /**
     * @param array{
     *     ranks: list<array{discord_id: string, name: string, percentage: int, bye_title: ?string, is_staff: bool}>,
     *     roles: list<array{name: string, percentage: int, image_url: string}>,
     *     stats: list<array{name: string}>,
     *     elements: list<array{name: string}>
     * } $catalog
     *
     * @return list<array{id: string, label: string, description: string, count: int}>
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
                'count' => \count($catalog[$catalogKey]),
            ];
        }

        return $sections;
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

    private function requestPercentage(Request $request): int
    {
        $percentage = (int) $request->request->get('percentage', 0);

        return max(0, min(100, $percentage));
    }
}
