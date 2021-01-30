<?php

declare(strict_types=1);

namespace Tests\Unit\Middleware\Guzzle;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Neucore\Factory\RepositoryFactory;
use Neucore\Middleware\Guzzle\Esi429Response;
use Neucore\Middleware\Guzzle\EsiHeaders;
use Neucore\Service\ObjectManager;
use Neucore\Storage\SystemVariableStorage;
use Neucore\Storage\Variables;
use PHPUnit\Framework\TestCase;
use Tests\Helper;
use Tests\Logger;

class Esi429ResponseTest extends TestCase
{
    /**
     * @var Helper
     */
    private $helper;

    /**
     * @var SystemVariableStorage
     */
    private $storage;

    /**
     * @var Esi429Response
     */
    private $obj;

    protected function setUp(): void
    {
        $this->helper = new Helper();
        $this->helper->emptyDb();
        $om = $this->helper->getObjectManager();

        $logger = new Logger('test');

        $this->storage = new SystemVariableStorage(new RepositoryFactory($om), new ObjectManager($om, $logger));
        #apcu_clear_cache();
        #$this->storage = new \Neucore\Storage\ApcuStorage();

        $this->obj = new Esi429Response($logger, $this->storage);
    }

    public function testInvoke()
    {
        $response1 = new Response(500, [], (string)\json_encode([
            'error' => 'Undefined 429 response. Original message: Too many errors.You have been temporarily throttled.'
        ]));
        $function1 = $this->obj->__invoke($this->helper->getGuzzleHandler($response1));
        $function1(new Request('GET', 'https://local.host/esi/path'), []);
        $this->assertSame('1', $this->storage->get(Variables::ESI_THROTTLED));

        $response2 = new Response(200);
        $function2 = $this->obj->__invoke($this->helper->getGuzzleHandler($response2));
        $function2(new Request('GET', 'https://local.host/esi/path'), []);
        $this->assertSame('0', $this->storage->get(Variables::ESI_THROTTLED));
    }
}
