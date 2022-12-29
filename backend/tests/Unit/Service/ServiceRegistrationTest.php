<?php
/** @noinspection DuplicatedCode */
/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Unit\Service;

require_once __DIR__ . '/ServiceRegistration/plugin-acc1/src/TestService1.php';

use Composer\Autoload\ClassLoader;
use Doctrine\Persistence\ObjectManager;
use Neucore\Application;
use Neucore\Data\PluginConfigurationDatabase;
use Neucore\Entity\Character;
use Neucore\Entity\Group;
use Neucore\Entity\Player;
use Neucore\Entity\Service;
use Neucore\Factory\RepositoryFactory;
use Neucore\Plugin\Exception;
use Neucore\Plugin\ServiceAccountData;
use Neucore\Plugin\ServiceConfiguration;
use Neucore\Plugin\ServiceInterface;
use Neucore\Service\AccountGroup;
use Neucore\Service\Config;
use Neucore\Service\ServiceRegistration;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Parser;
use Tests\Helper;
use Tests\Logger;
/* @phan-suppress-next-line PhanUnreferencedUseNormal */
use Tests\Unit\Service\ServiceRegistration\plugin\src\TestService;
use Tests\Unit\Service\ServiceRegistration\plugin\src\TestService1;

/* @phan-suppress-next-line PhanUnreferencedUseNormal */
class ServiceRegistrationTest extends TestCase
{
    private const PSR_PREFIX = 'Tests\Unit\Service\ServiceRegistration\plugin\src';

    private static ClassLoader $loader;

    private Logger $log;

    private ObjectManager $om;

    private Helper $helper;

    private ServiceRegistration $serviceRegistration;

    private ServiceInterface $testService1Impl;

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
        $accountGroup = new AccountGroup($repoFactory, $this->om);
        $config = new Config(['plugins_install_dir' => __DIR__ . '/ServiceRegistration']);
        $this->serviceRegistration = new ServiceRegistration(
            $this->log,
            $repoFactory,
            $accountGroup,
            $config,
            new Parser()
        );
        $this->testService1Impl = new TestService1(
            $this->log,
            new ServiceConfiguration(0, [], '')
        );
        TestService1::$getAccountException = false;
        TestService1::$moved = null;
    }

    protected function tearDown(): void
    {
        self::$loader->setPsr4(self::PSR_PREFIX.'\\', []);
    }

    public function testGetConfigurationFromConfigFile_Errors()
    {
        $actual1 = $this->serviceRegistration->getConfigurationFromConfigFile('does-not-exist');
        $this->assertNull($actual1);

        $actual2 = $this->serviceRegistration->getConfigurationFromConfigFile('plugin-parse-error');
        $this->assertNull($actual2);

        $actual3 = $this->serviceRegistration->getConfigurationFromConfigFile('plugin-error-string');
        $this->assertNull($actual3);

        $baseDir = __DIR__ . '/ServiceRegistration';
        $this->assertSame(
            [
                "File does not exist $baseDir/does-not-exist/plugin.yml",
                "Malformed inline YAML string at line 2.",
                "Invalid file content in $baseDir/plugin-error-string/plugin.yml",
            ],
            $this->log->getMessages()
        );
    }

    public function testGetConfigurationFromConfigFile()
    {
        $actual = $this->serviceRegistration->getConfigurationFromConfigFile('plugin-name');
        if (!$actual) {
            $this->fail();
        }

        $this->assertSame('Test', $actual->name);
        $this->assertSame('service', $actual->type);
        $this->assertSame('plugin-name', $actual->directoryName);
        $this->assertSame(self::PSR_PREFIX.'\TestService', $actual->phpClass);
        $this->assertSame(self::PSR_PREFIX, $actual->psr4Prefix);
        $this->assertSame('src', $actual->psr4Path);
        $this->assertSame(true, $actual->oneAccount);
        $this->assertSame(['username'], $actual->properties);
        $this->assertSame(true, $actual->showPassword);
        $this->assertSame(['update-account'], $actual->actions);
        $this->assertSame(1, count($actual->URLs));
        $this->assertSame('/plugin/{plugin_id}/action', $actual->URLs[0]->url);
        $this->assertSame('Example', $actual->URLs[0]->title);
        $this->assertSame('_self', $actual->URLs[0]->target);
        $this->assertSame('text top', $actual->textTop);
        $this->assertSame('text account', $actual->textAccount);
        $this->assertSame('text register', $actual->textRegister);
        $this->assertSame('text pending', $actual->textPending);
        $this->assertSame('config data', $actual->configurationData);
    }

    public function testGetService_InvalidId()
    {
        $this->assertNull($this->serviceRegistration->getService(1));
    }

    public function testGetService_InvalidYamlFile()
    {
        $conf = new PluginConfigurationDatabase();
        $conf->directoryName = 'plugin-parse-error';
        $service = (new Service())->setName('S1')->setConfigurationDatabase($conf);
        $this->om->persist($service);
        $this->om->flush();

        $this->assertNull($this->serviceRegistration->getService(1));
    }

    public function testGetService_WithoutConfigurations()
    {
        $service = (new Service())->setName('S1');
        $this->om->persist($service);
        $this->om->flush();

        $actual = $this->serviceRegistration->getService($service->getId());

        $this->assertNull($actual->getConfigurationFile());
        $this->assertNull($actual->getConfigurationDatabase());
    }

    public function testGetService_Ok()
    {
        $conf = new PluginConfigurationDatabase();
        $conf->directoryName = 'plugin-name';
        $conf->textTop = 'top text';
        $service = (new Service())->setName('S1')->setConfigurationDatabase($conf);
        $this->om->persist($service);
        $this->om->flush();
        $this->om->clear();

        $actual = $this->serviceRegistration->getService($service->getId());

        // from plugin.yml
        $this->assertSame('Test', $actual->getConfigurationFile()?->name);
        $this->assertSame('service', $actual->getConfigurationFile()->type);

        // from database
        $this->assertSame('top text', $actual->getConfigurationDatabase()?->textTop);
    }

    public function testGetServiceImplementation_InvalidConfiguration()
    {
        $service = new Service();
        $conf = new PluginConfigurationDatabase();
        $conf->directoryName = 'plugin-parse-error';
        $service->setConfigurationDatabase($conf);

        $this->assertNull($this->serviceRegistration->getServiceImplementation($service));
    }

    public function testGetServiceImplementation_MissingPhpClass()
    {
        $service = new Service();
        $conf = new PluginConfigurationDatabase();
        $conf->directoryName = 'plugin-missing-class-with-action';
        $service->setConfigurationDatabase($conf);

        $this->assertNull($this->serviceRegistration->getServiceImplementation($service));
    }

    public function testGetServiceImplementation_PhpClassMissingImplementation()
    {
        $service = new Service();
        $conf = new PluginConfigurationDatabase();
        $conf->directoryName = 'plugin-class-missing-impl';
        $service->setConfigurationDatabase($conf);

        $this->assertNull($this->serviceRegistration->getServiceImplementation($service));
    }

    public function testGetServiceImplementation()
    {
        // add same prefix to test, so that the new path is added, not replaced
        self::$loader->setPsr4(self::PSR_PREFIX.'\\', ['/some/path']);

        $service = new Service();
        $conf = new PluginConfigurationDatabase();
        $conf->directoryName = 'plugin-name';
        $conf->requiredGroups = [1, 2];
        $conf->configurationData = 'other: data';
        $service->setConfigurationDatabase($conf);

        /* @var TestService $implementation */
        $implementation = $this->serviceRegistration->getServiceImplementation($service);

        $this->assertInstanceOf(ServiceInterface::class, $implementation);
        /* @phan-suppress-next-line PhanUndeclaredMethod */
        $configuration = $implementation->getServiceConfiguration();
        $this->assertSame(0, $configuration->id);
        $this->assertSame([1, 2], $configuration->requiredGroups);
        $this->assertSame('other: data', $configuration->configurationData);

        $this->assertSame(
            ['/some/path', __DIR__ .  '/ServiceRegistration/plugin-name/src'],
            self::$loader->getPrefixesPsr4()[self::PSR_PREFIX.'\\']
        );
    }

    public function testGetAccounts_NoCharacters()
    {
        $this->assertSame(
            [],
            $this->serviceRegistration->getAccounts($this->testService1Impl, [])
        );
    }

    public function testGetAccounts()
    {
        $player = (new Player())->addGroup(new Group());
        $actual = $this->serviceRegistration->getAccounts(
            $this->testService1Impl,
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
            $this->testService1Impl,
            [(new Character())->setId(123)->setPlayer($player)]
        );

        $this->assertSame(1, count($actual));
    }

    public function testGetAccounts_Exception()
    {
        $this->expectException(Exception::class);
        $this->serviceRegistration->getAccounts(
            $this->testService1Impl,
            [(new Character())->setId(202)->setPlayer(new Player())]
        );
    }

    public function testUpdatePlayerAccounts_Ok()
    {
        $this->helper->emptyDb();

        $conf1 = new PluginConfigurationDatabase();
        $conf1->directoryName = 'plugin-acc1'; // with action
        $service1 = (new Service())->setName('S1')->setConfigurationDatabase($conf1);

        $conf2 = new PluginConfigurationDatabase();
        $conf2->directoryName = 'plugin-acc2'; // no action
        $service2 = (new Service())->setName('S2')->setConfigurationDatabase($conf2);

        $conf3 = (new PluginConfigurationDatabase());
        $conf3->directoryName = 'plugin-missing-class-with-action'; // with action
        $service3 = (new Service())->setName('S3')->setConfigurationDatabase($conf3);

        $player1 = (new Player())->setName('P1');
        $player2 = (new Player())->setName('P2');
        $char1 = (new Character())->setId(101)->setName('C1')->setPlayer($player1);
        $char2 = (new Character())->setId(102)->setName('C2')->setPlayer($player1);
        $player1->addCharacter($char1);
        $player1->addCharacter($char2);

        $this->om->persist($service1);
        $this->om->persist($service2);
        $this->om->persist($service3);
        $this->om->persist($player1);
        $this->om->persist($player2);
        $this->om->persist($char1);
        $this->om->persist($char2);
        $this->om->flush();

        $result = $this->serviceRegistration->updatePlayerAccounts($player1, $player2);

        $this->assertSame([['serviceName' => 'S1', 'characterId' => 101]], $result);
        $this->assertSame(['ServiceController::updateAllAccounts: S1: Test error'], $this->log->getMessages());
        $this->assertSame($player2->getId() . ' -> ' . $player1->getId(), TestService1::$moved);
    }

    public function testUpdatePlayerAccounts_YamlException()
    {
        $this->helper->emptyDb();

        $conf = new PluginConfigurationDatabase();
        $conf->directoryName = 'plugin-error-string';
        $service = (new Service())->setName('S1')->setConfigurationDatabase($conf);

        $player = (new Player())->setName('P1');

        $this->om->persist($service);
        $this->om->persist($player);
        $this->om->flush();

        $result = $this->serviceRegistration->updatePlayerAccounts($player);

        $this->assertSame([], $result);
        $this->assertSame(
            ['Invalid file content in '.__DIR__.'/ServiceRegistration/plugin-error-string/plugin.yml'],
            $this->log->getMessages()
        );
    }

    public function testUpdatePlayerAccounts_GetAccountException()
    {
        $this->helper->emptyDb();

        $conf1 = new PluginConfigurationDatabase();
        $conf1->directoryName = 'plugin-acc1'; // with action
        $service1 = (new Service())->setName('S1')->setConfigurationDatabase($conf1);

        $player2 = (new Player())->setName('P2');
        $char22 = (new Character())->setId(202)->setName('C22')->setPlayer($player2);
        $player2->addCharacter($char22);

        $this->om->persist($service1);
        $this->om->persist($player2);
        $this->om->persist($char22);
        $this->om->flush();

        $result = $this->serviceRegistration->updatePlayerAccounts($player2);

        $this->assertSame([], $result);
        $this->assertTrue(TestService1::$getAccountException);
    }

    public function testUpdateServiceAccount()
    {
        $char = (new Character())->setMain(true);
        $player = new Player();
        $char->setPlayer($player);
        $player->addCharacter($char);

        $result0 = $this->serviceRegistration->updateServiceAccount(null, $this->testService1Impl);
        $this->assertSame('No character provided.', $result0);

        $result1 = $this->serviceRegistration->updateServiceAccount($char, $this->testService1Impl);
        $this->assertNull($result1);

        $char->setId(102);
        $result2 = $this->serviceRegistration->updateServiceAccount($char, $this->testService1Impl);
        $this->assertSame('Test error', $result2);
    }
}
