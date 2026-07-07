<?php

namespace App\Controller;

use App\Backoffice\BackofficeAccess;
use App\Backoffice\BackofficeSession;
use App\Backoffice\CatalogTemplateImporter;
use App\Discord\DiscordGuildResourcesProviderInterface;
use App\Entity\CatalogTemplate;
use App\Entity\CatalogTemplateByeMessage;
use App\Entity\CatalogTemplateElement;
use App\Entity\CatalogTemplateRank;
use App\Entity\CatalogTemplateRankStat;
use App\Entity\CatalogTemplateRole;
use App\Entity\CatalogTemplateStat;
use App\Entity\CatalogTemplateWelcomeMessage;
use App\Entity\DiscordEmoji;
use App\Entity\DiscordServer;
use App\Entity\DiscordUser;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class CatalogTemplateController extends AbstractController
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
    private const array TEMPLATE_SECTIONS = [
        'ranks' => [
            'label' => 'Rangs',
            'description' => 'Les rangs à importer, à relier aux rôles Discord du serveur.',
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

    #[Route('/app/modeles-catalogue', name: 'app_catalog_templates', methods: ['GET'])]
    public function templates(
        BackofficeSession $backofficeSession,
        BackofficeAccess $backofficeAccess,
        EntityManagerInterface $entityManager,
    ): Response {
        $this->templateAdminUserOr403($backofficeSession, $backofficeAccess, $entityManager);

        return $this->render('backoffice/catalog_templates.html.twig', [
            'templates' => array_map(
                fn (CatalogTemplate $template): array => $this->templateSummaryPayload($entityManager, $template),
                $entityManager->getRepository(CatalogTemplate::class)->findBy([], ['published' => 'DESC', 'name' => 'ASC']),
            ),
        ]);
    }

    #[Route('/app/modeles-catalogue', name: 'app_catalog_template_create', methods: ['POST'])]
    public function createTemplate(
        Request $request,
        BackofficeSession $backofficeSession,
        BackofficeAccess $backofficeAccess,
        EntityManagerInterface $entityManager,
    ): Response {
        $user = $this->templateAdminUserOr403($backofficeSession, $backofficeAccess, $entityManager);
        $name = $this->requiredRequestString($request, 'name');
        if (null === $name) {
            return $this->redirectToRoute('app_catalog_templates');
        }

        $template = new CatalogTemplate($name, $this->optionalRequestString($request, 'description'), $user);
        $entityManager->persist($template);
        $entityManager->flush();

        return $this->redirectToRoute('app_catalog_template_configuration', ['templateId' => $template->id()]);
    }

    #[Route('/app/modeles-catalogue/{templateId}/configuration', name: 'app_catalog_template_configuration', requirements: ['templateId' => '\d+'], methods: ['GET'])]
    public function configuration(
        string $templateId,
        BackofficeSession $backofficeSession,
        BackofficeAccess $backofficeAccess,
        EntityManagerInterface $entityManager,
    ): Response {
        $this->templateAdminUserOr403($backofficeSession, $backofficeAccess, $entityManager);
        $template = $this->templateOr404($entityManager, (int) $templateId);
        $catalog = $this->templateCatalogPayload($entityManager, $template);

        return $this->render('backoffice/catalog_template_configuration.html.twig', [
            'template' => $this->templatePayload($entityManager, $template),
            'catalog' => $catalog,
            'emoji_picker' => $this->emojiPickerPayload($entityManager),
            'configuration_sections' => $this->templateSections($catalog),
            'active_section' => 'overview',
            'active_configuration_section' => null,
        ]);
    }

    #[Route('/app/modeles-catalogue/{templateId}/configuration/{section}', name: 'app_catalog_template_configuration_section', requirements: ['templateId' => '\d+'], methods: ['GET'])]
    public function configurationSection(
        string $templateId,
        string $section,
        BackofficeSession $backofficeSession,
        BackofficeAccess $backofficeAccess,
        EntityManagerInterface $entityManager,
    ): Response {
        $this->templateSectionOr404($section);
        $this->templateAdminUserOr403($backofficeSession, $backofficeAccess, $entityManager);
        $template = $this->templateOr404($entityManager, (int) $templateId);
        $catalog = $this->templateCatalogPayload($entityManager, $template);

        return $this->render('backoffice/catalog_template_configuration.html.twig', [
            'template' => $this->templatePayload($entityManager, $template),
            'catalog' => $catalog,
            'emoji_picker' => $this->emojiPickerPayload($entityManager),
            'configuration_sections' => $this->templateSections($catalog),
            'active_section' => $section,
            'active_configuration_section' => $this->templateSectionPayload($catalog, $section),
        ]);
    }

    #[Route('/app/modeles-catalogue/{templateId}/publication', name: 'app_catalog_template_publish', requirements: ['templateId' => '\d+'], methods: ['POST'])]
    public function updatePublication(
        string $templateId,
        Request $request,
        BackofficeSession $backofficeSession,
        BackofficeAccess $backofficeAccess,
        EntityManagerInterface $entityManager,
    ): Response {
        $this->templateAdminUserOr403($backofficeSession, $backofficeAccess, $entityManager);
        $template = $this->templateOr404($entityManager, (int) $templateId);

        if ('1' === $request->request->get('published')) {
            $template->publish();
        } else {
            $template->unpublish();
        }
        $entityManager->flush();

        return $this->redirectToRoute('app_catalog_template_configuration', ['templateId' => $templateId]);
    }

    #[Route('/app/modeles-catalogue/{templateId}/catalogue/ranks', name: 'app_catalog_template_rank_create', requirements: ['templateId' => '\d+'], methods: ['POST'])]
    public function createRank(
        string $templateId,
        Request $request,
        BackofficeSession $backofficeSession,
        BackofficeAccess $backofficeAccess,
        EntityManagerInterface $entityManager,
    ): Response {
        $template = $this->editableTemplateOr403($templateId, $backofficeSession, $backofficeAccess, $entityManager);
        $roleKey = $this->requiredRequestString($request, 'role_key');
        $name = $this->requiredRequestString($request, 'name');

        if (null !== $roleKey && null !== $name) {
            $entityManager->persist(new CatalogTemplateRank(
                $template,
                $roleKey,
                $name,
                $this->requestPercentage($request),
                $this->optionalRequestString($request, 'bye_title'),
                $request->request->has('is_staff'),
            ));
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_catalog_template_configuration_section', ['templateId' => $templateId, 'section' => 'ranks']);
    }

    #[Route('/app/modeles-catalogue/{templateId}/catalogue/ranks/{rankId}', name: 'app_catalog_template_rank_update', requirements: ['templateId' => '\d+', 'rankId' => '\d+'], methods: ['POST'])]
    public function updateRank(
        string $templateId,
        string $rankId,
        Request $request,
        BackofficeSession $backofficeSession,
        BackofficeAccess $backofficeAccess,
        EntityManagerInterface $entityManager,
    ): Response {
        $template = $this->editableTemplateOr403($templateId, $backofficeSession, $backofficeAccess, $entityManager);
        $rank = $this->templateRankOr404($entityManager, $template, (int) $rankId);
        $roleKey = $this->requiredRequestString($request, 'role_key');
        $name = $this->requiredRequestString($request, 'name');

        if (null !== $roleKey && null !== $name) {
            $rank->updateConfiguration(
                $roleKey,
                $name,
                $this->requestPercentage($request),
                $this->optionalRequestString($request, 'bye_title'),
                $request->request->has('is_staff'),
            );
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_catalog_template_configuration_section', ['templateId' => $templateId, 'section' => 'ranks']);
    }

    #[Route('/app/modeles-catalogue/{templateId}/catalogue/ranks/{rankId}/supprimer', name: 'app_catalog_template_rank_delete', requirements: ['templateId' => '\d+', 'rankId' => '\d+'], methods: ['POST'])]
    public function deleteRank(
        string $templateId,
        string $rankId,
        BackofficeSession $backofficeSession,
        BackofficeAccess $backofficeAccess,
        EntityManagerInterface $entityManager,
    ): Response {
        $template = $this->editableTemplateOr403($templateId, $backofficeSession, $backofficeAccess, $entityManager);
        $entityManager->remove($this->templateRankOr404($entityManager, $template, (int) $rankId));
        $entityManager->flush();

        return $this->redirectToRoute('app_catalog_template_configuration_section', ['templateId' => $templateId, 'section' => 'ranks']);
    }

    #[Route('/app/modeles-catalogue/{templateId}/catalogue/rank-stats', name: 'app_catalog_template_rank_stat_upsert', requirements: ['templateId' => '\d+'], methods: ['POST'])]
    public function upsertRankStat(
        string $templateId,
        Request $request,
        BackofficeSession $backofficeSession,
        BackofficeAccess $backofficeAccess,
        EntityManagerInterface $entityManager,
    ): Response {
        $template = $this->editableTemplateOr403($templateId, $backofficeSession, $backofficeAccess, $entityManager);
        $rankId = (int) $request->request->get('rank_id', 0);
        $statId = (int) $request->request->get('stat_id', 0);

        if ($rankId > 0 && $statId > 0) {
            $rank = $this->templateRankOr404($entityManager, $template, $rankId);
            $stat = $this->templateStatOr404($entityManager, $template, $statId);
            $rankStat = $entityManager->getRepository(CatalogTemplateRankStat::class)->findOneBy(['rank' => $rank, 'stat' => $stat]);
            if (!$rankStat instanceof CatalogTemplateRankStat) {
                $rankStat = new CatalogTemplateRankStat($rank, $stat, $this->requestPercentage($request));
                $entityManager->persist($rankStat);
            } else {
                $rankStat->updatePercentage($this->requestPercentage($request));
            }

            $entityManager->flush();
        }

        return $this->redirectToRoute('app_catalog_template_configuration_section', ['templateId' => $templateId, 'section' => 'rank-stats']);
    }

    #[Route('/app/modeles-catalogue/{templateId}/catalogue/rank-stats/{rankId}/{statId}/supprimer', name: 'app_catalog_template_rank_stat_delete', requirements: ['templateId' => '\d+', 'rankId' => '\d+', 'statId' => '\d+'], methods: ['POST'])]
    public function deleteRankStat(
        string $templateId,
        string $rankId,
        string $statId,
        BackofficeSession $backofficeSession,
        BackofficeAccess $backofficeAccess,
        EntityManagerInterface $entityManager,
    ): Response {
        $template = $this->editableTemplateOr403($templateId, $backofficeSession, $backofficeAccess, $entityManager);
        $rank = $this->templateRankOr404($entityManager, $template, (int) $rankId);
        $stat = $this->templateStatOr404($entityManager, $template, (int) $statId);
        $rankStat = $entityManager->getRepository(CatalogTemplateRankStat::class)->findOneBy(['rank' => $rank, 'stat' => $stat]);
        if ($rankStat instanceof CatalogTemplateRankStat) {
            $entityManager->remove($rankStat);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_catalog_template_configuration_section', ['templateId' => $templateId, 'section' => 'rank-stats']);
    }

    #[Route('/app/modeles-catalogue/{templateId}/catalogue/roles', name: 'app_catalog_template_role_create', requirements: ['templateId' => '\d+'], methods: ['POST'])]
    public function createRole(
        string $templateId,
        Request $request,
        BackofficeSession $backofficeSession,
        BackofficeAccess $backofficeAccess,
        EntityManagerInterface $entityManager,
    ): Response {
        $template = $this->editableTemplateOr403($templateId, $backofficeSession, $backofficeAccess, $entityManager);
        $name = $this->requiredRequestString($request, 'name');

        if (null !== $name) {
            $emoji = $this->requestEmoji($request, CatalogTemplateRole::DEFAULT_EMOJI);
            $entityManager->persist(new CatalogTemplateRole(
                $template,
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

        return $this->redirectToRoute('app_catalog_template_configuration_section', ['templateId' => $templateId, 'section' => 'roles']);
    }

    #[Route('/app/modeles-catalogue/{templateId}/catalogue/roles/{roleId}', name: 'app_catalog_template_role_update', requirements: ['templateId' => '\d+', 'roleId' => '\d+'], methods: ['POST'])]
    public function updateRole(
        string $templateId,
        string $roleId,
        Request $request,
        BackofficeSession $backofficeSession,
        BackofficeAccess $backofficeAccess,
        EntityManagerInterface $entityManager,
    ): Response {
        $template = $this->editableTemplateOr403($templateId, $backofficeSession, $backofficeAccess, $entityManager);
        $role = $this->templateRoleOr404($entityManager, $template, (int) $roleId);
        $name = $this->requiredRequestString($request, 'name');

        if (null !== $name) {
            $emoji = $this->requestEmoji($request, CatalogTemplateRole::DEFAULT_EMOJI);
            $role->updateConfiguration($name, $this->requestPercentage($request), $emoji['source'], $emoji['unicode'], $emoji['id'], $emoji['name'], $emoji['animated']);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_catalog_template_configuration_section', ['templateId' => $templateId, 'section' => 'roles']);
    }

    #[Route('/app/modeles-catalogue/{templateId}/catalogue/roles/{roleId}/supprimer', name: 'app_catalog_template_role_delete', requirements: ['templateId' => '\d+', 'roleId' => '\d+'], methods: ['POST'])]
    public function deleteRole(
        string $templateId,
        string $roleId,
        BackofficeSession $backofficeSession,
        BackofficeAccess $backofficeAccess,
        EntityManagerInterface $entityManager,
    ): Response {
        $template = $this->editableTemplateOr403($templateId, $backofficeSession, $backofficeAccess, $entityManager);
        $entityManager->remove($this->templateRoleOr404($entityManager, $template, (int) $roleId));
        $entityManager->flush();

        return $this->redirectToRoute('app_catalog_template_configuration_section', ['templateId' => $templateId, 'section' => 'roles']);
    }

    #[Route('/app/modeles-catalogue/{templateId}/catalogue/stats', name: 'app_catalog_template_stat_create', requirements: ['templateId' => '\d+'], methods: ['POST'])]
    public function createStat(
        string $templateId,
        Request $request,
        BackofficeSession $backofficeSession,
        BackofficeAccess $backofficeAccess,
        EntityManagerInterface $entityManager,
    ): Response {
        $template = $this->editableTemplateOr403($templateId, $backofficeSession, $backofficeAccess, $entityManager);
        $name = $this->requiredRequestString($request, 'name');
        if (null !== $name) {
            $entityManager->persist(new CatalogTemplateStat($template, $name));
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_catalog_template_configuration_section', ['templateId' => $templateId, 'section' => 'stats']);
    }

    #[Route('/app/modeles-catalogue/{templateId}/catalogue/stats/{statId}', name: 'app_catalog_template_stat_update', requirements: ['templateId' => '\d+', 'statId' => '\d+'], methods: ['POST'])]
    public function updateStat(
        string $templateId,
        string $statId,
        Request $request,
        BackofficeSession $backofficeSession,
        BackofficeAccess $backofficeAccess,
        EntityManagerInterface $entityManager,
    ): Response {
        $template = $this->editableTemplateOr403($templateId, $backofficeSession, $backofficeAccess, $entityManager);
        $name = $this->requiredRequestString($request, 'name');
        if (null !== $name) {
            $this->templateStatOr404($entityManager, $template, (int) $statId)->updateName($name);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_catalog_template_configuration_section', ['templateId' => $templateId, 'section' => 'stats']);
    }

    #[Route('/app/modeles-catalogue/{templateId}/catalogue/stats/{statId}/supprimer', name: 'app_catalog_template_stat_delete', requirements: ['templateId' => '\d+', 'statId' => '\d+'], methods: ['POST'])]
    public function deleteStat(
        string $templateId,
        string $statId,
        BackofficeSession $backofficeSession,
        BackofficeAccess $backofficeAccess,
        EntityManagerInterface $entityManager,
    ): Response {
        $template = $this->editableTemplateOr403($templateId, $backofficeSession, $backofficeAccess, $entityManager);
        $entityManager->remove($this->templateStatOr404($entityManager, $template, (int) $statId));
        $entityManager->flush();

        return $this->redirectToRoute('app_catalog_template_configuration_section', ['templateId' => $templateId, 'section' => 'stats']);
    }

    #[Route('/app/modeles-catalogue/{templateId}/catalogue/elements', name: 'app_catalog_template_element_create', requirements: ['templateId' => '\d+'], methods: ['POST'])]
    public function createElement(
        string $templateId,
        Request $request,
        BackofficeSession $backofficeSession,
        BackofficeAccess $backofficeAccess,
        EntityManagerInterface $entityManager,
    ): Response {
        $template = $this->editableTemplateOr403($templateId, $backofficeSession, $backofficeAccess, $entityManager);
        $name = $this->requiredRequestString($request, 'name');
        if (null !== $name) {
            $emoji = $this->requestEmoji($request, CatalogTemplateElement::DEFAULT_EMOJI);
            $entityManager->persist(new CatalogTemplateElement($template, $name, $emoji['source'], $emoji['unicode'], $emoji['id'], $emoji['name'], $emoji['animated']));
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_catalog_template_configuration_section', ['templateId' => $templateId, 'section' => 'elements']);
    }

    #[Route('/app/modeles-catalogue/{templateId}/catalogue/elements/{elementId}', name: 'app_catalog_template_element_update', requirements: ['templateId' => '\d+', 'elementId' => '\d+'], methods: ['POST'])]
    public function updateElement(
        string $templateId,
        string $elementId,
        Request $request,
        BackofficeSession $backofficeSession,
        BackofficeAccess $backofficeAccess,
        EntityManagerInterface $entityManager,
    ): Response {
        $template = $this->editableTemplateOr403($templateId, $backofficeSession, $backofficeAccess, $entityManager);
        $element = $this->templateElementOr404($entityManager, $template, (int) $elementId);
        $name = $this->requiredRequestString($request, 'name');
        if (null !== $name) {
            $emoji = $this->requestEmoji($request, CatalogTemplateElement::DEFAULT_EMOJI);
            $element->updateConfiguration($name, $emoji['source'], $emoji['unicode'], $emoji['id'], $emoji['name'], $emoji['animated']);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_catalog_template_configuration_section', ['templateId' => $templateId, 'section' => 'elements']);
    }

    #[Route('/app/modeles-catalogue/{templateId}/catalogue/elements/{elementId}/supprimer', name: 'app_catalog_template_element_delete', requirements: ['templateId' => '\d+', 'elementId' => '\d+'], methods: ['POST'])]
    public function deleteElement(
        string $templateId,
        string $elementId,
        BackofficeSession $backofficeSession,
        BackofficeAccess $backofficeAccess,
        EntityManagerInterface $entityManager,
    ): Response {
        $template = $this->editableTemplateOr403($templateId, $backofficeSession, $backofficeAccess, $entityManager);
        $entityManager->remove($this->templateElementOr404($entityManager, $template, (int) $elementId));
        $entityManager->flush();

        return $this->redirectToRoute('app_catalog_template_configuration_section', ['templateId' => $templateId, 'section' => 'elements']);
    }

    #[Route('/app/modeles-catalogue/{templateId}/catalogue/welcome-messages', name: 'app_catalog_template_welcome_message_create', requirements: ['templateId' => '\d+'], methods: ['POST'])]
    public function createWelcomeMessage(
        string $templateId,
        Request $request,
        BackofficeSession $backofficeSession,
        BackofficeAccess $backofficeAccess,
        EntityManagerInterface $entityManager,
    ): Response {
        $template = $this->editableTemplateOr403($templateId, $backofficeSession, $backofficeAccess, $entityManager);
        $rankId = (int) $request->request->get('rank_id', 0);
        $message = $this->requiredRequestString($request, 'message');
        if ($rankId > 0 && null !== $message) {
            $entityManager->persist(new CatalogTemplateWelcomeMessage($template, $this->templateRankOr404($entityManager, $template, $rankId), $message));
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_catalog_template_configuration_section', ['templateId' => $templateId, 'section' => 'welcome-messages']);
    }

    #[Route('/app/modeles-catalogue/{templateId}/catalogue/welcome-messages/{messageId}', name: 'app_catalog_template_welcome_message_update', requirements: ['templateId' => '\d+', 'messageId' => '\d+'], methods: ['POST'])]
    public function updateWelcomeMessage(
        string $templateId,
        string $messageId,
        Request $request,
        BackofficeSession $backofficeSession,
        BackofficeAccess $backofficeAccess,
        EntityManagerInterface $entityManager,
    ): Response {
        $template = $this->editableTemplateOr403($templateId, $backofficeSession, $backofficeAccess, $entityManager);
        $message = $this->requiredRequestString($request, 'message');
        if (null !== $message) {
            $this->templateWelcomeMessageOr404($entityManager, $template, (int) $messageId)->updateMessage($message);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_catalog_template_configuration_section', ['templateId' => $templateId, 'section' => 'welcome-messages']);
    }

    #[Route('/app/modeles-catalogue/{templateId}/catalogue/welcome-messages/{messageId}/supprimer', name: 'app_catalog_template_welcome_message_delete', requirements: ['templateId' => '\d+', 'messageId' => '\d+'], methods: ['POST'])]
    public function deleteWelcomeMessage(
        string $templateId,
        string $messageId,
        BackofficeSession $backofficeSession,
        BackofficeAccess $backofficeAccess,
        EntityManagerInterface $entityManager,
    ): Response {
        $template = $this->editableTemplateOr403($templateId, $backofficeSession, $backofficeAccess, $entityManager);
        $entityManager->remove($this->templateWelcomeMessageOr404($entityManager, $template, (int) $messageId));
        $entityManager->flush();

        return $this->redirectToRoute('app_catalog_template_configuration_section', ['templateId' => $templateId, 'section' => 'welcome-messages']);
    }

    #[Route('/app/modeles-catalogue/{templateId}/catalogue/bye-messages', name: 'app_catalog_template_bye_message_create', requirements: ['templateId' => '\d+'], methods: ['POST'])]
    public function createByeMessage(
        string $templateId,
        Request $request,
        BackofficeSession $backofficeSession,
        BackofficeAccess $backofficeAccess,
        EntityManagerInterface $entityManager,
    ): Response {
        $template = $this->editableTemplateOr403($templateId, $backofficeSession, $backofficeAccess, $entityManager);
        $rankId = (int) $request->request->get('rank_id', 0);
        $message = $this->requiredRequestString($request, 'message');
        if ($rankId > 0 && null !== $message) {
            $entityManager->persist(new CatalogTemplateByeMessage($template, $this->templateRankOr404($entityManager, $template, $rankId), $message));
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_catalog_template_configuration_section', ['templateId' => $templateId, 'section' => 'bye-messages']);
    }

    #[Route('/app/modeles-catalogue/{templateId}/catalogue/bye-messages/{messageId}', name: 'app_catalog_template_bye_message_update', requirements: ['templateId' => '\d+', 'messageId' => '\d+'], methods: ['POST'])]
    public function updateByeMessage(
        string $templateId,
        string $messageId,
        Request $request,
        BackofficeSession $backofficeSession,
        BackofficeAccess $backofficeAccess,
        EntityManagerInterface $entityManager,
    ): Response {
        $template = $this->editableTemplateOr403($templateId, $backofficeSession, $backofficeAccess, $entityManager);
        $message = $this->requiredRequestString($request, 'message');
        if (null !== $message) {
            $this->templateByeMessageOr404($entityManager, $template, (int) $messageId)->updateMessage($message);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_catalog_template_configuration_section', ['templateId' => $templateId, 'section' => 'bye-messages']);
    }

    #[Route('/app/modeles-catalogue/{templateId}/catalogue/bye-messages/{messageId}/supprimer', name: 'app_catalog_template_bye_message_delete', requirements: ['templateId' => '\d+', 'messageId' => '\d+'], methods: ['POST'])]
    public function deleteByeMessage(
        string $templateId,
        string $messageId,
        BackofficeSession $backofficeSession,
        BackofficeAccess $backofficeAccess,
        EntityManagerInterface $entityManager,
    ): Response {
        $template = $this->editableTemplateOr403($templateId, $backofficeSession, $backofficeAccess, $entityManager);
        $entityManager->remove($this->templateByeMessageOr404($entityManager, $template, (int) $messageId));
        $entityManager->flush();

        return $this->redirectToRoute('app_catalog_template_configuration_section', ['templateId' => $templateId, 'section' => 'bye-messages']);
    }

    #[Route('/app/serveurs/{guildId}/configuration/importer', name: 'app_server_catalog_template_imports', methods: ['GET'])]
    public function importableTemplates(
        string $guildId,
        BackofficeSession $backofficeSession,
        BackofficeAccess $backofficeAccess,
        EntityManagerInterface $entityManager,
    ): Response {
        $guild = $this->manageableGuildOr404($guildId, $backofficeSession, $backofficeAccess);
        $this->findServerEntityOr404($entityManager, $guild['id']);

        return $this->render('backoffice/catalog_template_imports.html.twig', [
            'guild' => $guild,
            'templates' => array_map(
                fn (CatalogTemplate $template): array => $this->templateSummaryPayload($entityManager, $template),
                $entityManager->getRepository(CatalogTemplate::class)->findBy(['published' => true], ['name' => 'ASC']),
            ),
        ]);
    }

    #[Route('/app/serveurs/{guildId}/configuration/importer/{templateId}', name: 'app_server_catalog_template_import', requirements: ['templateId' => '\d+'], methods: ['GET'])]
    public function importTemplateForm(
        string $guildId,
        string $templateId,
        BackofficeSession $backofficeSession,
        BackofficeAccess $backofficeAccess,
        EntityManagerInterface $entityManager,
        DiscordGuildResourcesProviderInterface $discordGuildResourcesProvider,
    ): Response {
        $guild = $this->manageableGuildOr404($guildId, $backofficeSession, $backofficeAccess);
        $this->findServerEntityOr404($entityManager, $guild['id']);
        $template = $this->publishedTemplateOr404($entityManager, (int) $templateId);
        $discordResources = $discordGuildResourcesProvider->resourcesForGuild($guild['id']);

        return $this->render('backoffice/catalog_template_import.html.twig', [
            'guild' => $guild,
            'template' => $this->templatePayload($entityManager, $template),
            'catalog' => $this->templateCatalogPayload($entityManager, $template),
            'discord_resources' => $discordResources,
        ]);
    }

    #[Route('/app/serveurs/{guildId}/configuration/importer/{templateId}', name: 'app_server_catalog_template_import_apply', requirements: ['templateId' => '\d+'], methods: ['POST'])]
    public function importTemplate(
        string $guildId,
        string $templateId,
        Request $request,
        BackofficeSession $backofficeSession,
        BackofficeAccess $backofficeAccess,
        EntityManagerInterface $entityManager,
        CatalogTemplateImporter $importer,
    ): Response {
        $guild = $this->manageableGuildOr404($guildId, $backofficeSession, $backofficeAccess);
        $server = $this->findServerEntityOr404($entityManager, $guild['id']);
        $template = $this->publishedTemplateOr404($entityManager, (int) $templateId);
        $rankRoles = $request->request->all('rank_roles');

        $importer->import($server, $template, \is_array($rankRoles) ? $rankRoles : []);

        return $this->redirectToRoute('app_server_configuration', ['guildId' => $guildId]);
    }

    private function editableTemplateOr403(
        string $templateId,
        BackofficeSession $backofficeSession,
        BackofficeAccess $backofficeAccess,
        EntityManagerInterface $entityManager,
    ): CatalogTemplate {
        $this->templateAdminUserOr403($backofficeSession, $backofficeAccess, $entityManager);

        return $this->templateOr404($entityManager, (int) $templateId);
    }

    private function templateAdminUserOr403(
        BackofficeSession $backofficeSession,
        BackofficeAccess $backofficeAccess,
        EntityManagerInterface $entityManager,
    ): DiscordUser {
        if (!$backofficeSession->isAuthenticated()) {
            throw new AccessDeniedHttpException('Backoffice authentication required.');
        }

        $userId = $backofficeSession->discordUserId();
        if (!$backofficeAccess->canManageCatalogTemplates($userId)) {
            throw new AccessDeniedHttpException('Global template administration role required.');
        }

        $user = $entityManager->find(DiscordUser::class, $userId);
        if (!$user instanceof DiscordUser) {
            throw new AccessDeniedHttpException('Backoffice user is not persisted.');
        }

        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    private function manageableGuildOr404(string $guildId, BackofficeSession $backofficeSession, BackofficeAccess $backofficeAccess): array
    {
        if (!$backofficeSession->isAuthenticated()) {
            throw new AccessDeniedHttpException('Backoffice authentication required.');
        }

        $guild = $backofficeAccess->findGuild($backofficeSession->discordUserId(), $guildId);
        if (null === $guild) {
            throw new NotFoundHttpException('Server is not available in this backoffice session.');
        }

        if (true !== ($guild['canManageConfiguration'] ?? false)) {
            throw new AccessDeniedHttpException('Administrator permission required for this server.');
        }

        return $guild;
    }

    private function findServerEntityOr404(EntityManagerInterface $entityManager, string $guildId): DiscordServer
    {
        $server = $entityManager->getRepository(DiscordServer::class)->findOneBy(['discordId' => $guildId]);
        if (!$server instanceof DiscordServer) {
            throw new NotFoundHttpException('Server is not available in this backoffice session.');
        }

        return $server;
    }

    private function templateOr404(EntityManagerInterface $entityManager, int $templateId): CatalogTemplate
    {
        $template = $entityManager->find(CatalogTemplate::class, $templateId);
        if (!$template instanceof CatalogTemplate) {
            throw new NotFoundHttpException('Catalog template is not available.');
        }

        return $template;
    }

    private function publishedTemplateOr404(EntityManagerInterface $entityManager, int $templateId): CatalogTemplate
    {
        $template = $this->templateOr404($entityManager, $templateId);
        if (!$template->published()) {
            throw new NotFoundHttpException('Catalog template is not published.');
        }

        return $template;
    }

    private function templateRankOr404(EntityManagerInterface $entityManager, CatalogTemplate $template, int $rankId): CatalogTemplateRank
    {
        $rank = $entityManager->getRepository(CatalogTemplateRank::class)->findOneBy(['id' => $rankId, 'template' => $template]);
        if (!$rank instanceof CatalogTemplateRank) {
            throw new NotFoundHttpException('Template rank is not available.');
        }

        return $rank;
    }

    private function templateRoleOr404(EntityManagerInterface $entityManager, CatalogTemplate $template, int $roleId): CatalogTemplateRole
    {
        $role = $entityManager->getRepository(CatalogTemplateRole::class)->findOneBy(['id' => $roleId, 'template' => $template]);
        if (!$role instanceof CatalogTemplateRole) {
            throw new NotFoundHttpException('Template role is not available.');
        }

        return $role;
    }

    private function templateStatOr404(EntityManagerInterface $entityManager, CatalogTemplate $template, int $statId): CatalogTemplateStat
    {
        $stat = $entityManager->getRepository(CatalogTemplateStat::class)->findOneBy(['id' => $statId, 'template' => $template]);
        if (!$stat instanceof CatalogTemplateStat) {
            throw new NotFoundHttpException('Template stat is not available.');
        }

        return $stat;
    }

    private function templateElementOr404(EntityManagerInterface $entityManager, CatalogTemplate $template, int $elementId): CatalogTemplateElement
    {
        $element = $entityManager->getRepository(CatalogTemplateElement::class)->findOneBy(['id' => $elementId, 'template' => $template]);
        if (!$element instanceof CatalogTemplateElement) {
            throw new NotFoundHttpException('Template element is not available.');
        }

        return $element;
    }

    private function templateWelcomeMessageOr404(EntityManagerInterface $entityManager, CatalogTemplate $template, int $messageId): CatalogTemplateWelcomeMessage
    {
        $message = $entityManager->getRepository(CatalogTemplateWelcomeMessage::class)->findOneBy(['id' => $messageId, 'template' => $template]);
        if (!$message instanceof CatalogTemplateWelcomeMessage) {
            throw new NotFoundHttpException('Template welcome message is not available.');
        }

        return $message;
    }

    private function templateByeMessageOr404(EntityManagerInterface $entityManager, CatalogTemplate $template, int $messageId): CatalogTemplateByeMessage
    {
        $message = $entityManager->getRepository(CatalogTemplateByeMessage::class)->findOneBy(['id' => $messageId, 'template' => $template]);
        if (!$message instanceof CatalogTemplateByeMessage) {
            throw new NotFoundHttpException('Template bye message is not available.');
        }

        return $message;
    }

    private function templateSectionOr404(string $section): void
    {
        if (!isset(self::TEMPLATE_SECTIONS[$section])) {
            throw new NotFoundHttpException('Template configuration section is not available.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function templatePayload(EntityManagerInterface $entityManager, CatalogTemplate $template): array
    {
        return [
            ...$this->templateSummaryPayload($entityManager, $template),
            'description' => $template->description(),
        ];
    }

    /**
     * @return array{id: int, name: string, description: ?string, published: bool, total_count: int}
     */
    private function templateSummaryPayload(EntityManagerInterface $entityManager, CatalogTemplate $template): array
    {
        $catalog = $this->templateCatalogPayload($entityManager, $template);

        return [
            'id' => (int) $template->id(),
            'name' => $template->name(),
            'description' => $template->description(),
            'published' => $template->published(),
            'total_count' => array_sum(array_map(static fn (mixed $rows): int => \is_array($rows) ? \count($rows) : 0, $catalog)),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function templateCatalogPayload(EntityManagerInterface $entityManager, CatalogTemplate $template): array
    {
        $ranks = $entityManager->getRepository(CatalogTemplateRank::class)->findBy(['template' => $template], ['percentage' => 'ASC', 'name' => 'ASC']);

        return [
            'ranks' => array_map(
                fn (CatalogTemplateRank $rank): array => [
                    'id' => (int) $rank->id(),
                    'role_key' => $rank->roleKey(),
                    'name' => $rank->name(),
                    'percentage' => $rank->percentage(),
                    'bye_title' => $rank->byeTitle(),
                    'is_staff' => $rank->isStaff(),
                    'rank_stats' => $this->templateRankStatsPayload($entityManager, $rank),
                    'welcome_messages' => $this->templateWelcomeMessagesPayload($entityManager, $template, $rank),
                    'bye_messages' => $this->templateByeMessagesPayload($entityManager, $template, $rank),
                ],
                $ranks,
            ),
            'rank_stats' => $this->templateRankStatsListPayload($entityManager, $template),
            'welcome_messages' => $this->templateWelcomeMessagesListPayload($entityManager, $template),
            'bye_messages' => $this->templateByeMessagesListPayload($entityManager, $template),
            'roles' => array_map(
                fn (CatalogTemplateRole $role): array => [
                    'id' => (int) $role->id(),
                    'name' => $role->name(),
                    'percentage' => $role->percentage(),
                    ...$this->emojiPayload($role->emojiSource(), $role->emojiUnicode(), $role->emojiId(), $role->emojiName(), $role->emojiAnimated()),
                ],
                $entityManager->getRepository(CatalogTemplateRole::class)->findBy(['template' => $template], ['percentage' => 'ASC', 'name' => 'ASC']),
            ),
            'stats' => array_map(
                static fn (CatalogTemplateStat $stat): array => [
                    'id' => (int) $stat->id(),
                    'name' => $stat->name(),
                ],
                $entityManager->getRepository(CatalogTemplateStat::class)->findBy(['template' => $template], ['name' => 'ASC']),
            ),
            'elements' => array_map(
                fn (CatalogTemplateElement $element): array => [
                    'id' => (int) $element->id(),
                    'name' => $element->name(),
                    ...$this->emojiPayload($element->emojiSource(), $element->emojiUnicode(), $element->emojiId(), $element->emojiName(), $element->emojiAnimated()),
                ],
                $entityManager->getRepository(CatalogTemplateElement::class)->findBy(['template' => $template], ['name' => 'ASC']),
            ),
        ];
    }

    /**
     * @return list<array{id: string, label: string, description: string, icon: string, count: int}>
     */
    private function templateSections(array $catalog): array
    {
        $sections = [];

        foreach (self::TEMPLATE_SECTIONS as $id => $section) {
            $sections[] = [
                'id' => $id,
                'label' => $section['label'],
                'description' => $section['description'],
                'icon' => $section['icon'],
                'count' => \count($catalog[$section['catalog_key']]),
            ];
        }

        return $sections;
    }

    /**
     * @return array{id: string, label: string, description: string, icon: string, count: int}
     */
    private function templateSectionPayload(array $catalog, string $id): array
    {
        $section = self::TEMPLATE_SECTIONS[$id];

        return [
            'id' => $id,
            'label' => $section['label'],
            'description' => $section['description'],
            'icon' => $section['icon'],
            'count' => \count($catalog[$section['catalog_key']]),
        ];
    }

    /**
     * @return list<array{stat_id: int, stat_name: string, percentage: int}>
     */
    private function templateRankStatsPayload(EntityManagerInterface $entityManager, CatalogTemplateRank $rank): array
    {
        return array_map(
            static fn (CatalogTemplateRankStat $rankStat): array => [
                'stat_id' => (int) $rankStat->stat()->id(),
                'stat_name' => $rankStat->stat()->name(),
                'percentage' => $rankStat->percentage(),
            ],
            $entityManager->getRepository(CatalogTemplateRankStat::class)->findBy(['rank' => $rank], ['percentage' => 'ASC']),
        );
    }

    /**
     * @return list<array{rank_id: int, rank_name: string, stat_id: int, stat_name: string, percentage: int}>
     */
    private function templateRankStatsListPayload(EntityManagerInterface $entityManager, CatalogTemplate $template): array
    {
        return array_map(
            static fn (CatalogTemplateRankStat $rankStat): array => [
                'rank_id' => (int) $rankStat->rank()->id(),
                'rank_name' => $rankStat->rank()->name(),
                'stat_id' => (int) $rankStat->stat()->id(),
                'stat_name' => $rankStat->stat()->name(),
                'percentage' => $rankStat->percentage(),
            ],
            $this->templateRankStats($entityManager, $template),
        );
    }

    /**
     * @return list<CatalogTemplateRankStat>
     */
    private function templateRankStats(EntityManagerInterface $entityManager, CatalogTemplate $template): array
    {
        return $entityManager->createQueryBuilder()
            ->select('rankStat')
            ->from(CatalogTemplateRankStat::class, 'rankStat')
            ->innerJoin('rankStat.rank', 'rankEntity')
            ->innerJoin('rankStat.stat', 'statEntity')
            ->andWhere('rankEntity.template = :template')
            ->andWhere('statEntity.template = :template')
            ->setParameter('template', $template)
            ->orderBy('rankEntity.percentage', 'ASC')
            ->addOrderBy('rankEntity.name', 'ASC')
            ->addOrderBy('rankStat.percentage', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<array{id: int, message: string}>
     */
    private function templateWelcomeMessagesPayload(EntityManagerInterface $entityManager, CatalogTemplate $template, CatalogTemplateRank $rank): array
    {
        return array_map(
            static fn (CatalogTemplateWelcomeMessage $message): array => [
                'id' => (int) $message->id(),
                'message' => $message->message(),
            ],
            $entityManager->getRepository(CatalogTemplateWelcomeMessage::class)->findBy(['template' => $template, 'rank' => $rank], ['id' => 'ASC']),
        );
    }

    /**
     * @return list<array{id: int, rank_id: int, rank_name: string, message: string}>
     */
    private function templateWelcomeMessagesListPayload(EntityManagerInterface $entityManager, CatalogTemplate $template): array
    {
        return array_map(
            static fn (CatalogTemplateWelcomeMessage $message): array => [
                'id' => (int) $message->id(),
                'rank_id' => (int) $message->rank()->id(),
                'rank_name' => $message->rank()->name(),
                'message' => $message->message(),
            ],
            $entityManager->getRepository(CatalogTemplateWelcomeMessage::class)->findBy(['template' => $template], ['id' => 'ASC']),
        );
    }

    /**
     * @return list<array{id: int, message: string}>
     */
    private function templateByeMessagesPayload(EntityManagerInterface $entityManager, CatalogTemplate $template, CatalogTemplateRank $rank): array
    {
        return array_map(
            static fn (CatalogTemplateByeMessage $message): array => [
                'id' => (int) $message->id(),
                'message' => $message->message(),
            ],
            $entityManager->getRepository(CatalogTemplateByeMessage::class)->findBy(['template' => $template, 'rank' => $rank], ['id' => 'ASC']),
        );
    }

    /**
     * @return list<array{id: int, rank_id: int, rank_name: string, message: string}>
     */
    private function templateByeMessagesListPayload(EntityManagerInterface $entityManager, CatalogTemplate $template): array
    {
        return array_map(
            static fn (CatalogTemplateByeMessage $message): array => [
                'id' => (int) $message->id(),
                'rank_id' => (int) $message->rank()->id(),
                'rank_name' => $message->rank()->name(),
                'message' => $message->message(),
            ],
            $entityManager->getRepository(CatalogTemplateByeMessage::class)->findBy(['template' => $template], ['id' => 'ASC']),
        );
    }

    /**
     * @return array{emoji_source: string, emoji_source_label: string, emoji_unicode: ?string, emoji_id: ?string, emoji_name: ?string, emoji_animated: bool, emoji_markup: string, emoji_cdn_url: ?string}
     */
    private function emojiPayload(string $source, ?string $unicode, ?string $id, ?string $name, bool $animated): array
    {
        $markup = null !== $id && null !== $name
            ? sprintf('<%s:%s:%s>', $animated ? 'a' : '', $name, $id)
            : ($unicode ?? CatalogTemplateRole::DEFAULT_EMOJI);

        return [
            'emoji_source' => $source,
            'emoji_source_label' => self::EMOJI_SOURCE_LABELS[$source] ?? self::EMOJI_SOURCE_LABELS['unicode'],
            'emoji_unicode' => $unicode,
            'emoji_id' => $id,
            'emoji_name' => $name,
            'emoji_animated' => $animated,
            'emoji_markup' => $markup,
            'emoji_cdn_url' => null === $id ? null : sprintf(
                'https://cdn.discordapp.com/emojis/%s.%s?size=64&quality=lossless',
                $id,
                $animated ? 'gif' : 'webp',
            ),
        ];
    }

    /**
     * @return array{
     *     standard: list<array{source: string, value: string, name: string, markup: string, cdn_url: ?string, discord_id: ?string}>,
     *     bot: list<array{source: string, value: string, name: string, markup: string, cdn_url: ?string, discord_id: ?string}>,
     *     server: array{}
     * }
     */
    private function emojiPickerPayload(EntityManagerInterface $entityManager): array
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
            'server' => [],
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
