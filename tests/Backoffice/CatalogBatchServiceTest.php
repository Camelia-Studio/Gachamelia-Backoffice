<?php

declare(strict_types=1);

namespace App\Tests\Backoffice;

use App\Backoffice\CatalogBatchService;
use App\Backoffice\CatalogBatchValidationException;
use App\Backoffice\Csv\CatalogCsvImportService;
use App\Backoffice\Csv\CatalogCsvParser;
use App\Backoffice\Csv\CatalogCsvSection;
use App\Entity\CatalogTemplate;
use App\Entity\CatalogTemplateRank;
use App\Entity\CatalogTemplateRole;
use App\Entity\CatalogTemplateStat;
use App\Entity\CharacterRole;
use App\Entity\DiscordServer;
use App\Entity\Rank;
use App\Entity\Stat;
use App\Tests\Support\DatabaseResetter;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class CatalogBatchServiceTest extends KernelTestCase
{
    use DatabaseResetter;

    private EntityManagerInterface $entityManager;

    #[DataProvider('provideOneInvalidRowBlocksTheEntireBatchForEveryTargetCases')]
    public function testOneInvalidRowBlocksTheEntireBatchForEveryTarget(CatalogCsvSection $section): void
    {
        self::bootKernel();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->resetDatabase();
        $batch = self::getContainer()->get(CatalogBatchService::class);
        $catalogue = self::getContainer()->get(CatalogCsvImportService::class);
        foreach ([$this->server(), $this->template()] as $target) {
            $records = match ($section) {
                CatalogCsvSection::Ranks => [['nom' => 'Nouveau 1', 'pourcentage' => '10', 'discord_id' => 'discord-1', 'role_key' => 'cle-1'], ['nom' => 'Nouveau 2', 'pourcentage' => '20', 'discord_id' => 'discord-2', 'role_key' => 'cle-2']],
                CatalogCsvSection::Roles => [['nom' => 'Oracle', 'pourcentage' => '10', 'emoji' => '🔮'], ['nom' => 'Guerrier', 'pourcentage' => '20', 'emoji' => '<:epee:12345678901234567>', 'emoji_source' => 'bot']],
                CatalogCsvSection::Stats => [['nom' => 'Force'], ['nom' => 'Agilité']],
                CatalogCsvSection::Elements => [['nom' => 'Feu', 'emoji' => '🔥'], ['nom' => 'Eau', 'emoji' => '<a:eau:12345678901234567>', 'emoji_source' => 'bot']],
                CatalogCsvSection::RoleStats => [['role' => 'Rôle initial', 'stat' => 'Stat initiale', 'pourcentage' => '10'], ['role' => 'Rôle initial', 'stat' => 'Autre stat', 'pourcentage' => '20']],
                CatalogCsvSection::WelcomeMessages, CatalogCsvSection::ByeMessages => [['rang' => 'Rang initial', 'message' => 'Message 1'], ['rang' => 'Rang initial', 'message' => 'Message 2']],
            };
            $before = \count($catalogue->rows($target, $section));
            $invalid = $records;
            $invalid[1][$section->requiredHeaders()[0]] = '';
            try {
                $batch->create($target, $section, $invalid, ['discord-1', 'discord-2']);
                self::fail('An invalid row must block every write.');
            } catch (CatalogBatchValidationException $exception) {
                self::assertContains(2, array_column($exception->errors, 'line'));
            }
            self::assertSame($before, \count($catalogue->rows($target, $section)));
            self::assertSame(2, $batch->create($target, $section, $records, ['discord-1', 'discord-2']));
            self::assertSame($before + 2, \count($catalogue->rows($target, $section)));
            if (\in_array($section, [CatalogCsvSection::Roles, CatalogCsvSection::Elements], true)) {
                self::assertContains($records[1]['emoji'], array_column($catalogue->rows($target, $section), 'emoji'));
            }
            try {
                $batch->create($target, $section, $records, ['discord-1', 'discord-2']);
                self::fail('Existing rows must not be silently overwritten.');
            } catch (CatalogBatchValidationException $exception) {
                self::assertContains('entry_exists', array_column($exception->errors, 'message'));
            }
            self::assertSame($before + 2, \count($catalogue->rows($target, $section)));
        }
    }

    public function testTemplateCustomEmojiKeepsItsSourceDuringCsvUpdate(): void
    {
        self::bootKernel();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->resetDatabase();
        $target = $this->template();
        self::getContainer()->get(CatalogBatchService::class)->create($target, CatalogCsvSection::Roles, [['nom' => 'Personnalisé', 'pourcentage' => '0', 'emoji' => '<a:custom:12345678901234567>', 'emoji_source' => 'bot']]);
        $document = self::getContainer()->get(CatalogCsvParser::class)->parseRows([['nom' => 'Rôle initial', 'pourcentage' => '50', 'emoji' => '🎭'], ['nom' => 'Personnalisé', 'pourcentage' => '50', 'emoji' => '<a:custom:12345678901234567>']], CatalogCsvSection::Roles);
        $service = self::getContainer()->get(CatalogCsvImportService::class);
        $preview = $service->preview($target, CatalogCsvSection::Roles, $document);
        self::assertTrue($preview->valid());
        $service->apply($target, CatalogCsvSection::Roles, $document, $preview->fingerprint());
        $role = $this->entityManager->getRepository(CatalogTemplateRole::class)->findOneBy(['template' => $target, 'name' => 'Personnalisé']);
        self::assertInstanceOf(CatalogTemplateRole::class, $role);
        self::assertSame('bot', $role->emojiSource());
        self::assertTrue($role->emojiAnimated());
        self::assertSame(50, $role->percentage());
    }

    public function testDatabaseFailureRollsBackEarlierRowsInTheSameBatch(): void
    {
        self::bootKernel();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->resetDatabase();
        $server = $this->server();
        $this->entityManager->persist(new Stat($server, 'Éther'));
        $this->entityManager->flush();
        $before = (int) $this->connection()->fetchOne('SELECT COUNT(*) FROM stats');
        try {
            self::getContainer()->get(CatalogBatchService::class)->create($server, CatalogCsvSection::Stats, [['nom' => 'Annulé'], ['nom' => 'Ether']]);
            self::fail('The database collation must reject this duplicate.');
        } catch (UniqueConstraintViolationException) {
            self::assertSame($before, (int) $this->connection()->fetchOne('SELECT COUNT(*) FROM stats'));
            self::assertSame(0, (int) $this->connection()->fetchOne("SELECT COUNT(*) FROM stats WHERE name = 'Annulé'"));
        }
    }

    public function testReferencesFromAnotherServerCannotBeUsed(): void
    {
        self::bootKernel();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->resetDatabase();
        $server = $this->server();
        $foreign = new DiscordServer('foreign', 'Autre serveur');
        $this->entityManager->persist($foreign);
        $this->entityManager->persist(new Rank($foreign, 'foreign-role', 'Rang étranger', 100));
        $this->entityManager->flush();
        try {
            self::getContainer()->get(CatalogBatchService::class)->create($server, CatalogCsvSection::WelcomeMessages, [['rang' => 'Rang initial', 'message' => 'Valide'], ['rang' => 'Rang étranger', 'message' => 'Interdit']]);
            self::fail('A reference must remain scoped to the target.');
        } catch (CatalogBatchValidationException $exception) {
            self::assertSame('rank_not_found', $exception->errors[0]['message']);
            self::assertSame(2, $exception->errors[0]['line']);
            self::assertSame(0, (int) $this->connection()->fetchOne('SELECT COUNT(*) FROM welcome_messages'));
        }
    }

    /**
     * @return iterable<string, array{CatalogCsvSection}>
     */
    public static function provideOneInvalidRowBlocksTheEntireBatchForEveryTargetCases(): iterable
    {
        foreach (CatalogCsvSection::cases() as $section) {
            yield $section->value => [$section];
        }
    }

    private function server(): DiscordServer
    {
        $target = new DiscordServer('batch-guild', 'Lot');
        foreach ([$target, new Rank($target, 'initial-role', 'Rang initial', 100), new CharacterRole($target, 'Rôle initial', 100), new Stat($target, 'Stat initiale'), new Stat($target, 'Autre stat')] as $entity) {
            $this->entityManager->persist($entity);
        }
        $this->entityManager->flush();

        return $target;
    }

    private function template(): CatalogTemplate
    {
        $target = new CatalogTemplate('Lot modèle');
        foreach ([$target, new CatalogTemplateRank($target, 'initial', 'Rang initial', 100), new CatalogTemplateRole($target, 'Rôle initial', 100), new CatalogTemplateStat($target, 'Stat initiale'), new CatalogTemplateStat($target, 'Autre stat')] as $entity) {
            $this->entityManager->persist($entity);
        }
        $this->entityManager->flush();

        return $target;
    }
}
