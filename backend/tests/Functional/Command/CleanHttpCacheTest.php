<?php

declare(strict_types=1);

namespace Tests\Functional\Command;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Kevinrob\GuzzleCache\CacheEntry;
use Kevinrob\GuzzleCache\Storage\Psr6CacheStorage;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Tests\Functional\ConsoleTestCase;

class CleanHttpCacheTest extends ConsoleTestCase
{
    private static string $cacheDir =  __DIR__ . '/cache';

    private array $files = [];

    protected function tearDown(): void
    {
        unset($_ENV['NEUCORE_CACHE_DIR']);

        foreach ($this->files as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
    }

    public function testExecute()
    {
        $dir = self::$cacheDir . '/http/default';
        $cache = new Psr6CacheStorage(new FilesystemAdapter('', 86400, $dir));
        $entry1 = new CacheEntry(new Request('GET', '/1'), new Response(), new \DateTime('+ 1000 seconds'));
        $entry2 = new CacheEntry(new Request('GET', '/2'), new Response(), new \DateTime('+ 10 seconds'));
        $cache->save('abc', $entry1);
        $cache->save('def', $entry2);

        $this->files = $this->getFiles($dir);

        // change lifetime of file1
        $file2Content = explode("\n", (string) file_get_contents($this->files[1]));
        $file2Content[0] = time() - 10;
        file_put_contents($this->files[1], implode("\n", $file2Content));

        $output = $this->runConsoleApp('clean-http-cache', [], [], [['NEUCORE_CACHE_DIR', self::$cacheDir]], true);
        $actual = explode("\n", $output);

        self::assertStringContainsString('Guzzle cache cleaned.', $actual[0]);
        self::assertSame('', $actual[1]);
        self::assertSame(2, count($actual));

        self::assertTrue(file_exists($this->files[0]));
        self::assertFalse(file_exists($this->files[1]));
    }

    private function getFiles(string $path): array
    {
        $files = [];
        $objects = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path));
        foreach ($objects as $object) { /* @var \SplFileInfo $object */
            if ($object->isFile()) {
                $files[] = $object->getRealPath();
            }
        }
        return $files;
    }
}
