<?php

declare(strict_types=1);

namespace Tests\Unit;

use DI\ContainerBuilder;
use DI\DependencyException;
use DI\NotFoundException;
use Doctrine\ORM\EntityManagerInterface;
use Neucore\Application;
use Neucore\Container;
use Neucore\Exception\RuntimeException;
use Neucore\Factory\RepositoryFactory;
use Neucore\Service\Config;
use Neucore\Service\ObjectManager;
use Neucore\Storage\Implementation\ApcuStorage;
use Neucore\Storage\Implementation\DatabaseStorage;
use Neucore\Storage\Interface\ApiRateLimitStorage;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Tests\Helper;
use Tests\Logger;

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

    public function testApiRateLimitStorageFactory(): void
    {
        // apc
        $config = new Config(['rate_limit' => ['storage' => Container::STORAGE_APC]]);
        $configContainer = $this->createMock(ContainerInterface::class);
        $configContainer->method('get')->willReturn($config);
        $factory = Container::getDefinitions()[ApiRateLimitStorage::class];
        $this->assertInstanceOf(ApcuStorage::class, $factory($configContainer));

        // database
        $om = (new Helper())->getObjectManager();
        $logger = new Logger();
        $config = new Config(['rate_limit' => ['storage' => Container::STORAGE_DATABASE]]);
        $configContainer = $this->createMock(ContainerInterface::class);
        $configContainer->method('get')->willReturnCallback(
            fn(string $id) => match ($id) {
                Config::class => $config,
                RepositoryFactory::class => new RepositoryFactory($om),
                ObjectManager::class => new ObjectManager($om, $logger),
                default => throw new RuntimeException("Unexpected container entry $id."),
            },
        );
        $factory = Container::getDefinitions()[ApiRateLimitStorage::class];
        $this->assertInstanceOf(DatabaseStorage::class, $factory($configContainer));
    }

    public function testApiRateLimitStorageFactoryInvalid(): void
    {
        $config = new Config(['rate_limit' => ['storage' => 'invalid']]);
        $configContainer = $this->createMock(ContainerInterface::class);
        $configContainer->method('get')->willReturn($config);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid storage type invalid.');
        $factory = Container::getDefinitions()[ApiRateLimitStorage::class];
        $factory($configContainer);
    }

    /**
     * @throws DependencyException
     * @throws NotFoundException
     * @throws \Exception
     */
    public function testStorageFactoryInCompiledContainer(): void
    {
        // The compiled container (used in production) inlines the factory closures into its own
        // class, so private methods of Container are not accessible from the compiled code
        // (getStorage() must be public).

        $cacheDir = Application::ROOT_DIR . '/var/cache/test/' . uniqid('', true);

        $builder = new ContainerBuilder();
        $builder->enableCompilation($cacheDir);
        $builder->addDefinitions(Container::getDefinitions());
        $container = $builder->build();

        try {
            $config = new Config(['rate_limit' => ['storage' => Container::STORAGE_APC]]);
            $container->set(Config::class, $config);

            // In case APC is not available in the test environment, the factory falls back
            // to the database storage, which requires these two entries.
            $om = (new Helper())->getObjectManager();
            $container->set(RepositoryFactory::class, new RepositoryFactory($om));
            $container->set(ObjectManager::class, new ObjectManager($om, new Logger()));

            $this->assertInstanceOf(
                ApiRateLimitStorage::class,
                $container->get(ApiRateLimitStorage::class),
            );
        } finally {
            // Clean-up the compiled container cache
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($cacheDir, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST,
            );
            foreach ($iterator as $file) { /* @var $file \SplFileInfo */
                $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            }
            rmdir($cacheDir);
        }
    }
}
