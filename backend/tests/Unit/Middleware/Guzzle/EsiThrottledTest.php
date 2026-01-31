<?php

declare(strict_types=1);

namespace Tests\Unit\Middleware\Guzzle;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Neucore\Factory\RepositoryFactory;
use Neucore\Middleware\Guzzle\EsiThrottled;
use Neucore\Service\ObjectManager;
use Neucore\Storage\DatabaseStorage;
use Neucore\Storage\Variables;
use PHPUnit\Framework\TestCase;
use Tests\Helper;
use Tests\Logger;

class EsiThrottledTest extends TestCase
{
    private Helper $helper;

    private DatabaseStorage $storage;

    private EsiThrottled $obj;

    protected function setUp(): void
    {
        $this->helper = new Helper();
        $this->helper->emptyDb();
        $om = $this->helper->getObjectManager();

        $logger = new Logger();

        $this->storage = new DatabaseStorage(
            new RepositoryFactory($om),
            new ObjectManager($om, $logger),
        );

        $this->obj = new EsiThrottled($this->storage);
    }

    public function testInvoke_500(): void
    {
        $response1 = new Response(500, [], (string) \json_encode([
            'error' => 'Undefined 429 response. Original message: Too many errors.' .
                        'You have been temporarily throttled.',
        ]));
        $function1 = $this->obj->__invoke($this->helper->getGuzzleHandler($response1));
        $function1(new Request('GET', 'https://local.host/esi/path'), []);
        $this->assertIsString($this->storage->get(Variables::ESI_THROTTLED));
        $this->assertLessThanOrEqual(time() + 60, $this->storage->get(Variables::ESI_THROTTLED));

        $this->storage->set(Variables::ESI_THROTTLED, '');

        $response2 = new Response(200);
        $function2 = $this->obj->__invoke($this->helper->getGuzzleHandler($response2));
        $function2(new Request('GET', 'https://local.host/esi/path'), []);
        $this->assertSame('', $this->storage->get(Variables::ESI_THROTTLED));
    }
}
