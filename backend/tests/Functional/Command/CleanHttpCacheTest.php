<?php

declare(strict_types=1);

namespace Tests\Functional\Command;

use Doctrine\DBAL\Exception;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Kevinrob\GuzzleCache\CacheEntry;
use Kevinrob\GuzzleCache\Storage\Psr6CacheStorage;
use Neucore\Factory\HttpClientFactory;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Tests\Functional\ConsoleTestCase;

class CleanHttpCacheTest extends ConsoleTestCase
{
    private static string $cacheDir =  __DIR__ . '/cache';

    /**
     * @var string[]
     */
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

    public function testExecute_Filesystem(): void
    {
        $dir = self::$cacheDir . '/http/default';
        $cache1 = new Psr6CacheStorage(new FilesystemAdapter('', 86400, $dir));
        $cache2 = new Psr6CacheStorage(new FilesystemAdapter('ns1', 86400, $dir));
        $entry1 = new CacheEntry(new Request('GET', '/1'), new Response(), new \DateTime('+ 1000 seconds'));
        $entry2 = new CacheEntry(new Request('GET', '/2'), new Response(), new \DateTime('+ 10 seconds'));
        $cache1->save('abc', $entry1);
        $cache1->save('def', $entry2);
        $cache2->save('123', $entry1);
        $cache2->save('456', $entry2);

        $this->files = $this->getFiles($dir);

        // change lifetime of two files
        foreach ([1, 3] as $fileIndex) {
            $fileContent = explode("\n", (string) file_get_contents($this->files[$fileIndex]));
            $fileContent[0] = time() - 10;
            file_put_contents($this->files[$fileIndex], implode("\n", $fileContent));
        }

        $output = $this->runConsoleApp('clean-http-cache', [], [], [
            ['NEUCORE_CACHE_DIR', self::$cacheDir],
        ], true);
        $actual = explode("\n", $output);

        self::assertStringContainsString('Started "clean-http-cache"', $actual[0]);
        self::assertStringContainsString('Finished "clean-http-cache"', $actual[1]);
        self::assertSame('', $actual[2]);
        self::assertSame(3, count($actual));

        self::assertTrue(file_exists($this->files[0]));
        self::assertTrue(file_exists($this->files[2]));
        self::assertFalse(file_exists($this->files[1]));
        self::assertFalse(file_exists($this->files[3]));
    }

    /**
     * @return string[]
     */
    private function getFiles(string $path): array
    {
        $files = [];
        $objects = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path));
        foreach ($objects as $object) { /* @var \SplFileInfo $object */
            if ($object->isFile()) {
                $files[] = (string) $object->getRealPath();
            }
        }
        return $files;
    }
}
