<?php

declare(strict_types=1);

namespace App\Tests\Backoffice;

use App\Backoffice\CatalogValidator;
use App\Entity\ByeMessage;
use App\Entity\CatalogTemplate;
use App\Entity\CatalogTemplateElement;
use App\Entity\CatalogTemplateRank;
use App\Entity\CatalogTemplateRankStat;
use App\Entity\CatalogTemplateRole;
use App\Entity\CatalogTemplateStat;
use App\Entity\CharacterRole;
use App\Entity\DiscordServer;
use App\Entity\Element;
use App\Entity\Rank;
use App\Entity\RankStat;
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
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
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

        self::assertSame([
            'invalid_rank_percentage_total',
            'invalid_role_percentage_total',
            'invalid_rank_stat_percentage_total',
        ], $this->validator->validateServer($server)->toArray()['errors']);
    }

    public function testStaffRankPercentageIsIncludedInCatalogueTotal(): void
    {
        $server = new DiscordServer('server-1', 'Serveur invalide');
        $rank = new Rank($server, 'rank-1', 'Novice', 100);
        $staffRank = new Rank($server, 'rank-staff', 'Staff', 50, staff: true);
        $stat = new Stat($server, 'Éther');
        $this->entityManager->persist($server);
        $this->entityManager->persist($rank);
        $this->entityManager->persist($staffRank);
        $this->entityManager->persist($stat);
        $this->entityManager->persist(new RankStat($rank, $stat, 100));
        $this->entityManager->persist(new RankStat($staffRank, $stat, 100));
        $this->entityManager->persist(new CharacterRole($server, 'Gardien', 100));
        $this->entityManager->persist(new Element($server, 'Ambre'));
        $this->entityManager->flush();

        self::assertContains(
            'invalid_rank_percentage_total',
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
        $stat = new Stat($server, 'Éther');
        $this->entityManager->persist($stat);
        $this->entityManager->persist(new RankStat($rank, $stat, 100));
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
        $rank = new CatalogTemplateRank($template, 'novice', 'Novice', 100);
        $stat = new CatalogTemplateStat($template, 'Éther');
        $this->entityManager->persist($template);
        $this->entityManager->persist($rank);
        $this->entityManager->persist(new CatalogTemplateRole($template, 'Gardien', 100));
        $this->entityManager->persist(new CatalogTemplateElement($template, 'Ambre'));
        $this->entityManager->persist($stat);
        $this->entityManager->persist(new CatalogTemplateRankStat($rank, $stat, 100));
        $this->entityManager->flush();

        self::assertSame([
            'ready' => true,
            'errors' => [],
            'warnings' => ['empty_welcome_messages', 'empty_bye_messages'],
        ], $this->validator->validateTemplate($template)->toArray());
    }
}
