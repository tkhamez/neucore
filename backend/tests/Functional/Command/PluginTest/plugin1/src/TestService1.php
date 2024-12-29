<?php

/** @noinspection PhpUnused */
/** @noinspection PhpIllegalPsrClassPathInspection */

declare(strict_types=1);

namespace Tests\Functional\Command\PluginTest;

use Neucore\Plugin\Core\FactoryInterface;
use Neucore\Plugin\Core\OutputInterface;
use Neucore\Plugin\Data\CoreAccount;
use Neucore\Plugin\Data\PluginConfiguration;
use Neucore\Plugin\Exception;
use Neucore\Plugin\GeneralInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

class TestService1 implements GeneralInterface
{
    public static array $arguments = [];

    public static array $options = [];

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
        throw new Exception();
    }

    public function getNavigationItems(): array
    {
        return [];
    }

    public function command(array $arguments, array $options, OutputInterface $output): void
    {
        self::$arguments = $arguments;
        self::$options = $options;
        $output->write('Test done.');
    }
}
