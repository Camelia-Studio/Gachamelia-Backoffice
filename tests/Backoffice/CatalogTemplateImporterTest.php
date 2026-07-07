<?php

namespace App\Tests\Backoffice;

use App\Backoffice\CatalogTemplateImporter;
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
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class CatalogTemplateImporterTest extends KernelTestCase
{
    use DatabaseResetter;

    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
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
        $rank = new CatalogTemplateRank($template, 'Comète', 'Comète de l’Aube', 35, 'Comète filante', true);
        $stat = new CatalogTemplateStat($template, 'Éther');
        $role = new CatalogTemplateRole($template, 'Gardien', 45, 'unicode', '🛡️');
        $element = new CatalogTemplateElement($template, 'Ambre', 'unicode', '🟠');
        $this->entityManager->persist($template);
        $this->entityManager->persist($rank);
        $this->entityManager->persist($stat);
        $this->entityManager->persist($role);
        $this->entityManager->persist($element);
        $this->entityManager->persist(new CatalogTemplateRankStat($rank, $stat, 80));
        $this->entityManager->persist(new CatalogTemplateWelcomeMessage($template, $rank, 'Bienvenue, {user}.'));
        $this->entityManager->persist(new CatalogTemplateByeMessage($template, $rank, 'Au revoir, {user}.'));
        $this->entityManager->flush();

        $this->importer()->import(
            $server,
            $template,
            [(string) $rank->id() => '777777777777777777'],
        );

        self::assertSame([
            'discord_id' => '777777777777777777',
            'name' => 'Comète de l’Aube',
            'percentage' => 35,
            'bye_title' => 'Comète filante',
            'is_staff' => 1,
        ], $this->connection()->fetchAssociative('SELECT discord_id, name, percentage, bye_title, is_staff FROM ranks WHERE server_id = ?', [$server->id()]));
        self::assertSame('Gardien', $this->connection()->fetchOne('SELECT name FROM roles WHERE server_id = ?', [$server->id()]));
        self::assertSame('Éther', $this->connection()->fetchOne('SELECT name FROM stats WHERE server_id = ?', [$server->id()]));
        self::assertSame('Ambre', $this->connection()->fetchOne('SELECT name FROM elements WHERE server_id = ?', [$server->id()]));
        self::assertSame(80, (int) $this->connection()->fetchOne('SELECT percentage FROM rank_stats'));
        self::assertSame('Bienvenue, {user}.', $this->connection()->fetchOne('SELECT message FROM welcome_messages'));
        self::assertSame('Au revoir, {user}.', $this->connection()->fetchOne('SELECT message FROM bye_messages'));
        self::assertSame(0, (int) $this->connection()->fetchOne('SELECT COUNT(*) FROM ranks WHERE name = ?', ['Ancien rang']));
        self::assertSame(0, (int) $this->connection()->fetchOne('SELECT COUNT(*) FROM stats WHERE name = ?', ['Ancienne stat']));
    }

    public function testItRejectsMissingDiscordRoleMapping(): void
    {
        $server = new DiscordServer('server-1', 'Serveur Test');
        $template = new CatalogTemplate('Starter Gacha');
        $rank = new CatalogTemplateRank($template, 'Comète', 'Comète de l’Aube', 35);
        $this->entityManager->persist($server);
        $this->entityManager->persist($template);
        $this->entityManager->persist($rank);
        $this->entityManager->flush();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing Discord role mapping for rank Comète de l’Aube.');

        $this->importer()->import($server, $template, []);
    }

    private function importer(): CatalogTemplateImporter
    {
        return new CatalogTemplateImporter($this->entityManager, $this->connection());
    }
}
