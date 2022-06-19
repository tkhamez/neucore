<?php
/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Unit\Service;

use Composer\Autoload\ClassLoader;
use Doctrine\Persistence\ObjectManager;
use Neucore\Application;
use Neucore\Entity\Character;
use Neucore\Entity\Group;
use Neucore\Entity\Player;
use Neucore\Entity\Service;
use Neucore\Entity\ServiceConfiguration;
use Neucore\Factory\RepositoryFactory;
use Neucore\Plugin\Exception;
use Neucore\Plugin\ServiceAccountData;
use Neucore\Plugin\ServiceInterface;
use Neucore\Service\AccountGroup;
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

    private ObjectManager $om;

    private Helper $helper;

    private ServiceRegistration $serviceRegistration;

    private ServiceInterface $serviceImplementation;

    public static function setUpBeforeClass(): void
    {
        /** @noinspection PhpIncludeInspection */
        self::$loader = require Application::ROOT_DIR . '/vendor/autoload.php';
    }

    protected function setUp(): void
    {
        $this->log = new Logger('Test');
        $this->helper = new Helper();
        $this->om = $this->helper->getObjectManager();
        $repoFactory = RepositoryFactory::getInstance($this->om);
        $accountGroup = new AccountGroup($repoFactory);
        $this->serviceRegistration = new ServiceRegistration($this->log, $repoFactory, $accountGroup);
        $this->serviceImplementation = new ServiceRegistrationTest_TestService(
            $this->log,
            new \Neucore\Plugin\ServiceConfiguration(0, [], '')
        );
        ServiceRegistrationTest_TestService::$getAccountException = false;
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
            $this->serviceRegistration->getAccounts($this->serviceImplementation, [])
        );
    }

    public function testGetAccounts()
    {
        $player = (new Player())->addGroup(new Group());
        $actual = $this->serviceRegistration->getAccounts(
            $this->serviceImplementation,
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
            $this->serviceImplementation,
            [(new Character())->setId(123)->setPlayer($player)]
        );

        $this->assertSame(1, count($actual));
    }

    public function testGetAccounts_Exception()
    {
        $this->expectException(Exception::class);
        $this->serviceRegistration->getAccounts(
            $this->serviceImplementation,
            [(new Character())->setId(202)->setPlayer(new Player())]
        );
    }

    public function testUpdatePlayerAccounts()
    {
        $this->helper->emptyDb();

        $conf1 = new ServiceConfiguration();
        $conf1->actions = [ServiceConfiguration::ACTION_UPDATE_ACCOUNT];
        $conf1->phpClass = ServiceRegistrationTest_TestService::class;
        $service1 = (new Service())->setName('S1')->setConfiguration($conf1);

        $conf2 = new ServiceConfiguration();
        $conf2->phpClass = ServiceRegistrationTest_TestService::class;
        $service2 = (new Service())->setName('S2')->setConfiguration($conf2);

        $conf3 = (new ServiceConfiguration());
        $conf3->phpClass = 'Does\Bot\Exist';
        $conf3->actions = [ServiceConfiguration::ACTION_UPDATE_ACCOUNT];
        $service3 = (new Service())->setName('S3')->setConfiguration($conf3);

        $player1 = (new Player())->setName('P1');
        $char1 = (new Character())->setId(101)->setName('C1')->setPlayer($player1);
        $char2 = (new Character())->setId(102)->setName('C2')->setPlayer($player1);
        $player1->addCharacter($char1);
        $player1->addCharacter($char2);

        $this->om->persist($service1);
        $this->om->persist($service2);
        $this->om->persist($service3);
        $this->om->persist($player1);
        $this->om->persist($char1);
        $this->om->persist($char2);
        $this->om->flush();

        $result = $this->serviceRegistration->updatePlayerAccounts($player1);

        $this->assertSame([
            ['serviceName' => 'S1', 'characterId' => 101],
        ], $result);
        $this->assertSame([
            'ServiceController::updateAllAccounts: S1: Test error',
        ], $this->log->getMessages());
    }

    public function testUpdatePlayerAccounts_GetAccountException()
    {
        $this->helper->emptyDb();

        $conf1 = new ServiceConfiguration();
        $conf1->actions = [ServiceConfiguration::ACTION_UPDATE_ACCOUNT];
        $conf1->phpClass = ServiceRegistrationTest_TestService::class;
        $service1 = (new Service())->setName('S1')->setConfiguration($conf1);

        $player2 = (new Player())->setName('P2');
        $char22 = (new Character())->setId(202)->setName('C22')->setPlayer($player2);
        $player2->addCharacter($char22);

        $this->om->persist($service1);
        $this->om->persist($player2);
        $this->om->persist($char22);
        $this->om->flush();

        $result = $this->serviceRegistration->updatePlayerAccounts($player2);

        $this->assertSame([], $result);
        $this->assertTrue(ServiceRegistrationTest_TestService::$getAccountException);
    }

    public function testUpdateServiceAccount()
    {
        $char = (new Character())->setMain(true);
        $player = new Player();
        $char->setPlayer($player);
        $player->addCharacter($char);

        $result0 = $this->serviceRegistration->updateServiceAccount(null, $this->serviceImplementation);
        $this->assertSame('No character provided.', $result0);

        $result1 = $this->serviceRegistration->updateServiceAccount($char, $this->serviceImplementation);
        $this->assertNull($result1);

        $char->setId(102);
        $result2 = $this->serviceRegistration->updateServiceAccount($char, $this->serviceImplementation);
        $this->assertSame('Test error', $result2);
    }
}
