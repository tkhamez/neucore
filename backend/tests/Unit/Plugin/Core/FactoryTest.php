<?php

declare(strict_types=1);

namespace Tests\Unit\Plugin\Core;

use Neucore\Plugin\Core\AccountInterface;
use Neucore\Plugin\Core\EsiClientInterface;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Symfony\Component\Yaml\Parser;
use Tests\Helper;

class FactoryTest extends TestCase
{
    public function testCreateHttpClient()
    {
        $factory = Helper::getPluginFactory();
        $this->assertInstanceOf(ClientInterface::class, $factory->createHttpClient('User Agent'));
    }

    public function testCreateHttpRequest()
    {
        $factory = Helper::getPluginFactory();
        $this->assertInstanceOf(
            RequestInterface::class,
            $factory->createHttpRequest('GET', 'https://test.com', [], 'body')
        );
    }

    public function testCreateSymfonyYamlParser()
    {
        $factory = Helper::getPluginFactory();
        $this->assertInstanceOf(Parser::class, $factory->createSymfonyYamlParser());
    }

    public function testGetEsiClient()
    {
        $factory = Helper::getPluginFactory();
        $this->assertInstanceOf(EsiClientInterface::class, $factory->getEsiClient());
    }

    public function testGetAccount()
    {
        $factory = Helper::getPluginFactory();
        $this->assertInstanceOf(AccountInterface::class, $factory->getAccount());
    }
}
