<?php

declare(strict_types=1);

namespace App\Tests\Backoffice\Csv;

use App\Backoffice\Csv\CatalogCsvDraftStore;
use App\Backoffice\Csv\CatalogCsvSection;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

final class CatalogCsvDraftStoreTest extends TestCase
{
    public function testDraftIsScopedExpiresAndIsRemovedExplicitly(): void
    {
        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage()));
        $requestStack = new RequestStack([$request]);
        $store = new CatalogCsvDraftStore($requestStack);
        $now = new \DateTimeImmutable('2026-07-26 20:00:00');

        $token = $store->put(42, 'server', 'guild-1', CatalogCsvSection::Stats, ['rows' => [['nom' => 'Force']]], $now);

        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $token);
        self::assertNull($store->get($token, 99, 'server', 'guild-1', CatalogCsvSection::Stats, $now));
        self::assertNull($store->get($token, 42, 'template', 'guild-1', CatalogCsvSection::Stats, $now));
        self::assertNull($store->get($token, 42, 'server', 'guild-2', CatalogCsvSection::Stats, $now));
        self::assertNull($store->get($token, 42, 'server', 'guild-1', CatalogCsvSection::Elements, $now));
        self::assertSame(
            ['rows' => [['nom' => 'Force']]],
            $store->get($token, 42, 'server', 'guild-1', CatalogCsvSection::Stats, $now->modify('+29 minutes')),
        );
        self::assertNull($store->get($token, 42, 'server', 'guild-1', CatalogCsvSection::Stats, $now->modify('+31 minutes')));

        $freshToken = $store->put(42, 'server', 'guild-1', CatalogCsvSection::Stats, ['ok' => true], $now);
        self::assertSame(['ok' => true], $store->get($freshToken, 42, 'server', 'guild-1', CatalogCsvSection::Stats, $now));
        $store->remove($freshToken);
        self::assertNull($store->get($freshToken, 42, 'server', 'guild-1', CatalogCsvSection::Stats, $now));
    }
}
