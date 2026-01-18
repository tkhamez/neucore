<?php

declare(strict_types=1);

namespace Tests\Functional\Command;

use Doctrine\DBAL\Exception;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Kevinrob\GuzzleCache\CacheEntry;
use Kevinrob\GuzzleCache\Storage\Psr6CacheStorage;
use Tests\Functional\ConsoleTestCase;
use Tests\Helper;

class CleanHttpCacheTest extends ConsoleTestCase
{
    /**
     * @throws Exception
     */
    public function testExecute(): void
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

        $output = $this->runConsoleApp('clean-http-cache', [], [], [], true);
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
}
