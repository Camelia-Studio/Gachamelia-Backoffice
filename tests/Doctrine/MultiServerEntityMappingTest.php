<?php

declare(strict_types=1);

namespace App\Tests\Doctrine;

use App\Entity\CharacterRole;
use App\Entity\ByeMessage;
use App\Entity\DiscordServer;
use App\Entity\DiscordServerMember;
use App\Entity\DiscordUser;
use App\Entity\Element;
use App\Entity\GachaUser;
use App\Entity\Rank;
use App\Entity\RankStat;
use App\Entity\Stat;
use App\Entity\UserStat;
use App\Entity\WelcomeMessage;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class MultiServerEntityMappingTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
    }

    public function testRootServerMapping(): void
    {
        $metadata = $this->metadata(DiscordServer::class);

        self::assertSame('discord_servers', $metadata->getTableName());
        self::assertTrue($metadata->hasField('discordId'));
        $this->assertUniqueColumns($metadata, ['discord_id']);
    }

    public function testBackofficeDiscordUserAndMembershipMappings(): void
    {
        $user = $this->metadata(DiscordUser::class);

        self::assertSame('discord_users', $user->getTableName());
        self::assertTrue($user->hasField('discordId'));
        self::assertTrue($user->hasField('username'));
        self::assertTrue($user->hasField('globalName'));
        self::assertTrue($user->hasField('avatar'));
        $this->assertUniqueColumns($user, ['discord_id']);

        $membership = $this->metadata(DiscordServerMember::class);

        self::assertSame('discord_server_members', $membership->getTableName());
        self::assertTrue($membership->hasAssociation('user'));
        self::assertTrue($membership->hasAssociation('server'));
        self::assertTrue($membership->hasField('canManageConfiguration'));
        $this->assertUniqueColumns($membership, ['user_id', 'server_id']);
    }

    public function testCatalogMappingsAreServerScoped(): void
    {
        foreach ([Rank::class, CharacterRole::class, Stat::class, Element::class] as $entityClass) {
            $metadata = $this->metadata($entityClass);

            self::assertTrue($metadata->hasAssociation('server'));
            self::assertSame('server_id', $metadata->getAssociationMapping('server')->joinColumns[0]->name);
            $this->assertUniqueColumns($metadata, ['server_id', 'name']);
        }

        foreach ([CharacterRole::class, Element::class] as $entityClass) {
            $metadata = $this->metadata($entityClass);

            self::assertTrue($metadata->hasField('emojiSource'));
            self::assertTrue($metadata->hasField('emojiUnicode'));
            self::assertTrue($metadata->hasField('emojiId'));
            self::assertTrue($metadata->hasField('emojiName'));
            self::assertTrue($metadata->hasField('emojiAnimated'));
        }

        $this->assertUniqueColumns($this->metadata(Rank::class), ['server_id', 'discord_id']);
    }

    public function testUsersAreServerScopedAndReferenceCatalogRows(): void
    {
        $metadata = $this->metadata(GachaUser::class);

        self::assertSame('users', $metadata->getTableName());
        self::assertTrue($metadata->hasAssociation('server'));
        self::assertTrue($metadata->hasAssociation('rank'));
        self::assertTrue($metadata->hasAssociation('role'));
        self::assertTrue($metadata->hasAssociation('elements'));
        $this->assertUniqueColumns($metadata, ['server_id', 'discord_id']);
    }

    public function testCompositeRelationMappings(): void
    {
        $rankStat = $this->metadata(RankStat::class);
        self::assertSame(['rank', 'stat'], $rankStat->identifier);
        self::assertSame('rank_stats', $rankStat->getTableName());

        $userStat = $this->metadata(UserStat::class);
        self::assertSame(['user', 'stat'], $userStat->identifier);
        self::assertSame('user_stats', $userStat->getTableName());
    }

    public function testMessageMappingsAreServerAndRankScoped(): void
    {
        foreach ([WelcomeMessage::class => 'welcome_messages', ByeMessage::class => 'bye_messages'] as $entityClass => $tableName) {
            $metadata = $this->metadata($entityClass);

            self::assertSame($tableName, $metadata->getTableName());
            self::assertTrue($metadata->hasAssociation('server'));
            self::assertTrue($metadata->hasAssociation('rank'));
            self::assertTrue($metadata->hasField('message'));
        }
    }

    /**
     * @param class-string $entityClass
     */
    private function metadata(string $entityClass): ClassMetadata
    {
        return $this->entityManager->getClassMetadata($entityClass);
    }

    /**
     * @param list<string> $columns
     */
    private function assertUniqueColumns(ClassMetadata $metadata, array $columns): void
    {
        $uniqueConstraints = $metadata->table['uniqueConstraints'] ?? [];

        foreach ($uniqueConstraints as $uniqueConstraint) {
            if (($uniqueConstraint['columns'] ?? []) === $columns) {
                self::assertTrue(true);

                return;
            }
        }

        self::fail(sprintf('Missing unique columns %s on %s.', implode(', ', $columns), $metadata->getTableName()));
    }
}
