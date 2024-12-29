<?php

declare(strict_types=1);

namespace Tests\Functional\Command;

use Tests\Functional\ConsoleTestCase;

class ClearCacheTest extends ConsoleTestCase
{
    protected function tearDown(): void
    {
        unset($_ENV['NEUCORE_CACHE_DIR']);
    }

    public function testExecute()
    {
        $cacheDir = __DIR__ . '/cache';
        if (! is_dir($cacheDir . '/di')) {
            mkdir($cacheDir . '/di', 0775, true);
        }
        if (! is_dir($cacheDir . '/proxies')) {
            mkdir($cacheDir . '/proxies', 0775, true);
        }
        if (! is_dir($cacheDir . '/http/dc')) {
            mkdir($cacheDir . '/http/dc', 0775, true);
        }
        if (! is_dir($cacheDir . '/another')) {
            mkdir($cacheDir . '/another', 0775, true);
        }
        touch($cacheDir . '/di/CompiledContainer.php');
        touch($cacheDir . '/proxies/__CG__NeucoreEntityCorporation.php');
        touch($cacheDir . '/http/dc/5b656339383535321313437375d5b315d.doctrinecache.data');

        // this test cannot run in prod mode because the CompiledContainer class is missing otherwise
        $output = $this->runConsoleApp('clear-cache', [], [], [['NEUCORE_CACHE_DIR', $cacheDir]], true);
        $actual = explode("\n", $output);

        $this->assertStringStartsWith(
            'Cleared ' . __DIR__ . '/cache/di, ' . __DIR__ . '/cache/proxies, ' . __DIR__ . '/cache/http',
            $actual[0],
        );
        $this->assertSame('', $actual[1]);
        $this->assertSame(2, count($actual));

        $this->assertFalse(file_exists($cacheDir . '/di/CompiledContainer.php'));
        $this->assertFalse(file_exists($cacheDir . '/proxies/__CG__NeucoreEntityCorporation.php'));
        $this->assertFalse(file_exists($cacheDir . '/http/dc/5b656339383535321313437375d5b315d.doctrinecache.data'));
    }
}
