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
use Tests\Client;
use Tests\Helper;
use Tests\Logger;

class ServiceRegistrationTest extends TestCase
{
    private const PSR_PREFIX = 'Tests\ServiceRegistration_AutoloadTest';

    /**
     * @var ClassLoader
     */
    private static $loader;

    /**
     * @var Logger
     */
    private $log;

    /**
     * @var Helper
     */
    private $helper;

    /**
     * @var ServiceRegistration
     */
    private $serviceRegistration;

    public static function setUpBeforeClass(): void
    {
        /** @noinspection PhpIncludeInspection */
        self::$loader = require Application::ROOT_DIR . '/vendor/autoload.php';
    }

    protected function setUp(): void
    {
        $this->log = new Logger('Test');
        $this->helper = new Helper();
        $userAuth = $this->helper->getUserAuthService($this->log, new Client());
        $this->serviceRegistration = new ServiceRegistration($this->log, $userAuth);
    }

    protected function tearDown(): void
    {
        self::$loader->setPsr4(self::PSR_PREFIX.'\\', []);
    }

    public function testHasRequiredGroups()
    {
        $this->helper->emptyDb();
        $group = (new Group())->setName('G1');
        $this->helper->getEm()->persist($group);
        $this->helper->getEm()->flush();

        // no required group, no logged in user
        $service = new Service();
        $this->assertFalse($this->serviceRegistration->hasRequiredGroups($service));

        // log in user
        $character = $this->helper->addCharacterMain('Test User', 800);
        $_SESSION['character_id'] = 800;
        $this->assertTrue($this->serviceRegistration->hasRequiredGroups($service));

        // add require group
        $conf = new ServiceConfiguration();
        $conf->requiredGroups = [$group->getId()];
        $service->setConfiguration($conf);
        $this->assertFalse($this->serviceRegistration->hasRequiredGroups($service));

        // add group to player
        $character->getPlayer()->addGroup($group);
        $this->assertTrue($this->serviceRegistration->hasRequiredGroups($service));

        // add another require group
        $conf->requiredGroups[] = 2;
        $service->setConfiguration($conf);
        $this->assertFalse($this->serviceRegistration->hasRequiredGroups($service));
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
        $conf->phpClass = 'Tests\ServiceRegistration_AutoloadTest\TestService';
        $conf->psr4Prefix = self::PSR_PREFIX;
        $conf->psr4Path = __DIR__ .  '/ServiceRegistration_AutoloadTest';
        $service->setConfiguration($conf);

        $this->assertInstanceOf(
            ServiceInterface::class,
            $this->serviceRegistration->getServiceImplementation($service)
        );

        $this->assertSame(
            ['/some/path', __DIR__ .  '/ServiceRegistration_AutoloadTest'],
            self::$loader->getPrefixesPsr4()[self::PSR_PREFIX.'\\']
        );
    }

    public function testGetAccounts_NoCharacters()
    {
        $this->assertSame(
            [],
            $this->serviceRegistration->getAccounts(new ServiceRegistrationTest_TestService($this->log), [])
        );
    }

    public function testGetAccounts()
    {
        $p = new Player();
        $actual = $this->serviceRegistration->getAccounts(
            new ServiceRegistrationTest_TestService($this->log),
            [(new Character())->setId(123)->setPlayer($p), (new Character())->setId(456)->setPlayer($p)]
        );

        $this->assertSame(1, count($actual));
        $this->assertInstanceOf(ServiceAccountData::class, $actual[0]);
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

    public function testGetAccounts_Exception()
    {
        $this->expectException(Exception::class);
        $this->serviceRegistration->getAccounts(
            new ServiceRegistrationTest_TestService($this->log),
            [(new Character())->setId(999)->setPlayer(new Player())]
        );
    }
}
