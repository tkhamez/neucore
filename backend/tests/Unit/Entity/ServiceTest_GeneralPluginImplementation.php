<?php

namespace Tests\Unit\Entity;

use Neucore\Plugin\CoreAccount;
use Neucore\Plugin\Exception;
use Neucore\Plugin\FactoryInterface;
use Neucore\Plugin\GeneralInterface;
use Neucore\Plugin\PluginConfiguration;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

class ServiceTest_GeneralPluginImplementation implements GeneralInterface
{
    public function __construct(
        LoggerInterface $logger,
        PluginConfiguration $pluginConfiguration,
        FactoryInterface $factory,
    ) {
    }

    public function onConfigurationChange(): void
    {
    }

    public function request(
        string $name,
        ServerRequestInterface $request,
        ResponseInterface $response,
        ?CoreAccount $coreAccount,
    ): ResponseInterface {
        throw new Exception();
    }
}
