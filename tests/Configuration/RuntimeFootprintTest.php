<?php

namespace App\Tests\Configuration;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

final class RuntimeFootprintTest extends TestCase
{
    public function testRuntimeKeepsDoctrineSymfonyUxAndApiSecurityButDropsUnusedBundles(): void
    {
        /** @var array<class-string, array<string, bool>> $bundles */
        $bundles = require dirname(__DIR__, 2).'/config/bundles.php';

        self::assertArrayHasKey(\Doctrine\Bundle\DoctrineBundle\DoctrineBundle::class, $bundles);
        self::assertArrayHasKey(\Doctrine\Bundle\MigrationsBundle\DoctrineMigrationsBundle::class, $bundles);
        self::assertArrayHasKey(\Symfony\Bundle\SecurityBundle\SecurityBundle::class, $bundles);
        self::assertArrayHasKey(\Symfony\UX\StimulusBundle\StimulusBundle::class, $bundles);
        self::assertArrayHasKey(\Symfony\UX\Turbo\TurboBundle::class, $bundles);

        self::assertArrayNotHasKey(\Twig\Extra\TwigExtraBundle::class, $bundles);
    }

    public function testDoctrineDefaultsTargetMysql(): void
    {
        $doctrineConfig = Yaml::parseFile(dirname(__DIR__, 2).'/config/packages/doctrine.yaml');

        self::assertSame(
            'identity',
            $doctrineConfig['doctrine']['orm']['identity_generation_preferences'][\Doctrine\DBAL\Platforms\MySQLPlatform::class] ?? null,
        );

        $env = file_get_contents(dirname(__DIR__, 2).'/.env');

        self::assertIsString($env);
        self::assertMatchesRegularExpression('/^DATABASE_URL="mysql:\/\//m', $env);
        self::assertStringContainsString('charset=utf8mb4', $env);
    }
}
