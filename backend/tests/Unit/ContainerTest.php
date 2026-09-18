<?php

declare(strict_types=1);

namespace Tests\Unit;

use Doctrine\ORM\EntityManagerInterface;
use Neucore\Application;
use Neucore\Container;
use Neucore\Exception\RuntimeException;
use Neucore\Service\Config;
use Neucore\Storage\EsiHeaderStorageInterface;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

class ContainerTest extends TestCase
{
    private static string $logDir = Application::ROOT_DIR . '/var/logs/test';

    public static function tearDownAfterClass(): void
    {
        if (is_dir(self::$logDir)) {
            rmdir(self::$logDir);
        }
    }

    public function testEntityManagerInterfaceFactoryWithContainer(): void
    {
        $app = new Application();
        $fullConfig = $app->loadSettings(true);

        $configContainer = $this->createMock(ContainerInterface::class);
        $configContainer->method('get')
            ->with(Config::class)
            ->willReturn($fullConfig);

        $factory = Container::getDefinitions()[EntityManagerInterface::class];
        $em = $factory($configContainer);

        $this->assertInstanceOf(EntityManagerInterface::class, $em);
    }

    public function testEntityManagerInterfaceFactoryWithNullContainer(): void
    {
        $app = new Application();
        $fullConfig = $app->loadSettings(true);

        $factory = Container::getDefinitions()[EntityManagerInterface::class];
        $em = $factory(null, $fullConfig);

        $this->assertInstanceOf(EntityManagerInterface::class, $em);
    }

    public function testLoggerInterfaceFactory(): void
    {
        $formats = [
            'line',
            'multiline',
            'html',
            'json',
            'loggly',
            'logstash',
            'fluentd',
            'gelf',
        ];

        // Build minimal config with a real writable path (not php://)
        $monologConfig = [
            'path' => self::$logDir,
            'rotation' => 'daily',
        ];

        // Create the log directory to satisfy is_writable() check
        if (!is_dir($monologConfig['path'])) {
            mkdir($monologConfig['path'], 0777, true);
        }

        foreach ($formats as $format) {
            $monologConfig['format'] = $format;

            $fullConfig = new Config(['monolog' => $monologConfig]);

            $configContainer = $this->createMock(ContainerInterface::class);
            $configContainer->method('get')
                ->with(Config::class)
                ->willReturn($fullConfig);

            $factory = Container::getDefinitions()[LoggerInterface::class];
            $logger = $factory($configContainer);

            $this->assertInstanceOf(LoggerInterface::class, $logger);
        }
    }
}
