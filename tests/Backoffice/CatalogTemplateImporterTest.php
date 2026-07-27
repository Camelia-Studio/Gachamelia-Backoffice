<?php

declare(strict_types=1);

namespace App\Tests\Backoffice;

use App\Backoffice\CatalogTemplateImporter;
use App\Backoffice\CatalogValidator;
use App\Entity\CatalogTemplate;
use App\Entity\CatalogTemplateByeMessage;
use App\Entity\CatalogTemplateElement;
use App\Entity\CatalogTemplateRank;
use App\Entity\CatalogTemplateRankStat;
use App\Entity\CatalogTemplateRole;
use App\Entity\CatalogTemplateStat;
use App\Entity\CatalogTemplateWelcomeMessage;
use App\Entity\DiscordServer;
use App\Entity\Rank;
use App\Entity\Stat;
use App\Tests\Support\DatabaseResetter;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class CatalogTemplateImporterTest extends KernelTestCase
{
    use DatabaseResetter;

    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->resetDatabase();
    }

    public function testItDestructivelyReplacesAServerCatalogFromACompleteTemplate(): void
    {
        $server = new DiscordServer('server-1', 'Serveur Test');
        $this->entityManager->persist($server);

        $oldRank = new Rank($server, '111111111111111111', 'Ancien rang', 100);
        $oldStat = new Stat($server, 'Ancienne stat');
        $this->entityManager->persist($oldRank);
        $this->entityManager->persist($oldStat);

        $template = new CatalogTemplate('Starter Gacha', 'Catalogue de départ.');
        $template->publish();
        $rank = new CatalogTemplateRank($template, 'Comète', 'Comète de l’Aube', 100, 'Comète filante');
        $stat = new CatalogTemplateStat($template, 'Éther');
        $role = new CatalogTemplateRole($template, 'Gardien', 100, 'unicode', '🛡️');
        $element = new CatalogTemplateElement($template, 'Ambre', 'unicode', '🟠');
        $this->entityManager->persist($template);
        $this->entityManager->persist($rank);
        $this->entityManager->persist($stat);
        $this->entityManager->persist($role);
        $this->entityManager->persist($element);
        $this->entityManager->persist(new CatalogTemplateRankStat($rank, $stat, 100));
        $this->entityManager->persist(new CatalogTemplateWelcomeMessage($template, $rank, 'Bienvenue, {user}.'));
        $this->entityManager->persist(new CatalogTemplateByeMessage($template, $rank, 'Au revoir, {user}.'));
        $this->entityManager->flush();

        $importer = $this->importer();
        $importer->import(
            $server,
            $template,
            [(string) $rank->id() => '777777777777777777'],
            $importer->preview($server, $template)['fingerprint'],
            ['777777777777777777'],
        );

        self::assertSame([
            'discord_id' => '777777777777777777',
            'name' => 'Comète de l’Aube',
            'percentage' => 100,
            'bye_title' => 'Comète filante',
            'is_staff' => 0,
        ], $this->connection()->fetchAssociative('SELECT discord_id, name, percentage, bye_title, is_staff FROM ranks WHERE server_id = ?', [$server->id()]));
        self::assertSame('Gardien', $this->connection()->fetchOne('SELECT name FROM roles WHERE server_id = ?', [$server->id()]));
        self::assertSame('Éther', $this->connection()->fetchOne('SELECT name FROM stats WHERE server_id = ?', [$server->id()]));
        self::assertSame('Ambre', $this->connection()->fetchOne('SELECT name FROM elements WHERE server_id = ?', [$server->id()]));
        self::assertSame(100, (int) $this->connection()->fetchOne('SELECT percentage FROM rank_stats'));
        self::assertSame('Bienvenue, {user}.', $this->connection()->fetchOne('SELECT message FROM welcome_messages'));
        self::assertSame('Au revoir, {user}.', $this->connection()->fetchOne('SELECT message FROM bye_messages'));
        self::assertSame(0, (int) $this->connection()->fetchOne('SELECT COUNT(*) FROM ranks WHERE name = ?', ['Ancien rang']));
        self::assertSame(0, (int) $this->connection()->fetchOne('SELECT COUNT(*) FROM stats WHERE name = ?', ['Ancienne stat']));
    }

    public function testItRejectsMissingDiscordRoleMapping(): void
    {
        $server = new DiscordServer('server-1', 'Serveur Test');
        $template = new CatalogTemplate('Starter Gacha');
        $template->publish();
        $rank = new CatalogTemplateRank($template, 'Comète', 'Comète de l’Aube', 100);
        $stat = new CatalogTemplateStat($template, 'Éther');
        $this->entityManager->persist($server);
        $this->entityManager->persist($template);
        $this->entityManager->persist($rank);
        $this->entityManager->persist($stat);
        $this->entityManager->persist(new CatalogTemplateRankStat($rank, $stat, 100));
        $this->entityManager->persist(new CatalogTemplateRole($template, 'Gardien', 100));
        $this->entityManager->persist(new CatalogTemplateElement($template, 'Ambre'));
        $this->entityManager->flush();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing Discord role mapping for rank Comète de l’Aube.');

        $importer = $this->importer();
        $importer->import($server, $template, [], $importer->preview($server, $template)['fingerprint'], ['777777777777777777']);
    }

    public function testItRejectsInvalidTemplateWithoutAlteringServerCatalog(): void
    {
        $server = new DiscordServer('server-1', 'Serveur Test');
        $oldRank = new Rank($server, 'old-rank', 'Ancien rang', 100);
        $template = new CatalogTemplate('Modèle invalide');
        $template->publish();
        $this->entityManager->persist($server);
        $this->entityManager->persist($oldRank);
        $this->entityManager->persist($template);
        $this->entityManager->flush();

        try {
            $this->importer()->import($server, $template, [], '', []);
            self::fail('The invalid template should have been rejected.');
        } catch (\InvalidArgumentException $exception) {
            self::assertSame('Catalog template is not ready for import.', $exception->getMessage());
        }

        self::assertSame(1, (int) $this->connection()->fetchOne('SELECT COUNT(*) FROM ranks WHERE server_id = ?', [$server->id()]));
        self::assertSame('Ancien rang', $this->connection()->fetchOne('SELECT name FROM ranks WHERE server_id = ?', [$server->id()]));
    }

    public function testPreviewFingerprintCoversTemplateAndTargetCatalogState(): void
    {
        $server = new DiscordServer('server-1', 'Serveur Test');
        $oldRank = new Rank($server, 'old-rank', 'Ancien rang', 100);
        $template = new CatalogTemplate('Starter Gacha');
        $template->publish();
        $templateRank = new CatalogTemplateRank($template, 'comete', 'Comète', 100);
        $templateStat = new CatalogTemplateStat($template, 'Éther');
        $this->entityManager->persist($server);
        $this->entityManager->persist($oldRank);
        $this->entityManager->persist($template);
        $this->entityManager->persist($templateRank);
        $this->entityManager->persist($templateStat);
        $this->entityManager->persist(new CatalogTemplateRankStat($templateRank, $templateStat, 100));
        $this->entityManager->persist(new CatalogTemplateRole($template, 'Gardien', 100));
        $this->entityManager->persist(new CatalogTemplateElement($template, 'Ambre'));
        $this->entityManager->flush();

        $importer = $this->importer();
        $initialFingerprint = $importer->preview($server, $template)['fingerprint'] ?? null;
        self::assertIsString($initialFingerprint);

        $oldRank->updateConfiguration('old-rank', 'Ancien rang modifié', 100, null, false);
        $this->entityManager->flush();
        $targetFingerprint = $importer->preview($server, $template)['fingerprint'] ?? null;
        self::assertIsString($targetFingerprint);
        self::assertNotSame($initialFingerprint, $targetFingerprint);

        $templateRank->updateConfiguration('comete', 'Comète modifiée', 100, null, false);
        $this->entityManager->flush();
        $templateFingerprint = $importer->preview($server, $template)['fingerprint'] ?? null;
        self::assertIsString($templateFingerprint);
        self::assertNotSame($targetFingerprint, $templateFingerprint);
    }

    public function testConcurrentTemplateChangeIsObservedBeforeImportMutation(): void
    {
        if (!\function_exists('pcntl_fork')) {
            self::markTestSkipped('The pcntl extension is required for this concurrency regression test.');
        }

        $server = new DiscordServer('server-1', 'Serveur Test');
        $oldRank = new Rank($server, 'old-rank', 'Ancien rang', 100);
        $template = new CatalogTemplate('Starter Gacha');
        $template->publish();
        $templateRank = new CatalogTemplateRank($template, 'comete', 'Comète', 100);
        $templateStat = new CatalogTemplateStat($template, 'Éther');
        $this->entityManager->persist($server);
        $this->entityManager->persist($oldRank);
        $this->entityManager->persist($template);
        $this->entityManager->persist($templateRank);
        $this->entityManager->persist($templateStat);
        $this->entityManager->persist(new CatalogTemplateRankStat($templateRank, $templateStat, 100));
        $this->entityManager->persist(new CatalogTemplateRole($template, 'Gardien', 100));
        $this->entityManager->persist(new CatalogTemplateElement($template, 'Ambre'));
        $this->entityManager->flush();

        $fingerprint = $this->importer()->preview($server, $template)['fingerprint'];
        $serverId = $server->id();
        $templateId = $template->id();
        $templateRankId = $templateRank->id();
        self::assertNotNull($serverId);
        self::assertNotNull($templateId);
        self::assertNotNull($templateRankId);

        $concurrentConnection = DriverManager::getConnection($this->connection()->getParams());
        $concurrentConnection->beginTransaction();
        $concurrentConnection->update(
            'catalog_template_ranks',
            ['name' => 'Comète modifiée en concurrence'],
            ['id' => $templateRankId],
        );

        self::assertSame(
            0,
            $this->concurrentImportExitCode(
                $concurrentConnection,
                $serverId,
                $templateId,
                $templateRankId,
                $fingerprint,
                'Le modèle ou le catalogue cible a changé depuis l’aperçu.',
            ),
            'The import did not wait for the concurrent catalog change before checking its fingerprint.',
        );
        self::assertSame('Ancien rang', $this->connection()->fetchOne('SELECT name FROM ranks WHERE server_id = ?', [$serverId]));

        $fingerprint = $this->importer()->preview($server, $template)['fingerprint'];
        $concurrentConnection = DriverManager::getConnection($this->connection()->getParams());
        $concurrentConnection->beginTransaction();
        $concurrentConnection->update('catalog_templates', ['published' => 0], ['id' => $templateId]);

        self::assertSame(
            0,
            $this->concurrentImportExitCode(
                $concurrentConnection,
                $serverId,
                $templateId,
                $templateRankId,
                $fingerprint,
                'Cannot import an unpublished catalog template.',
            ),
            'The import accepted a template unpublished concurrently after its preview.',
        );
        self::assertSame('Ancien rang', $this->connection()->fetchOne('SELECT name FROM ranks WHERE server_id = ?', [$serverId]));
    }

    private function concurrentImportExitCode(
        Connection $concurrentConnection,
        int $serverId,
        int $templateId,
        int $templateRankId,
        string $fingerprint,
        string $expectedMessage,
    ): int {
        $this->connection()->close();
        $pid = pcntl_fork();
        self::assertNotSame(-1, $pid, 'Unable to fork the concurrent import process.');
        if (0 === $pid) {
            self::ensureKernelShutdown();
            self::bootKernel();

            try {
                $entityManager = self::getContainer()->get(EntityManagerInterface::class);
                $childConnection = self::getContainer()->get(Connection::class);
                $childServer = $entityManager->find(DiscordServer::class, $serverId);
                $childTemplate = $entityManager->find(CatalogTemplate::class, $templateId);
                if (!$childServer instanceof DiscordServer || !$childTemplate instanceof CatalogTemplate) {
                    exit(12);
                }

                $importer = new CatalogTemplateImporter(
                    $entityManager,
                    $childConnection,
                    new CatalogValidator($entityManager),
                );
                $importer->import(
                    $childServer,
                    $childTemplate,
                    [(string) $templateRankId => '777777777777777777'],
                    $fingerprint,
                    ['777777777777777777'],
                );

                exit(10);
            } catch (\InvalidArgumentException $exception) {
                exit($expectedMessage === $exception->getMessage() ? 0 : 11);
            } catch (\Throwable) {
                exit(12);
            }
        }

        $status = 0;
        $finishedPid = 0;
        for ($attempt = 0; $attempt < 100 && 0 === $finishedPid; ++$attempt) {
            $finishedPid = pcntl_waitpid($pid, $status, WNOHANG);
            if (0 === $finishedPid) {
                usleep(20_000);
            }
        }

        $concurrentConnection->commit();
        $concurrentConnection->close();
        if (0 === $finishedPid) {
            pcntl_waitpid($pid, $status);
        }

        self::assertTrue(pcntl_wifexited($status), 'The concurrent import process did not exit normally.');

        return pcntl_wexitstatus($status);
    }

    private function importer(): CatalogTemplateImporter
    {
        return new CatalogTemplateImporter(
            $this->entityManager,
            $this->connection(),
            new CatalogValidator($this->entityManager),
        );
    }
}
