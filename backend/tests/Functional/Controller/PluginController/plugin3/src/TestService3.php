<?php

/** @noinspection PhpUnused */
/** @noinspection PhpIllegalPsrClassPathInspection */

declare(strict_types=1);

namespace Tests\Functional\Controller\PluginController;

use Neucore\Plugin\Core\FactoryInterface;
use Neucore\Plugin\Core\OutputInterface;
use Neucore\Plugin\Data\CoreAccount;
use Neucore\Plugin\Data\PluginConfiguration;
use Neucore\Plugin\Exception;
use Neucore\Plugin\GeneralInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

class TestService3 implements GeneralInterface
{
    public function __construct(
        LoggerInterface $logger,
        PluginConfiguration $pluginConfiguration,
        FactoryInterface $factory,
    ) {}

    public function onConfigurationChange(): void {}

    public function request(
        string $name,
        ServerRequestInterface $request,
        ResponseInterface $response,
        ?CoreAccount $coreAccount,
    ): ResponseInterface {
        throw new Exception('Exception from general plugin.');
    }

    public function getNavigationItems(): array
    {
        return [];
    }

    public function command(array $arguments, array $options, OutputInterface $output): void {}
}
