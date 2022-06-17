<?php
/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Unit\Service;

use Composer\Autoload\ClassLoader;
use Neucore\Application;
use Neucore\Entity\Character;
use Neucore\Entity\Group;
use Neucore\Entity\Player;
use Neucore\Entity\Service;
use Neucore\Entity\ServiceConfiguration;
use Neucore\Plugin\Exception;
use Neucore\Plugin\ServiceAccountData;
use Neucore\Plugin\ServiceInterface;
use Neucore\Service\ServiceRegistration;
use PHPUnit\Framework\TestCase;
use Tests\Helper;
use Tests\Logger;
/* @phan-suppress-next-line PhanUnreferencedUseNormal */
use Tests\Unit\Service\ServiceRegistration_AutoloadTest\TestService;

class ServiceRegistrationTest extends TestCase
{
    private const PSR_PREFIX = 'Tests\Unit\Service\ServiceRegistration_AutoloadTest';

    /**
     * @var ClassLoader
     */
    private static $loader;

    private Logger $log;

    private Helper $helper;

    private ServiceRegistration $serviceRegistration;

    public static function setUpBeforeClass(): void
    {
        /** @noinspection PhpIncludeInspection */
        self::$loader = require Application::ROOT_DIR . '/vendor/autoload.php';
    }

    protected function setUp(): void
    {
        $this->log = new Logger('Test');
        $this->helper = new Helper();
        $this->serviceRegistration = new ServiceRegistration($this->log);
    }

    protected function tearDown(): void
    {
        self::$loader->setPsr4(self::PSR_PREFIX.'\\', []);
    }

    public function testGetServiceImplementation_MissingPhpClass()
    {
        $service = new Service();
        $conf = new ServiceConfiguration();
        $conf->phpClass = 'Test\TestService';
        $service->setConfiguration($conf);

        $this->assertNull($this->serviceRegistration->getServiceImplementation($service));
    }

    public function testGetServiceImplementation_PhpClassMissingImplementation()
    {
        $service = new Service();
        $conf = new ServiceConfiguration();
        $conf->phpClass = ServiceRegistrationTest_TestServiceInvalid::class;
        $service->setConfiguration($conf);

        $this->assertNull($this->serviceRegistration->getServiceImplementation($service));
    }

    public function testGetServiceImplementation()
    {
        // add same prefix to test that the new path is added, not replaced
        self::$loader->setPsr4(self::PSR_PREFIX.'\\', ['/some/path']);

        $service = new Service();
        $conf = new ServiceConfiguration();
        $conf->phpClass = self::PSR_PREFIX.'\TestService';
        $conf->psr4Prefix = self::PSR_PREFIX;
        $conf->psr4Path = __DIR__ .  '/ServiceRegistration_AutoloadTest';
        $conf->requiredGroups = [1, 2];
        $conf->configurationData = 'other: data';
        $service->setConfiguration($conf);

        /* @var TestService $implementation */
        $implementation = $this->serviceRegistration->getServiceImplementation($service);

        $this->assertInstanceOf(ServiceInterface::class, $implementation);
        /* @phan-suppress-next-line PhanUndeclaredMethod */
        $configuration = $implementation->getServiceConfiguration();
        $this->assertSame(0, $configuration->id);
        $this->assertSame([1, 2], $configuration->requiredGroups);
        $this->assertSame('other: data', $configuration->configurationData);

        $this->assertSame(
            ['/some/path', __DIR__ .  '/ServiceRegistration_AutoloadTest'],
            self::$loader->getPrefixesPsr4()[self::PSR_PREFIX.'\\']
        );
    }

    public function testGetAccounts_NoCharacters()
    {
        $this->assertSame(
            [],
            $this->serviceRegistration->getAccounts(
                new ServiceRegistrationTest_TestService(
                    $this->log,
                    new \Neucore\Plugin\ServiceConfiguration(0, [], '')
                ),
                []
            )
        );
    }

    public function testGetAccounts()
    {
        $player = (new Player())->addGroup(new Group());
        $actual = $this->serviceRegistration->getAccounts(
            new ServiceRegistrationTest_TestService($this->log, new \Neucore\Plugin\ServiceConfiguration(0, [], '')),
            [(new Character())->setId(123)->setPlayer($player)]
        );

        $this->assertSame(1, count($actual));
        $this->assertInstanceOf(ServiceAccountData::class, $actual[0]);
        $this->assertSame(123, $actual[0]->getCharacterId());

        $this->assertSame(
            [
                'ServiceController: ServiceInterface::getAccounts must return an array of AccountData objects.',
                'ServiceController: Character ID does not match.'
            ],
            $this->log->getMessages()
        );
    }

    public function testGetAccounts_DeactivatedGroups()
    {
        $this->helper->emptyDb();
        $character = $this->helper->setupDeactivateAccount();

        $player = (new Player())->addGroup(new Group())->addCharacter($character);
        $actual = $this->serviceRegistration->getAccounts(
            new ServiceRegistrationTest_TestService($this->log, new \Neucore\Plugin\ServiceConfiguration(0, [], '')),
            [(new Character())->setId(123)->setPlayer($player)]
        );

        $this->assertSame(1, count($actual));
    }

    public function testGetAccounts_Exception()
    {
        $this->expectException(Exception::class);
        $this->serviceRegistration->getAccounts(
            new ServiceRegistrationTest_TestService($this->log, new \Neucore\Plugin\ServiceConfiguration(0, [], '')),
            [(new Character())->setId(999)->setPlayer(new Player())]
        );
    }
}
