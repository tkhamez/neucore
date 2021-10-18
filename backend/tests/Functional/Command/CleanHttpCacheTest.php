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
    private $file1;
    private $file2;

    protected function tearDown(): void
    {
        putenv('NEUCORE_CACHE_DIR=');

        @unlink($this->file1);
        @unlink($this->file2);
    }

    public function testExecute()
    {
        $dir = __DIR__ . '/cache';
        /* @phan-suppress-next-line PhanDeprecatedClass */
        $cache = new Psr6CacheStorage(new FilesystemAdapter('', 86400, $dir . '/http/default'));
        $entry1 = new CacheEntry(new Request('GET', '/1'), new Response(), new \DateTime('+ 1000 seconds'));
        $entry2 = new CacheEntry(new Request('GET', '/2'), new Response(), new \DateTime('+ 10 seconds'));
        $cache->save('abc', $entry1);
        $cache->save('def', $entry2);

        $this->file1 = $dir . '/http/default/@/D/K/EgL8QYyiakaaA6EJZP8Q';
        $this->file2 = $dir . '/http/default/@/X/N/fghpXxufE0oEF1KNkZqw';

        // change lifetime of file1
        $file1Content = explode("\n", (string)file_get_contents($this->file2));
        $file1Content[0] = time() - 10;
        file_put_contents($this->file2, implode("\n", $file1Content));

        $output = $this->runConsoleApp('clean-http-cache', [], [], ['NEUCORE_CACHE_DIR=' . $dir], true);
        $actual = explode("\n", $output);

        $this->assertStringContainsString('Guzzle cache cleaned.', $actual[0]);
        $this->assertSame('', $actual[1]);
        $this->assertSame(2, count($actual));

        $this->assertTrue(file_exists($this->file1));
        $this->assertFalse(file_exists($this->file2));
    }
}
