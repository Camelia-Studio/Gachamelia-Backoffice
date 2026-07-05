<?php

namespace App\Tests\Configuration;

use PHPUnit\Framework\TestCase;

final class WebpackEnvLoaderTest extends TestCase
{
    private string $temporaryDirectory;

    protected function setUp(): void
    {
        $this->temporaryDirectory = sys_get_temp_dir().'/gachamelia-webpack-env-'.bin2hex(random_bytes(6));
        mkdir($this->temporaryDirectory);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->temporaryDirectory);
    }

    public function testWebpackBuildLoadsAppBasePathFromSymfonyLocalEnvFile(): void
    {
        file_put_contents($this->temporaryDirectory.'/.env', "APP_ENV=dev\nAPP_BASE_PATH=\n");
        file_put_contents($this->temporaryDirectory.'/.env.local', "APP_BASE_PATH=/gachamelia\n");

        $payload = $this->runWebpackEnvScript(<<<'JS'
            const target = {};
            loadSymfonyDotenvFiles({ cwd, target });
            console.log(JSON.stringify({
                appBasePath: target.APP_BASE_PATH,
                normalized: normalizeBasePath(target.APP_BASE_PATH),
            }));
        JS);

        self::assertSame('/gachamelia', $payload['appBasePath'] ?? null);
        self::assertSame('/gachamelia', $payload['normalized'] ?? null);
    }

    public function testRealEnvironmentKeepsPriorityOverSymfonyEnvFiles(): void
    {
        file_put_contents($this->temporaryDirectory.'/.env', "APP_ENV=dev\nAPP_BASE_PATH=\n");
        file_put_contents($this->temporaryDirectory.'/.env.local', "APP_BASE_PATH=/gachamelia\n");

        $payload = $this->runWebpackEnvScript(<<<'JS'
            const target = { APP_BASE_PATH: '/from-shell' };
            loadSymfonyDotenvFiles({ cwd, target });
            console.log(JSON.stringify({
                appBasePath: target.APP_BASE_PATH,
                normalized: normalizeBasePath(target.APP_BASE_PATH),
            }));
        JS);

        self::assertSame('/from-shell', $payload['appBasePath'] ?? null);
        self::assertSame('/from-shell', $payload['normalized'] ?? null);
    }

    /**
     * @return array<string, mixed>
     */
    private function runWebpackEnvScript(string $scriptBody): array
    {
        $moduleUrl = 'file://'.\dirname(__DIR__, 2).'/webpack.env.js';
        $script = <<<JS
            const cwd = {$this->jsonEncode($this->temporaryDirectory)};
            const { loadSymfonyDotenvFiles, normalizeBasePath } = await import({$this->jsonEncode($moduleUrl)});
            {$scriptBody}
        JS;

        $command = 'node --input-type=module --eval '.escapeshellarg($script);
        exec($command, $output, $exitCode);

        self::assertSame(0, $exitCode, implode("\n", $output));

        $payload = json_decode(implode("\n", $output), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($payload);

        return $payload;
    }

    private function jsonEncode(string $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR);
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        foreach (scandir($directory) ?: [] as $item) {
            if ('.' === $item || '..' === $item) {
                continue;
            }

            $path = $directory.'/'.$item;
            if (is_dir($path)) {
                $this->removeDirectory($path);
                continue;
            }

            unlink($path);
        }

        rmdir($directory);
    }
}
