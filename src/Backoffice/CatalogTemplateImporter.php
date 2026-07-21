<?php

namespace App\Backoffice;

use App\Entity\ByeMessage;
use App\Entity\CatalogTemplate;
use App\Entity\CatalogTemplateByeMessage;
use App\Entity\CatalogTemplateElement;
use App\Entity\CatalogTemplateRank;
use App\Entity\CatalogTemplateRankStat;
use App\Entity\CatalogTemplateRole;
use App\Entity\CatalogTemplateStat;
use App\Entity\CatalogTemplateWelcomeMessage;
use App\Entity\CharacterRole;
use App\Entity\DiscordServer;
use App\Entity\Element;
use App\Entity\Rank;
use App\Entity\RankStat;
use App\Entity\Stat;
use App\Entity\WelcomeMessage;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;

final readonly class CatalogTemplateImporter
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Connection $connection,
        private CatalogValidator $catalogValidator,
    ) {
    }

    /**
     * @param array<string, string> $rankDiscordRoleIds Indexed by template rank id.
     */
    public function import(DiscordServer $server, CatalogTemplate $template, array $rankDiscordRoleIds): void
    {
        if (!$server->active()) {
            throw new \InvalidArgumentException('Cannot import a catalog into an inactive server.');
        }

        if (!$this->catalogValidator->validateTemplate($template)->ready()) {
            throw new \InvalidArgumentException('Catalog template is not ready for import.');
        }

        $templateRanks = $this->templateRanks($template);
        $this->assertRankMappings($templateRanks, $rankDiscordRoleIds);

        $this->entityManager->wrapInTransaction(function () use ($server, $template, $templateRanks, $rankDiscordRoleIds): void {
            $this->clearServerCatalog($server);

            $rankMap = [];
            foreach ($templateRanks as $templateRank) {
                $rank = new Rank(
                    $server,
                    trim($rankDiscordRoleIds[(string) $templateRank->id()]),
                    $templateRank->name(),
                    $templateRank->percentage(),
                    $templateRank->byeTitle(),
                    $templateRank->isStaff(),
                );
                $this->entityManager->persist($rank);
                $rankMap[$templateRank->id()] = $rank;
            }

            foreach ($this->templateRoles($template) as $templateRole) {
                $this->entityManager->persist(new CharacterRole(
                    $server,
                    $templateRole->name(),
                    $templateRole->percentage(),
                    $templateRole->emojiSource(),
                    $templateRole->emojiUnicode(),
                    $templateRole->emojiId(),
                    $templateRole->emojiName(),
                    $templateRole->emojiAnimated(),
                ));
            }

            $statMap = [];
            foreach ($this->templateStats($template) as $templateStat) {
                $stat = new Stat($server, $templateStat->name());
                $this->entityManager->persist($stat);
                $statMap[$templateStat->id()] = $stat;
            }

            foreach ($this->templateElements($template) as $templateElement) {
                $this->entityManager->persist(new Element(
                    $server,
                    $templateElement->name(),
                    $templateElement->emojiSource(),
                    $templateElement->emojiUnicode(),
                    $templateElement->emojiId(),
                    $templateElement->emojiName(),
                    $templateElement->emojiAnimated(),
                ));
            }

            foreach ($this->templateRankStats($template) as $templateRankStat) {
                $rank = $rankMap[$templateRankStat->rank()->id()] ?? null;
                $stat = $statMap[$templateRankStat->stat()->id()] ?? null;
                if ($rank instanceof Rank && $stat instanceof Stat) {
                    $this->entityManager->persist(new RankStat($rank, $stat, $templateRankStat->percentage()));
                }
            }

            foreach ($this->templateWelcomeMessages($template) as $templateMessage) {
                $rank = $rankMap[$templateMessage->rank()->id()] ?? null;
                if ($rank instanceof Rank) {
                    $this->entityManager->persist(new WelcomeMessage($server, $rank, $templateMessage->message()));
                }
            }

            foreach ($this->templateByeMessages($template) as $templateMessage) {
                $rank = $rankMap[$templateMessage->rank()->id()] ?? null;
                if ($rank instanceof Rank) {
                    $this->entityManager->persist(new ByeMessage($server, $rank, $templateMessage->message()));
                }
            }

            $this->entityManager->flush();
        });
    }

    /**
     * @return array{
     *     current: array{ranks: int, rank_stats: int, welcome_messages: int, bye_messages: int, roles: int, stats: int, elements: int, total: int},
     *     incoming: array{ranks: int, rank_stats: int, welcome_messages: int, bye_messages: int, roles: int, stats: int, elements: int, total: int},
     *     affected_user_count: int,
     *     validation: array{ready: bool, errors: list<string>, warnings: list<string>}
     * }
     */
    public function preview(DiscordServer $server, CatalogTemplate $template): array
    {
        $serverId = $server->id();
        $templateId = $template->id();
        if (null === $serverId || null === $templateId) {
            throw new \InvalidArgumentException('Cannot preview an import with unpersisted data.');
        }

        $current = [
            'ranks' => $this->tableCount('ranks', 'server_id', $serverId),
            'rank_stats' => (int) $this->connection->fetchOne('SELECT COUNT(*) FROM rank_stats rs INNER JOIN ranks r ON r.id = rs.rank_id WHERE r.server_id = ?', [$serverId]),
            'welcome_messages' => $this->tableCount('welcome_messages', 'server_id', $serverId),
            'bye_messages' => $this->tableCount('bye_messages', 'server_id', $serverId),
            'roles' => $this->tableCount('roles', 'server_id', $serverId),
            'stats' => $this->tableCount('stats', 'server_id', $serverId),
            'elements' => $this->tableCount('elements', 'server_id', $serverId),
        ];
        $incoming = [
            'ranks' => $this->tableCount('catalog_template_ranks', 'template_id', $templateId),
            'rank_stats' => (int) $this->connection->fetchOne('SELECT COUNT(*) FROM catalog_template_rank_stats rs INNER JOIN catalog_template_ranks r ON r.id = rs.rank_id WHERE r.template_id = ?', [$templateId]),
            'welcome_messages' => $this->tableCount('catalog_template_welcome_messages', 'template_id', $templateId),
            'bye_messages' => $this->tableCount('catalog_template_bye_messages', 'template_id', $templateId),
            'roles' => $this->tableCount('catalog_template_roles', 'template_id', $templateId),
            'stats' => $this->tableCount('catalog_template_stats', 'template_id', $templateId),
            'elements' => $this->tableCount('catalog_template_elements', 'template_id', $templateId),
        ];

        return [
            'current' => [...$current, 'total' => array_sum($current)],
            'incoming' => [...$incoming, 'total' => array_sum($incoming)],
            'affected_user_count' => $this->tableCount('users', 'server_id', $serverId),
            'validation' => $this->catalogValidator->validateTemplate($template)->toArray(),
        ];
    }

    /**
     * @param list<CatalogTemplateRank> $templateRanks
     * @param array<string, string>     $rankDiscordRoleIds
     */
    private function assertRankMappings(array $templateRanks, array $rankDiscordRoleIds): void
    {
        $usedDiscordRoleIds = [];
        foreach ($templateRanks as $templateRank) {
            $templateRankId = (string) $templateRank->id();
            $discordRoleId = trim($rankDiscordRoleIds[$templateRankId] ?? '');
            if ('' === $discordRoleId) {
                throw new \InvalidArgumentException(sprintf('Missing Discord role mapping for rank %s.', $templateRank->name()));
            }

            if (isset($usedDiscordRoleIds[$discordRoleId])) {
                throw new \InvalidArgumentException(sprintf('Discord role %s is mapped more than once.', $discordRoleId));
            }

            $usedDiscordRoleIds[$discordRoleId] = true;
        }
    }

    private function clearServerCatalog(DiscordServer $server): void
    {
        $serverId = $server->id();
        if (null === $serverId) {
            throw new \InvalidArgumentException('Cannot import a template into a server that is not persisted.');
        }

        $this->connection->executeStatement('UPDATE users SET rank_id = NULL, role_id = NULL WHERE server_id = ?', [$serverId]);
        $this->connection->executeStatement('DELETE FROM users_elements WHERE element_id IN (SELECT id FROM elements WHERE server_id = ?)', [$serverId]);
        $this->connection->executeStatement('DELETE FROM user_stats WHERE stat_id IN (SELECT id FROM stats WHERE server_id = ?)', [$serverId]);
        $this->connection->delete('bye_messages', ['server_id' => $serverId]);
        $this->connection->delete('welcome_messages', ['server_id' => $serverId]);
        $this->connection->executeStatement('DELETE FROM rank_stats WHERE rank_id IN (SELECT id FROM ranks WHERE server_id = ?) OR stat_id IN (SELECT id FROM stats WHERE server_id = ?)', [$serverId, $serverId]);
        $this->connection->delete('ranks', ['server_id' => $serverId]);
        $this->connection->delete('roles', ['server_id' => $serverId]);
        $this->connection->delete('elements', ['server_id' => $serverId]);
        $this->connection->delete('stats', ['server_id' => $serverId]);
    }

    private function tableCount(string $table, string $scopeColumn, int $scopeId): int
    {
        return (int) $this->connection->fetchOne(
            sprintf('SELECT COUNT(*) FROM %s WHERE %s = ?', $table, $scopeColumn),
            [$scopeId],
        );
    }

    /**
     * @return list<CatalogTemplateRank>
     */
    private function templateRanks(CatalogTemplate $template): array
    {
        return $this->entityManager->getRepository(CatalogTemplateRank::class)->findBy(['template' => $template], ['percentage' => 'ASC', 'name' => 'ASC']);
    }

    /**
     * @return list<CatalogTemplateRole>
     */
    private function templateRoles(CatalogTemplate $template): array
    {
        return $this->entityManager->getRepository(CatalogTemplateRole::class)->findBy(['template' => $template], ['percentage' => 'ASC', 'name' => 'ASC']);
    }

    /**
     * @return list<CatalogTemplateStat>
     */
    private function templateStats(CatalogTemplate $template): array
    {
        return $this->entityManager->getRepository(CatalogTemplateStat::class)->findBy(['template' => $template], ['name' => 'ASC']);
    }

    /**
     * @return list<CatalogTemplateElement>
     */
    private function templateElements(CatalogTemplate $template): array
    {
        return $this->entityManager->getRepository(CatalogTemplateElement::class)->findBy(['template' => $template], ['name' => 'ASC']);
    }

    /**
     * @return list<CatalogTemplateRankStat>
     */
    private function templateRankStats(CatalogTemplate $template): array
    {
        return $this->entityManager->createQueryBuilder()
            ->select('rankStat')
            ->from(CatalogTemplateRankStat::class, 'rankStat')
            ->innerJoin('rankStat.rank', 'rank')
            ->andWhere('rank.template = :template')
            ->setParameter('template', $template)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<CatalogTemplateWelcomeMessage>
     */
    private function templateWelcomeMessages(CatalogTemplate $template): array
    {
        return $this->entityManager->getRepository(CatalogTemplateWelcomeMessage::class)->findBy(['template' => $template], ['id' => 'ASC']);
    }

    /**
     * @return list<CatalogTemplateByeMessage>
     */
    private function templateByeMessages(CatalogTemplate $template): array
    {
        return $this->entityManager->getRepository(CatalogTemplateByeMessage::class)->findBy(['template' => $template], ['id' => 'ASC']);
    }
}
