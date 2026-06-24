<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    protected function frontendJavaScriptSource(): string
    {
        return $this->frontendSourceBundle('js/app.js', 'js/features/*.js');
    }

    protected function frontendCssSource(): string
    {
        return $this->frontendSourceBundle('css/app.css', 'css/features/*.css');
    }

    private function frontendSourceBundle(string $entrypoint, string $featureGlob): string
    {
        $featurePaths = glob(resource_path($featureGlob)) ?: [];
        sort($featurePaths);

        $paths = [
            resource_path($entrypoint),
            ...$featurePaths,
        ];

        return implode("\n", array_map(
            static fn (string $path): string => (string) file_get_contents($path),
            $paths,
        ));
    }
}
