<?php

namespace App\Http;

use Symfony\Component\HttpFoundation\Request;

final class PublicPathRequestNormalizer
{
    public static function normalize(Request $request, ?string $basePath): void
    {
        $basePath = self::normalizeBasePath($basePath);

        if ('' === $basePath || !self::requestUsesBasePath($request, $basePath)) {
            return;
        }

        $scriptFilename = str_replace('\\', '/', (string) $request->server->get('SCRIPT_FILENAME', ''));

        if (!str_ends_with($scriptFilename, '/public/index.php')) {
            return;
        }

        $frontController = $basePath.'/index.php';
        $request->server->set('SCRIPT_NAME', $frontController);
        $request->server->set('PHP_SELF', $frontController);
    }

    private static function normalizeBasePath(?string $basePath): string
    {
        $basePath = trim((string) $basePath, '/');

        return '' === $basePath ? '' : '/'.$basePath;
    }

    private static function requestUsesBasePath(Request $request, string $basePath): bool
    {
        $requestUri = (string) $request->server->get('REQUEST_URI', '');
        $requestPath = parse_url($requestUri, PHP_URL_PATH);

        if (!\is_string($requestPath)) {
            return false;
        }

        return $requestPath === $basePath || str_starts_with($requestPath, $basePath.'/');
    }
}
