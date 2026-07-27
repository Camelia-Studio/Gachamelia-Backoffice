<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\Catch_\ThrowWithPreviousExceptionRector;
use Rector\Config\RectorConfig;
use Rector\Exception\Configuration\InvalidConfigurationException;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;

try {
    return RectorConfig::configure()
        ->withSets([
            LevelSetList::UP_TO_PHP_83,
            SetList::DEAD_CODE,
            SetList::CODE_QUALITY,
            SetList::PHP_83,
        ])
        ->withPaths([
            __DIR__.'/src',
            __DIR__.'/tests',
            __DIR__.'/migrations',
        ])
        ->withPreparedSets(
            deadCode: true,
            codeQuality: true,
        )
        ->withComposerBased(
            twig: true,
            doctrine: true,
            phpunit: true,
            symfony: true,
        )
        ->withSkip([
            ThrowWithPreviousExceptionRector::class => [
                __DIR__.'/src/Security/JwtAuthenticator.php',
            ],
        ])
        ->withSymfonyContainerPhp(__DIR__.'/var/cache/dev/App_KernelDevDebugContainer.php')
    ;
} catch (InvalidConfigurationException $e) {
    echo 'Rector configuration error: '.$e->getMessage().\PHP_EOL;
    exit(1);
}
