<?php

namespace App\Tests\Http;

use App\Http\PublicPathRequestNormalizer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class PublicPathRequestNormalizerTest extends TestCase
{
    public function testNormalizesProjectSubdirectoryFrontController(): void
    {
        $request = Request::create('/gachamelia/', server: [
            'REQUEST_URI' => '/gachamelia/',
            'SCRIPT_FILENAME' => '/var/www/gachamelia/public/index.php',
            'SCRIPT_NAME' => '/gachamelia/public/index.php',
            'PHP_SELF' => '/gachamelia/public/index.php',
        ]);

        PublicPathRequestNormalizer::normalize($request, '/gachamelia');

        self::assertSame('/gachamelia', $request->getBasePath());
        self::assertSame('/', $request->getPathInfo());
    }

    public function testKeepsRequestsOutsideBasePathUntouched(): void
    {
        $request = Request::create('/admin', server: [
            'REQUEST_URI' => '/admin',
            'SCRIPT_FILENAME' => '/var/www/gachamelia/public/index.php',
            'SCRIPT_NAME' => '/public/index.php',
            'PHP_SELF' => '/public/index.php',
        ]);

        PublicPathRequestNormalizer::normalize($request, '/gachamelia');

        self::assertSame('', $request->getBasePath());
        self::assertSame('/admin', $request->getPathInfo());
    }
}
