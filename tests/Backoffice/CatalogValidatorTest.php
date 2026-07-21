<?php

namespace App\Tests\Backoffice;

use App\Backoffice\CatalogValidator;
use App\Entity\ByeMessage;
use App\Entity\CatalogTemplate;
use App\Entity\CatalogTemplateElement;
use App\Entity\CatalogTemplateRank;
use App\Entity\CatalogTemplateRole;
use App\Entity\CatalogTemplateStat;
use App\Entity\CharacterRole;
use App\Entity\DiscordServer;
use App\Entity\Element;
use App\Entity\Rank;
use App\Entity\Stat;
use App\Entity\WelcomeMessage;
use App\Tests\Support\DatabaseResetter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class CatalogValidatorTest extends KernelTestCase
{
    use DatabaseResetter;

    private EntityManagerInterface $entityManager;
    private CatalogValidator $validator;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $this->validator = new CatalogValidator($this->entityManager);
        $this->resetDatabase();
    }

    public function testEmptyServerCatalogueReportsBlockingErrorsAndWarnings(): void
    {
        $server = new DiscordServer('server-1', 'Serveur vide');
        $this->entityManager->persist($server);
        $this->entityManager->flush();

        self::assertSame([
            'ready' => false,
            'errors' => ['missing_non_staff_rank', 'empty_roles', 'empty_elements'],
            'warnings' => ['empty_stats', 'missing_welcome_channel', 'missing_bye_channel', 'empty_welcome_messages', 'empty_bye_messages'],
        ], $this->validator->validateServer($server)->toArray());
    }

    public function testInvalidWeightsAreBlocking(): void
    {
        $server = new DiscordServer('server-1', 'Serveur invalide');
        $rank = new Rank($server, 'rank-1', 'Novice', 0);
        $this->entityManager->persist($server);
        $this->entityManager->persist($rank);
        $this->entityManager->persist(new CharacterRole($server, 'Gardien', 0));
        $this->entityManager->persist(new Element($server, 'Ambre'));
        $this->entityManager->flush();

        self::assertSame(
            ['zero_rank_weight', 'zero_role_weight'],
            $this->validator->validateServer($server)->toArray()['errors'],
        );
    }

    public function testCompleteServerIsReadyWhileOptionalStaffMismatchIsAWarning(): void
    {
        $server = new DiscordServer('server-1', 'Serveur prêt');
        $server->updateSettings('welcome-channel', 'bye-channel', 'staff-role');
        $rank = new Rank($server, 'rank-1', 'Novice', 100);
        $this->entityManager->persist($server);
        $this->entityManager->persist($rank);
        $this->entityManager->persist(new CharacterRole($server, 'Gardien', 100));
        $this->entityManager->persist(new Element($server, 'Ambre'));
        $this->entityManager->persist(new Stat($server, 'Éther'));
        $this->entityManager->persist(new WelcomeMessage($server, $rank, 'Bienvenue.'));
        $this->entityManager->persist(new ByeMessage($server, $rank, 'À bientôt.'));
        $this->entityManager->flush();

        self::assertSame([
            'ready' => true,
            'errors' => [],
            'warnings' => ['staff_role_without_staff_rank'],
        ], $this->validator->validateServer($server)->toArray());
    }

    public function testTemplateValidationUsesTheSameBlockingCatalogueRules(): void
    {
        $template = new CatalogTemplate('Modèle prêt');
        $this->entityManager->persist($template);
        $this->entityManager->persist(new CatalogTemplateRank($template, 'novice', 'Novice', 100));
        $this->entityManager->persist(new CatalogTemplateRole($template, 'Gardien', 100));
        $this->entityManager->persist(new CatalogTemplateElement($template, 'Ambre'));
        $this->entityManager->persist(new CatalogTemplateStat($template, 'Éther'));
        $this->entityManager->flush();

        self::assertSame([
            'ready' => true,
            'errors' => [],
            'warnings' => ['empty_welcome_messages', 'empty_bye_messages'],
        ], $this->validator->validateTemplate($template)->toArray());
    }
}
