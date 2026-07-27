<?php

declare(strict_types=1);

namespace App\Controller;

use App\Backoffice\BackofficeAccess;
use App\Backoffice\BackofficeSession;
use App\Backoffice\Csv\CatalogCsvDocument;
use App\Backoffice\Csv\CatalogCsvDraftStore;
use App\Backoffice\Csv\CatalogCsvImportService;
use App\Backoffice\Csv\CatalogCsvParser;
use App\Backoffice\Csv\CatalogCsvPreview;
use App\Backoffice\Csv\CatalogCsvSampleGenerator;
use App\Backoffice\Csv\CatalogCsvSection;
use App\Discord\DiscordGuildResourcesProviderInterface;
use App\Entity\CatalogTemplate;
use App\Entity\DiscordServer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;

#[IsCsrfTokenValid('backoffice', tokenKey: '_token', methods: ['POST'])]
final class CatalogCsvImportController extends AbstractController
{
    private const string SECTION_REQUIREMENT = 'ranks|rank-stats|welcome-messages|bye-messages|roles|stats|elements';

    #[Route(
        '/app/serveurs/{guildId}/configuration/{section}/csv',
        name: 'app_server_catalog_csv_import',
        requirements: ['section' => self::SECTION_REQUIREMENT],
        methods: ['GET'],
    )]
    public function serverImport(
        string $guildId,
        string $section,
        BackofficeSession $session,
        BackofficeAccess $access,
        EntityManagerInterface $entityManager,
    ): Response {
        if (!$session->isAuthenticated()) {
            return $this->redirectToRoute('app_discord_login');
        }
        $target = $this->serverTarget($guildId, $session, $access, $entityManager, true);

        return $this->renderImport('server', $target, $this->section($section));
    }

    #[Route(
        '/app/serveurs/{guildId}/configuration/{section}/csv/exemple',
        name: 'app_server_catalog_csv_example',
        requirements: ['section' => self::SECTION_REQUIREMENT],
        methods: ['GET'],
    )]
    public function serverExample(
        string $guildId,
        string $section,
        BackofficeSession $session,
        BackofficeAccess $access,
        EntityManagerInterface $entityManager,
        CatalogCsvSampleGenerator $generator,
    ): Response {
        if (!$session->isAuthenticated()) {
            return $this->redirectToRoute('app_discord_login');
        }
        $this->serverTarget($guildId, $session, $access, $entityManager, false);

        return $this->example($this->section($section), $generator);
    }

    #[Route(
        '/app/serveurs/{guildId}/configuration/{section}/csv/apercu',
        name: 'app_server_catalog_csv_preview',
        requirements: ['section' => self::SECTION_REQUIREMENT],
        methods: ['POST'],
    )]
    public function serverPreview(
        string $guildId,
        string $section,
        Request $request,
        BackofficeSession $session,
        BackofficeAccess $access,
        EntityManagerInterface $entityManager,
        CatalogCsvParser $parser,
        CatalogCsvImportService $importService,
        CatalogCsvDraftStore $draftStore,
        DiscordGuildResourcesProviderInterface $resourcesProvider,
    ): Response {
        $target = $this->serverTarget($guildId, $session, $access, $entityManager, true);

        return $this->preview(
            'server',
            $target,
            $this->section($section),
            $request,
            $session,
            $parser,
            $importService,
            $draftStore,
            $resourcesProvider,
        );
    }

    #[Route(
        '/app/serveurs/{guildId}/configuration/{section}/csv/{token}/appliquer',
        name: 'app_server_catalog_csv_apply',
        requirements: ['section' => self::SECTION_REQUIREMENT, 'token' => '[a-f0-9]{64}'],
        methods: ['POST'],
    )]
    public function serverApply(
        string $guildId,
        string $section,
        string $token,
        Request $request,
        BackofficeSession $session,
        BackofficeAccess $access,
        EntityManagerInterface $entityManager,
        CatalogCsvImportService $importService,
        CatalogCsvDraftStore $draftStore,
        DiscordGuildResourcesProviderInterface $resourcesProvider,
    ): Response {
        $target = $this->serverTarget($guildId, $session, $access, $entityManager, true);

        return $this->apply(
            'server',
            $target,
            $this->section($section),
            $token,
            $request,
            $session,
            $importService,
            $draftStore,
            $resourcesProvider,
        );
    }

    #[Route(
        '/app/modeles-catalogue/{templateId}/configuration/{section}/csv',
        name: 'app_catalog_template_csv_import',
        requirements: ['templateId' => '\d+', 'section' => self::SECTION_REQUIREMENT],
        methods: ['GET'],
    )]
    public function templateImport(
        string $templateId,
        string $section,
        BackofficeSession $session,
        BackofficeAccess $access,
        EntityManagerInterface $entityManager,
    ): Response {
        $target = $this->templateTarget($templateId, $session, $access, $entityManager);

        return $this->renderImport('template', $target, $this->section($section));
    }

    #[Route(
        '/app/modeles-catalogue/{templateId}/configuration/{section}/csv/exemple',
        name: 'app_catalog_template_csv_example',
        requirements: ['templateId' => '\d+', 'section' => self::SECTION_REQUIREMENT],
        methods: ['GET'],
    )]
    public function templateExample(
        string $templateId,
        string $section,
        BackofficeSession $session,
        BackofficeAccess $access,
        EntityManagerInterface $entityManager,
        CatalogCsvSampleGenerator $generator,
    ): Response {
        $this->templateTarget($templateId, $session, $access, $entityManager);

        return $this->example($this->section($section), $generator);
    }

    #[Route(
        '/app/modeles-catalogue/{templateId}/configuration/{section}/csv/apercu',
        name: 'app_catalog_template_csv_preview',
        requirements: ['templateId' => '\d+', 'section' => self::SECTION_REQUIREMENT],
        methods: ['POST'],
    )]
    public function templatePreview(
        string $templateId,
        string $section,
        Request $request,
        BackofficeSession $session,
        BackofficeAccess $access,
        EntityManagerInterface $entityManager,
        CatalogCsvParser $parser,
        CatalogCsvImportService $importService,
        CatalogCsvDraftStore $draftStore,
        DiscordGuildResourcesProviderInterface $resourcesProvider,
    ): Response {
        $target = $this->templateTarget($templateId, $session, $access, $entityManager);

        return $this->preview(
            'template',
            $target,
            $this->section($section),
            $request,
            $session,
            $parser,
            $importService,
            $draftStore,
            $resourcesProvider,
        );
    }

    #[Route(
        '/app/modeles-catalogue/{templateId}/configuration/{section}/csv/{token}/appliquer',
        name: 'app_catalog_template_csv_apply',
        requirements: ['templateId' => '\d+', 'section' => self::SECTION_REQUIREMENT, 'token' => '[a-f0-9]{64}'],
        methods: ['POST'],
    )]
    public function templateApply(
        string $templateId,
        string $section,
        string $token,
        Request $request,
        BackofficeSession $session,
        BackofficeAccess $access,
        EntityManagerInterface $entityManager,
        CatalogCsvImportService $importService,
        CatalogCsvDraftStore $draftStore,
        DiscordGuildResourcesProviderInterface $resourcesProvider,
    ): Response {
        $target = $this->templateTarget($templateId, $session, $access, $entityManager);

        return $this->apply(
            'template',
            $target,
            $this->section($section),
            $token,
            $request,
            $session,
            $importService,
            $draftStore,
            $resourcesProvider,
        );
    }

    private function example(CatalogCsvSection $section, CatalogCsvSampleGenerator $generator): Response
    {
        $response = new Response($generator->generate($section));
        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', 'attachment; filename='.$section->exampleFilename());

        return $response;
    }

    /**
     * @param list<array{line: ?int, column: ?string, message: string, value: ?string}>          $errors
     * @param list<array{id: string, name: string, label: string, position: int, managed: bool}> $discordRoles
     */
    private function renderImport(
        string $targetType,
        DiscordServer|CatalogTemplate $target,
        CatalogCsvSection $section,
        ?CatalogCsvPreview $preview = null,
        ?string $token = null,
        array $errors = [],
        array $discordRoles = [],
        int $status = Response::HTTP_OK,
    ): Response {
        return $this->render('backoffice/catalog_csv_import.html.twig', [
            'target_type' => $targetType,
            'target_id' => $this->targetId($target),
            'target_name' => $target->name(),
            'section' => $section,
            'preview' => $preview,
            'token' => $token,
            'errors' => $errors,
            'discord_roles' => $discordRoles,
            'routes' => $this->routes($targetType, $target, $section, $token),
        ], new Response(status: $status));
    }

    private function preview(
        string $targetType,
        DiscordServer|CatalogTemplate $target,
        CatalogCsvSection $section,
        Request $request,
        BackofficeSession $session,
        CatalogCsvParser $parser,
        CatalogCsvImportService $importService,
        CatalogCsvDraftStore $draftStore,
        DiscordGuildResourcesProviderInterface $resourcesProvider,
    ): Response {
        $file = $request->files->get('csv_file');
        if (!$file instanceof UploadedFile || 'csv' !== strtolower($file->getClientOriginalExtension())) {
            return $this->renderImport(
                $targetType,
                $target,
                $section,
                errors: [$this->error('Le fichier doit être un CSV valide.')],
                status: Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $document = $parser->parse($file->getPathname(), $section);
        $preview = $importService->preview($target, $section, $document);
        if (!$preview->valid()) {
            return $this->renderImport(
                $targetType,
                $target,
                $section,
                errors: $this->translatedErrors($preview->errors()),
                status: Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $userId = $session->discordUserId();
        if (null === $userId) {
            throw new AccessDeniedHttpException('Backoffice authentication required.');
        }
        $token = $draftStore->put($userId, $targetType, $this->targetId($target), $section, [
            'document' => $document->toArray(),
            'fingerprint' => $preview->fingerprint(),
        ]);

        return $this->renderImport(
            $targetType,
            $target,
            $section,
            $preview,
            $token,
            discordRoles: $this->discordRoles($target, $section, $resourcesProvider),
        );
    }

    private function apply(
        string $targetType,
        DiscordServer|CatalogTemplate $target,
        CatalogCsvSection $section,
        string $token,
        Request $request,
        BackofficeSession $session,
        CatalogCsvImportService $importService,
        CatalogCsvDraftStore $draftStore,
        DiscordGuildResourcesProviderInterface $resourcesProvider,
    ): Response {
        $userId = $session->discordUserId();
        if (null === $userId) {
            throw new AccessDeniedHttpException('Backoffice authentication required.');
        }
        $draft = $draftStore->get($token, $userId, $targetType, $this->targetId($target), $section);
        if (null === $draft || !\is_array($draft['document'] ?? null) || !\is_string($draft['fingerprint'] ?? null)) {
            throw new NotFoundHttpException('Le brouillon CSV est expiré ou indisponible.');
        }

        $document = CatalogCsvDocument::fromArray($draft['document']);
        $preview = $importService->preview($target, $section, $document);
        $mappings = [];
        if ($target instanceof DiscordServer && CatalogCsvSection::Ranks === $section) {
            $roles = $this->discordRoles($target, $section, $resourcesProvider, true);
            $allowed = array_fill_keys(array_column($roles, 'id'), true);
            foreach ($request->request->all('discord_roles') as $line => $discordId) {
                if ((\is_int($line) || ctype_digit($line)) && \is_string($discordId) && isset($allowed[$discordId])) {
                    $mappings[(int) $line] = $discordId;
                }
            }
            if (\count($mappings) !== \count($preview->discordRoleLines())) {
                return $this->renderImport(
                    $targetType,
                    $target,
                    $section,
                    $preview,
                    $token,
                    [$this->error('Chaque nouveau rang doit être relié à un rôle Discord actuel.')],
                    $roles,
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                );
            }
        }

        try {
            $result = $importService->apply(
                $target,
                $section,
                $document,
                $draft['fingerprint'],
                $mappings,
            );
        } catch (\InvalidArgumentException $exception) {
            $status = 'catalog_changed' === $exception->getMessage()
                ? Response::HTTP_CONFLICT
                : Response::HTTP_UNPROCESSABLE_ENTITY;
            $message = Response::HTTP_CONFLICT === $status
                ? 'Le catalogue a changé depuis l’aperçu. Recharge le fichier avant de confirmer.'
                : 'L’import ne peut pas être appliqué avec ces données.';

            return $this->renderImport(
                $targetType,
                $target,
                $section,
                $preview,
                $token,
                [$this->error($message)],
                $this->discordRoles($target, $section, $resourcesProvider),
                $status,
            );
        }

        $draftStore->remove($token);
        $counts = $result->counts();
        $this->addFlash('success', \sprintf(
            'Import terminé : %d création%s, %d mise%s à jour, %d ligne%s inchangée%s. L’état du catalogue a été recalculé.',
            $counts['creates'],
            1 === $counts['creates'] ? '' : 's',
            $counts['updates'],
            1 === $counts['updates'] ? '' : 's',
            $counts['unchanged'],
            1 === $counts['unchanged'] ? '' : 's',
            1 === $counts['unchanged'] ? '' : 's',
        ));

        return $target instanceof DiscordServer
            ? $this->redirectToRoute('app_server_configuration_section', ['guildId' => $target->discordId(), 'section' => $section->value])
            : $this->redirectToRoute('app_catalog_template_configuration_section', ['templateId' => $target->id(), 'section' => $section->value]);
    }

    private function serverTarget(
        string $guildId,
        BackofficeSession $session,
        BackofficeAccess $access,
        EntityManagerInterface $entityManager,
        bool $activeRequired,
    ): DiscordServer {
        if (!$session->isAuthenticated()) {
            throw new AccessDeniedHttpException('Backoffice authentication required.');
        }
        $guild = $access->findGuild($session->discordUserId(), $guildId);
        if (null === $guild) {
            throw new NotFoundHttpException('Server is not available in this backoffice session.');
        }
        if (true !== ($guild['canManageConfiguration'] ?? false)) {
            throw new AccessDeniedHttpException('Administrator permission required for this server.');
        }
        $server = $entityManager->getRepository(DiscordServer::class)->findOneBy(['discordId' => $guildId]);
        if (!$server instanceof DiscordServer) {
            throw new NotFoundHttpException('Server is not available in this backoffice session.');
        }
        if ($activeRequired && !$server->active()) {
            throw new ConflictHttpException('Server is inactive.');
        }

        return $server;
    }

    private function templateTarget(
        string $templateId,
        BackofficeSession $session,
        BackofficeAccess $access,
        EntityManagerInterface $entityManager,
    ): CatalogTemplate {
        if (!$session->isAuthenticated() || !$access->canManageCatalogTemplates($session->discordUserId())) {
            throw new AccessDeniedHttpException('Global template administration role required.');
        }
        $template = $entityManager->find(CatalogTemplate::class, (int) $templateId);
        if (!$template instanceof CatalogTemplate) {
            throw new NotFoundHttpException('Catalog template is not available.');
        }

        return $template;
    }

    private function section(string $section): CatalogCsvSection
    {
        return CatalogCsvSection::tryFrom($section)
            ?? throw new NotFoundHttpException('Unknown CSV catalogue section.');
    }

    /**
     * @return list<array{id: string, name: string, label: string, position: int, managed: bool}>
     */
    private function discordRoles(
        DiscordServer|CatalogTemplate $target,
        CatalogCsvSection $section,
        DiscordGuildResourcesProviderInterface $resourcesProvider,
        bool $fresh = false,
    ): array {
        if (!$target instanceof DiscordServer || CatalogCsvSection::Ranks !== $section) {
            return [];
        }

        try {
            $resources = $resourcesProvider->resourcesForGuild($target->discordId(), $fresh);
        } catch (\RuntimeException) {
            return [];
        }

        return array_values(array_filter(
            $resources['roles'],
            static fn (array $role): bool => false === $role['managed'],
        ));
    }

    /**
     * @return array{back: string, example: string, preview: string, apply: ?string}
     */
    private function routes(
        string $targetType,
        DiscordServer|CatalogTemplate $target,
        CatalogCsvSection $section,
        ?string $token,
    ): array {
        if ('server' === $targetType && $target instanceof DiscordServer) {
            $parameters = ['guildId' => $target->discordId(), 'section' => $section->value];

            return [
                'back' => $this->generateUrl('app_server_configuration_section', $parameters),
                'example' => $this->generateUrl('app_server_catalog_csv_example', $parameters),
                'preview' => $this->generateUrl('app_server_catalog_csv_preview', $parameters),
                'apply' => null === $token ? null : $this->generateUrl('app_server_catalog_csv_apply', $parameters + ['token' => $token]),
            ];
        }
        if (!$target instanceof CatalogTemplate) {
            throw new \LogicException('Invalid CSV catalogue target.');
        }
        $parameters = ['templateId' => $target->id(), 'section' => $section->value];

        return [
            'back' => $this->generateUrl('app_catalog_template_configuration_section', $parameters),
            'example' => $this->generateUrl('app_catalog_template_csv_example', $parameters),
            'preview' => $this->generateUrl('app_catalog_template_csv_preview', $parameters),
            'apply' => null === $token ? null : $this->generateUrl('app_catalog_template_csv_apply', $parameters + ['token' => $token]),
        ];
    }

    private function targetId(DiscordServer|CatalogTemplate $target): string
    {
        if ($target instanceof DiscordServer) {
            return $target->discordId();
        }
        $id = $target->id();
        if (null === $id) {
            throw new \LogicException('CSV catalogue template must be persisted.');
        }

        return (string) $id;
    }

    /**
     * @return array{line: ?int, column: ?string, message: string, value: ?string}
     */
    private function error(string $message): array
    {
        return ['line' => null, 'column' => null, 'message' => $message, 'value' => null];
    }

    /**
     * @param list<array{line: ?int, column: ?string, message: string, value: ?string}> $errors
     *
     * @return list<array{line: ?int, column: ?string, message: string, value: ?string}>
     */
    private function translatedErrors(array $errors): array
    {
        $labels = [
            'file_too_large' => 'Le fichier dépasse la limite de 5 Mio.',
            'too_many_rows' => 'Le fichier dépasse 1 000 lignes de données.',
            'invalid_utf8' => 'Le fichier doit être encodé en UTF-8.',
            'missing_header' => 'La ligne d’en-tête est absente.',
            'missing_required_header' => 'Une colonne obligatoire est absente.',
            'unknown_header' => 'Une colonne inconnue est présente.',
            'duplicate_header' => 'Une colonne est déclarée plusieurs fois.',
            'required_value' => 'Une valeur obligatoire est absente.',
            'invalid_integer' => 'Le pourcentage doit être un nombre entier.',
            'percentage_out_of_range' => 'Le pourcentage doit être compris entre 0 et 100.',
            'invalid_boolean' => 'La valeur doit être oui/non, true/false ou 1/0.',
            'value_too_long' => 'La valeur est trop longue.',
            'duplicate_natural_key' => 'Cette entrée apparaît plusieurs fois dans le fichier.',
            'invalid_rank_percentage_total' => 'Le total projeté des rangs doit être exactement de 100 %.',
            'invalid_role_percentage_total' => 'Le total projeté des rôles doit être exactement de 100 %.',
            'invalid_rank_stat_percentage_total' => 'Le total projeté des stats de ce rang doit être exactement de 100 %.',
            'multiple_staff_ranks' => 'Un seul rang peut être marqué comme rang staff.',
            'rank_not_found' => 'Le rang référencé n’existe pas dans ce catalogue.',
            'stat_not_found' => 'La stat référencée n’existe pas dans ce catalogue.',
        ];
        foreach ($errors as &$error) {
            $error['message'] = $labels[$error['message']] ?? $error['message'];
        }
        unset($error);

        return $errors;
    }
}
