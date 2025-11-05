<?php

declare(strict_types=1);

namespace Tests\Unit\Middleware\Guzzle;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Neucore\Factory\RepositoryFactory;
use Neucore\Middleware\Guzzle\EsiRateLimits;
use Neucore\Service\ObjectManager;
use Neucore\Storage\SystemVariableStorage;
use Neucore\Storage\Variables;
use PHPUnit\Framework\TestCase;
use Tests\Helper;
use Tests\Logger;

class EsiRateLimitsTest extends TestCase
{
    private Helper $helper;

    private SystemVariableStorage $storage;

    private EsiRateLimits $obj;

    protected function setUp(): void
    {
        $this->helper = new Helper();
        $this->helper->emptyDb();
        $om = $this->helper->getObjectManager();

        $logger = new Logger();

        $this->storage = new SystemVariableStorage(
            new RepositoryFactory($om),
            new ObjectManager($om, $logger),
        );
        #apcu_clear_cache();
        #$this->storage = new \Neucore\Storage\ApcuStorage();

        $this->obj = new EsiRateLimits($logger, $this->storage);
    }

    public function testInvoke_RateLimits(): void
    {
        $response = new Response(200, [
            'X-Ratelimit-Group' => ['char-location'],
            'X-Ratelimit-Limit' => ['1200/15m'],
            'X-Ratelimit-Remaining' => ['1198'],
            'X-Ratelimit-Used' => ['2'],
        ]);
        $function = $this->obj->__invoke($this->helper->getGuzzleHandler($response));

        $function(new Request('GET', 'http://localhost/path'), []);

        $this->assertSame(
            '{"char-location":{"limit":"1200/15m","remaining":1198,"used":2}}',
            $this->storage->get(Variables::ESI_RATE_LIMIT),
        );
    }

    public function testInvoke_429(): void
    {
        $waitUntil = time() + 60;
        $response1 = new Response(429, ['Retry-After' => [date('D, d M Y H:i:s \G\M\T', $waitUntil)]]);
        $function1 = $this->obj->__invoke($this->helper->getGuzzleHandler($response1));
        $function1(new Request('GET', 'https://local.host/esi/path'), []);
        $this->assertSame("$waitUntil", $this->storage->get(Variables::ESI_RATE_LIMITED));

        $response2 = new Response(429, ['Retry-After' => ['60']]);
        $function2 = $this->obj->__invoke($this->helper->getGuzzleHandler($response2));
        $function2(new Request('GET', 'https://local.host/esi/path'), []);
        $this->assertIsString($this->storage->get(Variables::ESI_RATE_LIMITED));
        $this->assertLessThanOrEqual(time() + 60, $this->storage->get(Variables::ESI_RATE_LIMITED));
    }
}
