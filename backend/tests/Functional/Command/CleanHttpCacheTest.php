<?php

declare(strict_types=1);

namespace Tests\Functional\Command;

use Doctrine\Common\Cache\FilesystemCache;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Kevinrob\GuzzleCache\CacheEntry;
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

    /**
     * @see \Kevinrob\GuzzleCache\Strategy\PrivateCacheStrategy::cache()
     * @see \Kevinrob\GuzzleCache\Strategy\PrivateCacheStrategy::getCacheObject()
     * @see \Kevinrob\GuzzleCache\Storage\DoctrineCacheStorage::save()
     * @see \Doctrine\Common\Cache\FilesystemCache::getFilename()
     * @see \Doctrine\Common\Cache\FileCache::save()
     * @see \Doctrine\Common\Cache\FilesystemCache::doSave()
     */
    public function testExecute()
    {
        $dir = __DIR__ . '/cache';
        /* @phan-suppress-next-line PhanDeprecatedClass */
        $cache = new FilesystemCache($dir . '/http');
        $entry1 = new CacheEntry(new Request('GET', '/1'), new Response(), new \DateTime('+ 100 seconds'));
        $entry2 = new CacheEntry(new Request('GET', '/2'), new Response(), new \DateTime('- 100 seconds'));
        $cache->save('abc', serialize($entry1));
        $cache->save('def', serialize($entry2));
        $this->file1 = $dir . '/http/88/5b6162635d5b315d.doctrinecache.data';
        $this->file2 = $dir . '/http/be/5b6465665d5b315d.doctrinecache.data';

        // this test cannot run in prod mode because the CompiledContainer class is missing otherwise
        $output = $this->runConsoleApp('clean-http-cache', [], [], ['NEUCORE_CACHE_DIR=' . $dir], true);
        $actual = explode("\n", $output);

        $this->assertStringContainsString('Guzzle cache cleaned.', $actual[0]);
        $this->assertSame('', $actual[1]);
        $this->assertSame(2, count($actual));

        $this->assertTrue(file_exists($this->file1));
        $this->assertFalse(file_exists($this->file2));
    }
}
