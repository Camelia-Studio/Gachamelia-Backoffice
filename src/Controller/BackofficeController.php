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
    /**
     * @var array<string, string>
     */
    private const array EMOJI_SOURCE_LABELS = [
        'unicode' => 'Emoji standard',
        'bot' => 'Emoji du bot',
        'server' => 'Emoji serveur',
    ];

    /**
     * @var array<string, array{label: string, description: string, catalog_key: string, icon: string}>
     */
    private const array CONFIGURATION_SECTIONS = [
        'ranks' => [
            'label' => 'Rangs',
            'description' => 'Les rangs Discord qui portent les probabilités principales.',
            'catalog_key' => 'ranks',
            'icon' => 'R',
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

        return $this->render('backoffice/server_configuration.html.twig', [
            'guild' => $guild,
            'catalog' => $catalog,
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
     *     roles: list<array{name: string, percentage: int, emoji_source: string, emoji_source_label: string, emoji_unicode: ?string, emoji_id: ?string, emoji_name: ?string, emoji_animated: bool, emoji_markup: string, emoji_cdn_url: ?string}>,
     *     stats: list<array{name: string}>,
     *     elements: list<array{name: string, emoji_source: string, emoji_source_label: string, emoji_unicode: ?string, emoji_id: ?string, emoji_name: ?string, emoji_animated: bool, emoji_markup: string, emoji_cdn_url: ?string}>
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
                static fn (Stat $stat): array => ['name' => $stat->name()],
                $entityManager->getRepository(Stat::class)->findBy(['server' => $server], ['name' => 'ASC']),
            ),
            'elements' => array_map(
                static fn (Element $element): array => [
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
     * @param array{
     *     ranks: list<array{discord_id: string, name: string, percentage: int, bye_title: ?string, is_staff: bool}>,
     *     roles: list<array{name: string, percentage: int, emoji_source: string, emoji_source_label: string, emoji_unicode: ?string, emoji_id: ?string, emoji_name: ?string, emoji_animated: bool, emoji_markup: string, emoji_cdn_url: ?string}>,
     *     stats: list<array{name: string}>,
     *     elements: list<array{name: string, emoji_source: string, emoji_source_label: string, emoji_unicode: ?string, emoji_id: ?string, emoji_name: ?string, emoji_animated: bool, emoji_markup: string, emoji_cdn_url: ?string}>
     * } $catalog
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
                'count' => \count($catalog[$catalogKey]),
            ];
        }

        return $sections;
    }

    /**
     * @param array{
     *     ranks: list<array{discord_id: string, name: string, percentage: int, bye_title: ?string, is_staff: bool}>,
     *     roles: list<array{name: string, percentage: int, emoji_source: string, emoji_source_label: string, emoji_unicode: ?string, emoji_id: ?string, emoji_name: ?string, emoji_animated: bool, emoji_markup: string, emoji_cdn_url: ?string}>,
     *     stats: list<array{name: string}>,
     *     elements: list<array{name: string, emoji_source: string, emoji_source_label: string, emoji_unicode: ?string, emoji_id: ?string, emoji_name: ?string, emoji_animated: bool, emoji_markup: string, emoji_cdn_url: ?string}>
     * } $catalog
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
            'count' => \count($catalog[$catalogKey]),
        ];
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
