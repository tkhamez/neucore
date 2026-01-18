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
use Tests\Helper;

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

    /**
     * @throws Exception
     */
    public function testExecute_Database(): void
    {
        $cacheTable = 'cache_http';
        $h = new Helper();
        $h->emptyDb();
        $conn = $h->getEm()->getConnection();

        $storage = new Psr6CacheStorage($h->getHttpCacheAdapter($cacheTable, 'test.1'));
        $request = new Request('GET', 'https://example.com/test');
        $entry1 = new CacheEntry($request, new Response(), new \DateTime('+ 1000 seconds'));
        $entry2 = new CacheEntry($request, new Response(), new \DateTime('+ 10 seconds'));
        $storage->save('abc', $entry1);
        $storage->save('def', $entry2);

        // change item time of $entry2
        $conn->executeStatement(
            "UPDATE $cacheTable SET item_time = ? WHERE item_id = ?",
            [time() - 11, 'test.1:def'],
        );

        $output = $this->runConsoleApp('clean-http-cache', [], [], [
            ['NEUCORE_HTTP_CACHE_STORAGE', HttpClientFactory::CACHE_STORAGE_DATABASE],
        ], true);
        $actual = explode("\n", $output);

        self::assertStringContainsString('Started "clean-http-cache"', $actual[0]);
        self::assertStringContainsString('Finished "clean-http-cache"', $actual[1]);
        self::assertSame('', $actual[2]);
        self::assertSame(3, count($actual));

        $result = $conn->fetchAllAssociative("SELECT item_id FROM $cacheTable");
        $ids = [];
        foreach ($result as $row) {
            $ids[] = $row['item_id'];
        }
        self::assertTrue(in_array('test.1:abc', $ids));
        self::assertFalse(in_array('test.1:def', $ids));
    }

    public function testExecute_Filesystem(): void
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

        $output = $this->runConsoleApp('clean-http-cache', [], [], [
            ['NEUCORE_HTTP_CACHE_STORAGE', HttpClientFactory::CACHE_STORAGE_FILESYSTEM],
            ['NEUCORE_CACHE_DIR', self::$cacheDir],
        ], true);
        $actual = explode("\n", $output);

        self::assertStringContainsString('Started "clean-http-cache"', $actual[0]);
        self::assertStringContainsString('Finished "clean-http-cache"', $actual[1]);
        self::assertSame('', $actual[2]);
        self::assertSame(3, count($actual));

        self::assertTrue(file_exists($this->files[0]));
        self::assertFalse(file_exists($this->files[1]));
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
