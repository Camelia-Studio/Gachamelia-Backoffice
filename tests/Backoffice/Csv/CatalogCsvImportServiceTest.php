<?php

declare(strict_types=1);

namespace App\Tests\Backoffice\Csv;

use App\Backoffice\Csv\CatalogCsvDocument;
use App\Backoffice\Csv\CatalogCsvImportResult;
use App\Backoffice\Csv\CatalogCsvImportService;
use App\Backoffice\Csv\CatalogCsvSection;
use App\Backoffice\Csv\ServerCatalogCsvTargetAdapter;
use App\Backoffice\Csv\TemplateCatalogCsvTargetAdapter;
use App\Entity\CatalogTemplate;
use App\Entity\CatalogTemplateByeMessage;
use App\Entity\CatalogTemplateElement;
use App\Entity\CatalogTemplateRank;
use App\Entity\CatalogTemplateRoleStat;
use App\Entity\CatalogTemplateRole;
use App\Entity\CatalogTemplateStat;
use App\Entity\CatalogTemplateWelcomeMessage;
use App\Entity\CharacterRole;
use App\Entity\DiscordServer;
use App\Entity\Element;
use App\Entity\Rank;
use App\Entity\RoleStat;
use App\Entity\Stat;
use App\Entity\WelcomeMessage;
use App\Tests\Support\DatabaseResetter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class CatalogCsvImportServiceTest extends KernelTestCase
{
    use DatabaseResetter;

    private EntityManagerInterface $entityManager;
    private CatalogCsvImportService $service;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->resetDatabase();
        $this->service = new CatalogCsvImportService(
            $this->entityManager,
            new ServerCatalogCsvTargetAdapter($this->entityManager),
            new TemplateCatalogCsvTargetAdapter($this->entityManager),
        );
    }

    public function testMergesEveryServerSectionWithoutDeletingAbsentRows(): void
    {
        $server = new DiscordServer('guild-1', 'Serveur');
        $novice = new Rank($server, 'discord-novice', 'Novice', 60);
        $staff = new Rank($server, 'discord-staff', 'Staff', 40, staff: true);
        $force = new Stat($server, 'Force');
        $guerrier = new CharacterRole($server, 'Guerrier', 100, emojiUnicode: '⚔️');
        $this->entityManager->persist($server);
        foreach ([$novice, $staff, $force] as $entity) {
            $this->entityManager->persist($entity);
        }
        $this->entityManager->persist(new RoleStat($guerrier, $force, 100));
        $this->entityManager->persist($guerrier);
        $this->entityManager->persist(new Element($server, 'Feu', emojiUnicode: '🔥'));
        $this->entityManager->persist(new WelcomeMessage($server, $novice, 'Bienvenue.'));
        $this->entityManager->flush();

        $rankDocument = $this->document(CatalogCsvSection::Ranks, [
            ['nom' => 'novice', 'pourcentage' => 50, 'titre_depart' => null, 'est_staff' => false],
            ['nom' => 'Staff', 'pourcentage' => 30, 'titre_depart' => 'Départ staff', 'est_staff' => true],
            ['nom' => 'Gardien', 'pourcentage' => 20, 'titre_depart' => null, 'est_staff' => false],
        ]);
        $rankPreview = $this->service->preview($server, CatalogCsvSection::Ranks, $rankDocument);
        self::assertTrue($rankPreview->valid());
        self::assertSame(['creates' => 1, 'updates' => 2, 'unchanged' => 0], $rankPreview->counts());
        self::assertSame([4], $rankPreview->discordRoleLines());
        $rankResult = $this->service->apply(
            $server,
            CatalogCsvSection::Ranks,
            $rankDocument,
            $rankPreview->fingerprint(),
            [4 => 'discord-gardien'],
        );
        self::assertSame(['creates' => 1, 'updates' => 2, 'unchanged' => 0], $rankResult->counts());

        $this->apply($server, CatalogCsvSection::Stats, [
            ['nom' => ' force '],
            ['nom' => 'Agilité'],
        ]);
        $this->apply($server, CatalogCsvSection::Roles, [
            ['nom' => 'Guerrier', 'pourcentage' => 70, 'emoji' => '🗡️'],
            ['nom' => 'Oracle', 'pourcentage' => 30, 'emoji' => '🔮'],
        ]);
        $this->apply($server, CatalogCsvSection::RoleStats, [
            ['role' => 'Guerrier', 'stat' => 'Force', 'pourcentage' => 50],
            ['role' => 'Guerrier', 'stat' => 'Agilité', 'pourcentage' => 50],
            ['role' => 'Oracle', 'stat' => 'Force', 'pourcentage' => 100],
        ]);
        $this->apply($server, CatalogCsvSection::Elements, [
            ['nom' => 'Feu', 'emoji' => null],
            ['nom' => 'Eau', 'emoji' => '💧'],
        ]);
        $welcomeResult = $this->apply($server, CatalogCsvSection::WelcomeMessages, [
            ['rang' => 'Novice', 'message' => 'Bienvenue.'],
            ['rang' => 'Novice', 'message' => 'Bienvenue encore.'],
        ]);
        $this->apply($server, CatalogCsvSection::ByeMessages, [
            ['rang' => 'Staff', 'message' => 'À bientôt.'],
        ]);

        self::assertSame(['creates' => 1, 'updates' => 0, 'unchanged' => 1], $welcomeResult->counts());
        self::assertSame(3, (int) $this->connection()->fetchOne('SELECT COUNT(*) FROM ranks WHERE server_id = ?', [$server->id()]));
        self::assertSame('discord-novice', $this->connection()->fetchOne('SELECT discord_id FROM ranks WHERE server_id = ? AND name = ?', [$server->id(), 'novice']));
        self::assertSame('discord-gardien', $this->connection()->fetchOne('SELECT discord_id FROM ranks WHERE server_id = ? AND name = ?', [$server->id(), 'Gardien']));
        self::assertSame(2, (int) $this->connection()->fetchOne('SELECT COUNT(*) FROM stats WHERE server_id = ?', [$server->id()]));
        self::assertSame(3, (int) $this->connection()->fetchOne('SELECT COUNT(*) FROM role_stats WHERE server_id = ?', [$server->id()]));
        self::assertSame(2, (int) $this->connection()->fetchOne('SELECT COUNT(*) FROM roles WHERE server_id = ?', [$server->id()]));
        self::assertSame(2, (int) $this->connection()->fetchOne('SELECT COUNT(*) FROM elements WHERE server_id = ?', [$server->id()]));
        self::assertSame(Element::DEFAULT_EMOJI, $this->connection()->fetchOne('SELECT emoji_unicode FROM elements WHERE server_id = ? AND name = ?', [$server->id(), 'Feu']));
        self::assertSame(2, (int) $this->connection()->fetchOne('SELECT COUNT(*) FROM welcome_messages WHERE server_id = ?', [$server->id()]));
        self::assertSame(1, (int) $this->connection()->fetchOne('SELECT COUNT(*) FROM bye_messages WHERE server_id = ?', [$server->id()]));
    }

    public function testMergesEveryTemplateSectionAndGeneratesStableRoleKey(): void
    {
        $template = new CatalogTemplate('Starter');
        $novice = new CatalogTemplateRank($template, 'novice-key', 'Novice', 100);
        $force = new CatalogTemplateStat($template, 'Force');
        $guerrier = new CatalogTemplateRole($template, 'Guerrier', 100, emojiUnicode: '⚔️');
        $this->entityManager->persist($template);
        $this->entityManager->persist($novice);
        $this->entityManager->persist($force);
        $this->entityManager->persist(new CatalogTemplateRoleStat($guerrier, $force, 100));
        $this->entityManager->persist($guerrier);
        $this->entityManager->persist(new CatalogTemplateElement($template, 'Feu', emojiUnicode: '🔥'));
        $this->entityManager->persist(new CatalogTemplateWelcomeMessage($template, $novice, 'Bienvenue.'));
        $this->entityManager->persist(new CatalogTemplateByeMessage($template, $novice, 'À bientôt.'));
        $this->entityManager->flush();

        $this->apply($template, CatalogCsvSection::Ranks, [
            ['nom' => 'Novice', 'pourcentage' => 70, 'titre_depart' => null, 'est_staff' => false],
            ['nom' => 'Gardien céleste', 'pourcentage' => 30, 'titre_depart' => 'Départ', 'est_staff' => true],
        ]);
        $this->apply($template, CatalogCsvSection::Stats, [
            ['nom' => 'Force'],
            ['nom' => 'Agilité'],
        ]);
        $this->apply($template, CatalogCsvSection::Roles, [
            ['nom' => 'Guerrier', 'pourcentage' => 60, 'emoji' => '🗡️'],
            ['nom' => 'Oracle', 'pourcentage' => 40, 'emoji' => '🔮'],
        ]);
        $this->apply($template, CatalogCsvSection::RoleStats, [
            ['role' => 'Oracle', 'stat' => 'Force', 'pourcentage' => 100],
        ]);
        $this->apply($template, CatalogCsvSection::Elements, [
            ['nom' => 'Feu', 'emoji' => '🔥'],
            ['nom' => 'Eau', 'emoji' => '💧'],
        ]);
        $this->apply($template, CatalogCsvSection::WelcomeMessages, [
            ['rang' => 'Novice', 'message' => 'Bienvenue.'],
            ['rang' => 'Gardien céleste', 'message' => 'Bienvenue au staff.'],
        ]);
        $this->apply($template, CatalogCsvSection::ByeMessages, [
            ['rang' => 'Novice', 'message' => 'À bientôt.'],
            ['rang' => 'Gardien céleste', 'message' => 'Au revoir staff.'],
        ]);

        self::assertSame('novice-key', $this->connection()->fetchOne('SELECT role_key FROM catalog_template_ranks WHERE template_id = ? AND name = ?', [$template->id(), 'Novice']));
        self::assertSame('gardien-celeste', $this->connection()->fetchOne('SELECT role_key FROM catalog_template_ranks WHERE template_id = ? AND name = ?', [$template->id(), 'Gardien céleste']));
        self::assertSame(2, (int) $this->connection()->fetchOne('SELECT COUNT(*) FROM catalog_template_ranks WHERE template_id = ?', [$template->id()]));
        self::assertSame(2, (int) $this->connection()->fetchOne('SELECT COUNT(*) FROM catalog_template_roles WHERE template_id = ?', [$template->id()]));
        self::assertSame(2, (int) $this->connection()->fetchOne('SELECT COUNT(*) FROM catalog_template_stats WHERE template_id = ?', [$template->id()]));
        self::assertSame(2, (int) $this->connection()->fetchOne('SELECT COUNT(*) FROM catalog_template_elements WHERE template_id = ?', [$template->id()]));
        self::assertSame(2, (int) $this->connection()->fetchOne('SELECT COUNT(*) FROM catalog_template_welcome_messages WHERE template_id = ?', [$template->id()]));
        self::assertSame(2, (int) $this->connection()->fetchOne('SELECT COUNT(*) FROM catalog_template_bye_messages WHERE template_id = ?', [$template->id()]));
    }

    public function testRejectsInvalidTotalsReferencesMappingsAndStalePreviewWithoutWriting(): void
    {
        $server = new DiscordServer('guild-1', 'Serveur');
        $otherServer = new DiscordServer('guild-2', 'Autre');
        $rank = new Rank($server, 'discord-novice', 'Novice', 100);
        $otherRank = new Rank($otherServer, 'discord-other', 'Externe', 100);
        $this->entityManager->persist($server);
        $this->entityManager->persist($otherServer);
        $this->entityManager->persist($rank);
        $this->entityManager->persist($otherRank);
        $this->entityManager->persist(new CharacterRole($server, 'Guerrier', 100));
        $this->entityManager->persist(new Element($server, 'Feu'));
        $this->entityManager->persist(new Stat($otherServer, 'Externe'));
        $this->entityManager->flush();

        $invalidTotal = $this->service->preview($server, CatalogCsvSection::Roles, $this->document(CatalogCsvSection::Roles, [
            ['nom' => 'Guerrier', 'pourcentage' => 80, 'emoji' => '⚔️'],
        ]));
        self::assertFalse($invalidTotal->valid());
        self::assertSame('invalid_role_percentage_total', $invalidTotal->errors()[0]['message']);

        $missingReference = $this->service->preview($server, CatalogCsvSection::RoleStats, $this->document(CatalogCsvSection::RoleStats, [
            ['role' => 'Externe', 'stat' => 'Externe', 'pourcentage' => 100],
        ]));
        self::assertFalse($missingReference->valid());
        self::assertSame('role_not_found', $missingReference->errors()[0]['message']);

        $ranks = $this->document(CatalogCsvSection::Ranks, [
            ['nom' => 'Novice', 'pourcentage' => 50, 'titre_depart' => null, 'est_staff' => false],
            ['nom' => 'Gardien', 'pourcentage' => 50, 'titre_depart' => null, 'est_staff' => false],
        ]);
        $rankPreview = $this->service->preview($server, CatalogCsvSection::Ranks, $ranks);
        try {
            $this->service->apply($server, CatalogCsvSection::Ranks, $ranks, $rankPreview->fingerprint(), []);
            self::fail('Missing Discord role mapping should reject the import.');
        } catch (\InvalidArgumentException $exception) {
            self::assertStringContainsString('discord_role_mapping_required', $exception->getMessage());
        }
        self::assertSame(1, (int) $this->connection()->fetchOne('SELECT COUNT(*) FROM ranks WHERE server_id = ?', [$server->id()]));

        $stats = $this->document(CatalogCsvSection::Stats, [['nom' => 'Force']]);
        $statsPreview = $this->service->preview($server, CatalogCsvSection::Stats, $stats);
        $this->entityManager->persist(new Stat($server, 'Agilité'));
        $this->entityManager->flush();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('catalog_changed');
        $this->service->apply($server, CatalogCsvSection::Stats, $stats, $statsPreview->fingerprint());
    }

    /**
     * @param list<array<string, string|int|bool|null>> $rows
     * @param array<int, string>                        $discordRoleIdsByLine
     */
    private function apply(
        DiscordServer|CatalogTemplate $target,
        CatalogCsvSection $section,
        array $rows,
        array $discordRoleIdsByLine = [],
    ): CatalogCsvImportResult {
        $document = $this->document($section, $rows);
        $preview = $this->service->preview($target, $section, $document);
        self::assertTrue($preview->valid(), json_encode($preview->errors(), JSON_THROW_ON_ERROR));

        return $this->service->apply(
            $target,
            $section,
            $document,
            $preview->fingerprint(),
            $discordRoleIdsByLine,
        );
    }

    /**
     * @param list<array<string, string|int|bool|null>> $values
     */
    private function document(CatalogCsvSection $section, array $values): CatalogCsvDocument
    {
        $rows = [];
        foreach ($values as $index => $row) {
            $rows[] = [
                'line' => $index + 2,
                'key' => $section->naturalKey($row),
                'values' => $row,
            ];
        }

        return new CatalogCsvDocument($rows, []);
    }
}
