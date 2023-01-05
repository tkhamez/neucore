<?php

declare(strict_types=1);

namespace Tests\Unit\Plugin;

use Neucore\Plugin\Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Symfony\Component\Yaml\Parser;

class FactoryTest extends TestCase
{

    public function testCreateHttpClient()
    {
        $factory = new Factory();
        $this->assertInstanceOf(ClientInterface::class, $factory->createHttpClient('User Agent'));
    }

    public function testCreateHttpRequest()
    {
        $factory = new Factory();
        $this->assertInstanceOf(
            RequestInterface::class,
            $factory->createHttpRequest('GET', 'https://test.com', [], 'body')
        );
    }

    public function testCreateSymfonyYamlParser()
    {
        $factory = new Factory();
        $this->assertInstanceOf(Parser::class, $factory->createSymfonyYamlParser());
    }
}
