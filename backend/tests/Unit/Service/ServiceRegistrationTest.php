<?php
/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Unit\Service;

use Composer\Autoload\ClassLoader;
use Neucore\Application;
use Neucore\Entity\Character;
use Neucore\Entity\Corporation;
use Neucore\Entity\EsiToken;
use Neucore\Entity\EveLogin;
use Neucore\Entity\Group;
use Neucore\Entity\Player;
use Neucore\Entity\Service;
use Neucore\Entity\ServiceConfiguration;
use Neucore\Entity\SystemVariable;
use Neucore\Plugin\CoreGroup;
use Neucore\Plugin\Exception;
use Neucore\Plugin\ServiceAccountData;
use Neucore\Plugin\ServiceInterface;
use Neucore\Service\ServiceRegistration;
use PHPUnit\Framework\TestCase;
use Tests\Client;
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
        $account = $this->helper->getAccountService($this->log, new Client());
        $userAuth = $this->helper->getUserAuthService($this->log, new Client());
        $this->serviceRegistration = new ServiceRegistration($this->log, $userAuth, $account);
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

        // no required group, no logged-in user
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
        $this->assertTrue($this->serviceRegistration->hasRequiredGroups($service));

        // "deactivate" account
        $setting1 = (new SystemVariable(SystemVariable::GROUPS_REQUIRE_VALID_TOKEN))->setValue('1');
        $setting2 = (new SystemVariable(SystemVariable::ACCOUNT_DEACTIVATION_ALLIANCES))->setValue('11');
        $setting3 = (new SystemVariable(SystemVariable::ACCOUNT_DEACTIVATION_CORPORATIONS))->setValue('101');
        $corporation = (new Corporation())->setId(101);
        $character->setCorporation($corporation)->getEsiToken(EveLogin::NAME_DEFAULT)->setValidToken(false);
        $this->helper->getEm()->persist($setting1);
        $this->helper->getEm()->persist($setting2);
        $this->helper->getEm()->persist($setting3);
        $this->helper->getEm()->persist($corporation);
        $this->helper->getEm()->flush();
        $this->assertFalse($this->serviceRegistration->hasRequiredGroups($service));
    }

    public function testGetCoreGroups()
    {
        $character = $this->setupDeactivateAccount();

        $player = (new Player())->addGroup(new Group());
        $this->assertEquals([new CoreGroup(0, '')], $this->serviceRegistration->getCoreGroups($player));

        $player->addCharacter($character); // character with invalid ESI token
        $this->assertEquals([], $this->serviceRegistration->getCoreGroups($player));
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
        $character = $this->setupDeactivateAccount();

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

    private function setupDeactivateAccount(): Character
    {
        $this->helper->emptyDb();
        $setting1 = (new SystemVariable(SystemVariable::GROUPS_REQUIRE_VALID_TOKEN))->setValue('1');
        $setting2 = (new SystemVariable(SystemVariable::ACCOUNT_DEACTIVATION_ALLIANCES))->setValue('11');
        $setting3 = (new SystemVariable(SystemVariable::ACCOUNT_DEACTIVATION_CORPORATIONS))->setValue('101');
        $this->helper->getEm()->persist($setting1);
        $this->helper->getEm()->persist($setting2);
        $this->helper->getEm()->persist($setting3);
        $this->helper->getEm()->flush();
        $corporation = (new Corporation())->setId(101);
        return (new Character())->setCorporation($corporation)->addEsiToken((new EsiToken())->setValidToken(false));
    }
}
