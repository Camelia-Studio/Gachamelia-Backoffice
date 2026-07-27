<?php

declare(strict_types=1);

/*
 * This file is part of PHP CS Fixer.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *     Dariusz Rumiński <dariusz.ruminski@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

$finder = PhpCsFixer\Finder::create()
    ->ignoreVCSIgnored(true)
    ->notPath('config/reference.php')
    ->exclude('tests/Fixtures')
    ->in(__DIR__)
    ->append([
        __DIR__.'/dev-tools/doc.php',
        // __DIR__.'/php-cs-fixer', disabled, as we want to be able to run bootstrap file even on lower PHP version, to show nice message
        __FILE__,
    ])
;

$config = new PhpCsFixer\Config();
$config
    ->setRiskyAllowed(true)
    ->setRules([
        '@PHP83Migration' => true,
        '@PHP82Migration:risky' => true,
        '@Symfony' => true,
        '@Symfony:risky' => true,
        '@PhpCsFixer:risky' => true,
        '@PSR1' => true,
        '@PSR2' => true,
        '@PSR12' => true,
    ])
    ->setFinder($finder)
;

return $config;
