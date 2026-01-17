<?php

declare(strict_types=1);

namespace Tests\Functional\Command;

use Doctrine\DBAL\Exception;
use Tests\Functional\ConsoleTestCase;
use Tests\Helper;

class ClearCacheTest extends ConsoleTestCase
{
    protected function tearDown(): void
    {
        unset($_ENV['NEUCORE_CACHE_DIR']);
    }

    /**
     * @throws Exception
     */
    public function testExecute(): void
    {
        $h = new Helper();

        $cacheDir = __DIR__ . '/cache';
        if (!is_dir($cacheDir . '/di')) {
            mkdir($cacheDir . '/di', 0775, true);
        }
        if (!is_dir($cacheDir . '/proxies')) {
            mkdir($cacheDir . '/proxies', 0775, true);
        }
        if (!is_dir($cacheDir . '/another')) {
            mkdir($cacheDir . '/another', 0775, true);
        }
        touch($cacheDir . '/di/CompiledContainer.php');
        touch($cacheDir . '/proxies/__CG__NeucoreEntityCorporation.php');
        $h->addHttpCacheEntry('cache_http', 'test', 'key1');

        // this test cannot run in prod mode because the CompiledContainer class is missing otherwise
        $output = $this->runConsoleApp('clear-cache', [], [], [['NEUCORE_CACHE_DIR', $cacheDir]], true);
        $actual = explode("\n", $output);

        self::assertSame(
            'Cleared ' . __DIR__ . '/cache/di, ' .
            __DIR__ . '/cache/proxies, Database table cache_http',
            $actual[0],
        );
        $this->assertSame('', $actual[1]);
        $this->assertSame(2, count($actual));

        $this->assertFalse(file_exists($cacheDir . '/di/CompiledContainer.php'));
        $this->assertFalse(file_exists($cacheDir . '/proxies/__CG__NeucoreEntityCorporation.php'));

        $conn = $h->getEm()->getConnection();
        $result = $conn->executeQuery('SELECT * FROM cache_http')->fetchAllAssociative();
        self::assertSame([], $result);
    }
}
