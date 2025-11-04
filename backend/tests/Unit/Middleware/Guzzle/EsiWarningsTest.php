<?php

declare(strict_types=1);

namespace Tests\Unit\Middleware\Guzzle;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Neucore\Middleware\Guzzle\EsiWarnings;
use PHPUnit\Framework\TestCase;
use Tests\Helper;
use Tests\Logger;

class EsiWarningsTest extends TestCase
{
    private Helper $helper;

    private Logger $logger;

    private EsiWarnings $obj;

    protected function setUp(): void
    {
        $this->helper = new Helper();
        $this->helper->emptyDb();

        $this->logger = new Logger();

        $this->obj = new EsiWarnings($this->logger);
    }

    public function testInvokeDeprecated(): void
    {
        $response = new Response(
            200,
            [
                'warning' => ['299 - This route is deprecated'],
                'Warning' => ['299 - This route is deprecated'],
            ],
        );

        $function = $this->obj->__invoke($this->helper->getGuzzleHandler($response));
        $function(new Request('GET', 'https://local.host/esi/path'), []);

        $this->assertSame(2, count($this->logger->getMessages()));
        $this->assertSame(
            'https://local.host/esi/path: 299 - This route is deprecated',
            $this->logger->getMessages()[0],
        );
        $this->assertSame(
            'https://local.host/esi/path: 299 - This route is deprecated',
            $this->logger->getMessages()[1],
        );
    }
}
