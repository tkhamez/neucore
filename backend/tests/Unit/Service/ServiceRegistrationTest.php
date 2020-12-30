<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use Composer\Autoload\ClassLoader;
use Neucore\Application;
use Neucore\Entity\Service;
use Neucore\Plugin\AccountData;
use Neucore\Plugin\ServiceInterface;
use Neucore\Service\ServiceRegistration;
use PHPUnit\Framework\TestCase;
use Tests\Logger;

class ServiceRegistrationTest extends TestCase
{
    private const PSR_PREFIX = 'Tests\AutoloadTest\\';

    /**
     * @var ClassLoader
     */
    private static $loader;

    /**
     * @var Logger
     */
    private $log;

    public static function setUpBeforeClass(): void
    {
        /** @noinspection PhpIncludeInspection */
        self::$loader = require Application::ROOT_DIR . '/vendor/autoload.php';
    }

    protected function setUp(): void
    {
        $this->log = new Logger('Test');
    }

    protected function tearDown(): void
    {
        self::$loader->setPsr4(self::PSR_PREFIX, []);
    }

    public function testGetServiceObject_MissingPhpClass()
    {
        $service = new Service();
        $service->setConfiguration((string)\json_encode(['phpClass' => 'Test\TestService']));
        $serviceRegistration = new ServiceRegistration($this->log);

        $this->assertNull($serviceRegistration->getServiceObject($service));
    }

    public function testGetServiceObject_PhpClassMissingImplementation()
    {
        $service = new Service();
        $service->setConfiguration((string)\json_encode([
            'phpClass' => ServiceRegistrationTest_TestServiceInvalid::class
        ]));
        $serviceRegistration = new ServiceRegistration($this->log);

        $this->assertNull($serviceRegistration->getServiceObject($service));
    }

    public function testGetServiceObject()
    {
        // add same prefix to test that the new path is added, not replaced
        self::$loader->setPsr4(self::PSR_PREFIX, ['/some/path']);

        $service = new Service();
        $service->setConfiguration((string)\json_encode([
            'phpClass' => 'Tests\AutoloadTest\TestService',
            'psr4Prefix' => self::PSR_PREFIX, // no \ at the end to test that it is added
            'psr4Path' => __DIR__ .  '/AutoloadTest',
        ]));

        $serviceRegistration = new ServiceRegistration($this->log);
        $this->assertInstanceOf(ServiceInterface::class, $serviceRegistration->getServiceObject($service));

        $this->assertSame(
            ['/some/path', __DIR__ .  '/AutoloadTest'],
            self::$loader->getPrefixesPsr4()[self::PSR_PREFIX]
        );
    }

    public function testGetAccounts()
    {
        $serviceRegistration = new ServiceRegistration($this->log);
        $actual = $serviceRegistration->getAccounts(new ServiceRegistrationTest_TestService(), [123, 456]);

        $this->assertSame(1, count($actual));
        $this->assertInstanceOf(AccountData::class, $actual[0]);
        $this->assertSame(123, $actual[0]->getCharacterId());

        $this->assertSame(
            "ServiceController: ServiceInterface::getAccounts must return an array of AccountData objects.",
            $this->log->getHandler()->getRecords()[0]['message']
        );
        $this->assertSame(
            "ServiceController: Character ID does not match.",
            $this->log->getHandler()->getRecords()[1]['message']
        );
    }
}
