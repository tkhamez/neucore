<?php declare(strict_types=1);

namespace Tests\Unit\Factory;

use Neucore\Factory\ResponseFactory;
use PHPUnit\Framework\TestCase;

class ResponseFactoryTest extends TestCase
{
    public function testCreateResponse()
    {
        $actual = (new ResponseFactory())->createResponse(404, 'Something was not found.');
        $this->assertSame(404, $actual->getStatusCode());
        $this->assertSame('Something was not found.', $actual->getReasonPhrase());
    }
}
