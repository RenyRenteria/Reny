<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class AdminPathConfigTest extends TestCase
{
    private const DEFAULT_ADMIN_PATH = '7YDX5h38a6Q2sfrsW2pRv9CoU59RA5YWD2R7K3AuMA';

    #[DataProvider('blankAdminPathProvider')]
    public function test_blank_admin_path_env_values_fall_back_to_private_path(string $adminPath): void
    {
        $adminRoutes = $this->routeList(['--name=admin'], $adminPath);

        $this->assertNotEmpty($adminRoutes);

        foreach ($adminRoutes as $route) {
            $this->assertStringStartsWith(self::DEFAULT_ADMIN_PATH, $route['uri']);
            $this->assertFalse(str_starts_with($route['uri'], 'admin'));
        }

        $this->assertSame([], $this->routeList(['--path=admin'], $adminPath));
    }

    public static function blankAdminPathProvider(): array
    {
        return [
            'empty string' => [''],
            'root slash' => ['/'],
        ];
    }

    /**
     * @return list<array{uri: string}>
     */
    private function routeList(array $filters, string $adminPath): array
    {
        $process = new Process(
            [PHP_BINARY, 'artisan', 'route:list', '--json', ...$filters],
            dirname(__DIR__, 2),
            [
                'ADMIN_PATH' => $adminPath,
                'APP_ENV' => 'testing',
                'APP_KEY' => 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=',
            ],
        );

        $process->run();

        if (! $process->isSuccessful()) {
            if (str_contains($process->getErrorOutput(), "doesn't have any routes matching the given criteria")) {
                return [];
            }

            $this->fail($process->getErrorOutput() ?: $process->getOutput());
        }

        $output = $process->getOutput();

        if (str_contains($output, "doesn't have any routes matching the given criteria")) {
            return [];
        }

        return json_decode($output, true, flags: JSON_THROW_ON_ERROR);
    }
}
