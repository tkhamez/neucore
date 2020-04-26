<?php

declare(strict_types=1);

namespace Tests\Unit\Factory;

use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ResponseFactory;

class ResponseFactoryTest extends TestCase
{
    public function testCreateResponse()
    {
        $actual = (new ResponseFactory())->createResponse(404, 'Something was not found.');
        $this->assertSame(404, $actual->getStatusCode());
        $this->assertSame('Something was not found.', $actual->getReasonPhrase());
    }
}
