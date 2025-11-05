<?php

declare(strict_types=1);

namespace Tests\Unit\Middleware\Guzzle;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Neucore\Factory\RepositoryFactory;
use Neucore\Middleware\Guzzle\EsiErrorLimit;
use Neucore\Service\ObjectManager;
use Neucore\Storage\Variables;
use Neucore\Storage\SystemVariableStorage;
use PHPUnit\Framework\TestCase;
use Tests\Helper;
use Tests\Logger;

class EsiErrorLimitTest extends TestCase
{
    private Helper $helper;

    private SystemVariableStorage $storage;

    private EsiErrorLimit $obj;

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

        $this->obj = new EsiErrorLimit($this->storage);
    }

    public function testInvokeErrorLimit(): void
    {
        $response = new Response(
            200,
            ['X-Esi-Error-Limit-Remain' => ['100'], 'X-Esi-Error-Limit-Reset' => ['60']],
        );

        $function = $this->obj->__invoke($this->helper->getGuzzleHandler($response));
        $function(new Request('GET', 'https://local.host/esi/path'), []);

        $val = \Neucore\Data\EsiErrorLimit::fromJson(
            (string) $this->storage->get(Variables::ESI_ERROR_LIMIT),
        );

        $this->assertSame(100, $val->remain);
        $this->assertSame(60, $val->reset);
        $this->assertLessThanOrEqual(time(), $val->updated);
    }
}
