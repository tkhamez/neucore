<?php declare(strict_types=1);

namespace Tests\Unit\Psr;

use PHPUnit\Framework\TestCase;
use Neucore\Psr\ResponseFactory;

class ResponseFactoryTest extends TestCase
{
    public function testCreateResponse()
    {
        $actual = (new ResponseFactory())->createResponse(404, 'Something was not found.');
        $this->assertSame(404, $actual->getStatusCode());
        $this->assertSame('Something was not found.', $actual->getReasonPhrase());
    }
}
