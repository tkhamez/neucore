<?php
/** @noinspection DuplicatedCode */
/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Unit\Service;

require_once __DIR__ . '/PluginService/plugin-acc1/src/TestService1.php';

use Composer\Autoload\ClassLoader;
use Doctrine\Persistence\ObjectManager;
use Neucore\Application;
use Neucore\Data\PluginConfigurationDatabase;
use Neucore\Data\PluginConfigurationFile;
use Neucore\Entity\Character;
use Neucore\Entity\Group;
use Neucore\Entity\Player;
use Neucore\Entity\Plugin;
use Neucore\Factory\RepositoryFactory;
use Neucore\Plugin\Exception;
use Neucore\Plugin\GeneralPluginInterface;
use Neucore\Plugin\ServiceAccountData;
use Neucore\Plugin\PluginConfiguration;
use Neucore\Plugin\ServiceInterface;
use Neucore\Service\AccountGroup;
use Neucore\Service\Config;
use Neucore\Service\PluginService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Parser;
use Tests\Helper;
use Tests\Logger;
/* @phan-suppress-next-line PhanUnreferencedUseNormal */
use Tests\Unit\Service\PluginService\plugin\src\TestService;
use Tests\Unit\Service\PluginService\plugin\src\TestService1;

/* @phan-suppress-next-line PhanUnreferencedUseNormal */
class PluginServiceTest extends TestCase
{
    private const PSR_PREFIX = 'Tests\Unit\Service\PluginService\plugin\src';

    private static ClassLoader $loader;

    private Logger $log;

    private ObjectManager $om;

    private Helper $helper;

    private PluginService $pluginService;

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
        $config = new Config(['plugins_install_dir' => __DIR__ . '/PluginService']);
        $this->pluginService = new PluginService(
            $this->log,
            $repoFactory,
            $accountGroup,
            $config,
            new Parser()
        );
        $this->testService1Impl = new TestService1(
            $this->log,
            new PluginConfiguration(0, [], '')
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
        $actual1 = $this->pluginService->getConfigurationFromConfigFile('does-not-exist');
        $this->assertNull($actual1);

        $actual2 = $this->pluginService->getConfigurationFromConfigFile('plugin-parse-error');
        $this->assertNull($actual2);

        $actual3 = $this->pluginService->getConfigurationFromConfigFile('plugin-error-string');
        $this->assertNull($actual3);

        $baseDir = __DIR__ . '/PluginService';
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
        $actual = $this->pluginService->getConfigurationFromConfigFile('plugin-name');
        if (!$actual) {
            $this->fail();
        }

        $this->assertSame('Test', $actual->name);
        $this->assertSame([], $actual->types); // does not load implementation, so this is empty
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

    public function testGetPlugin_InvalidId()
    {
        $this->assertNull($this->pluginService->getPlugin(1));
    }

    public function testGetPlugin_InvalidYamlFile()
    {
        $conf = new PluginConfigurationDatabase();
        $conf->directoryName = 'plugin-parse-error';
        $service = (new Plugin())->setName('S1')->setConfigurationDatabase($conf);
        $this->om->persist($service);
        $this->om->flush();

        $this->assertNull($this->pluginService->getPlugin(1));
    }

    public function testGetPlugin_WithoutConfigurations()
    {
        $service = (new Plugin())->setName('S1');
        $this->om->persist($service);
        $this->om->flush();

        $actual = $this->pluginService->getPlugin($service->getId());

        $this->assertNull($actual->getConfigurationFile());
        $this->assertNull($actual->getConfigurationDatabase());
    }

    public function testGetPlugin_Ok()
    {
        $conf = new PluginConfigurationDatabase();
        $conf->directoryName = 'plugin-name';
        $conf->textTop = 'top text';
        $service = (new Plugin())->setName('S1')->setConfigurationDatabase($conf);
        $this->om->persist($service);
        $this->om->flush();
        $this->om->clear();

        $actual = $this->pluginService->getPlugin($service->getId());

        // from plugin.yml
        $this->assertSame('Test', $actual->getConfigurationFile()?->name);
        $this->assertSame([], $actual->getConfigurationFile()->types); // does not load implementation, so empty

        // from database
        $this->assertSame('top text', $actual->getConfigurationDatabase()?->textTop);
    }

    public function testGetPluginImplementation_InvalidConfiguration()
    {
        $service = new Plugin();
        $conf = new PluginConfigurationDatabase();
        $conf->directoryName = 'plugin-parse-error';
        $service->setConfigurationDatabase($conf);

        $this->assertNull($this->pluginService->getPluginImplementation($service));
    }

    public function testGetPluginImplementation_MissingConfiguration()
    {
        $service = new Plugin();
        $conf = new PluginConfigurationFile();
        $conf->directoryName = 'plugin-name';
        $service->setConfigurationFile($conf);

        $this->assertNull($this->pluginService->getPluginImplementation($service));
    }

    public function testGetPluginImplementation_MissingPhpClass()
    {
        $service = new Plugin();
        $conf = new PluginConfigurationDatabase();
        $conf->directoryName = 'plugin-missing-class-with-action';
        $service->setConfigurationDatabase($conf);

        $this->assertNull($this->pluginService->getPluginImplementation($service));
    }

    public function testGetPluginImplementation()
    {
        // add same prefix to test, so that the new path is added, not replaced
        self::$loader->setPsr4(self::PSR_PREFIX.'\\', ['/some/path']);

        $service = new Plugin();
        $conf = new PluginConfigurationDatabase();
        $conf->directoryName = 'plugin-name';
        $conf->requiredGroups = [1, 2];
        $conf->configurationData = 'other: data';
        $service->setConfigurationDatabase($conf);

        /* @var TestService $implementation */
        $implementation = $this->pluginService->getPluginImplementation($service);

        $this->assertSame([PluginConfigurationFile::TYPE_SERVICE], $service->getConfigurationFile()->types);

        $this->assertNotInstanceOf(GeneralPluginInterface::class, $implementation);
        $this->assertInstanceOf(ServiceInterface::class, $implementation);

        /* @phan-suppress-next-line PhanUndeclaredMethod */
        $configuration = $implementation->getPluginConfiguration();
        $this->assertSame(0, $configuration->id);
        $this->assertSame([1, 2], $configuration->requiredGroups);
        $this->assertSame('other: data', $configuration->configurationData);

        $this->assertSame(
            ['/some/path', __DIR__ .  '/PluginService/plugin-name/src'],
            self::$loader->getPrefixesPsr4()[self::PSR_PREFIX.'\\']
        );
    }

    public function testLoadPluginImplementation_PhpClassMissingImplementation()
    {
        $conf = new PluginConfigurationFile();
        $conf->directoryName = 'plugin-class-missing-impl';
        $conf->phpClass = 'Tests\Unit\Service\PluginService\plugin\src\Invalid';
        $conf->psr4Prefix = 'Tests\Unit\Service\PluginService\plugin\src';
        $conf->psr4Path = 'src';

        $this->assertNull($this->pluginService->loadPluginImplementation($conf));
    }

    public function testLoadPluginImplementation_ClassDoesNotImplement()
    {
        $conf = new PluginConfigurationFile();
        $conf->directoryName = 'plugin-class-missing-impl';
        $conf->phpClass = 'Tests\Unit\Service\PluginService\plugin\src\ServiceInvalid';
        $conf->psr4Prefix = 'Tests\Unit\Service\PluginService\plugin\src';
        $conf->psr4Path = 'src';

        $this->assertNull($this->pluginService->loadPluginImplementation($conf));
    }

    public function testLoadPluginImplementation_Service()
    {
        $conf = new PluginConfigurationFile();
        $conf->directoryName = 'plugin-name';
        $conf->phpClass = 'Tests\Unit\Service\PluginService\plugin\src\TestService';
        $conf->psr4Prefix = 'Tests\Unit\Service\PluginService\plugin\src';
        $conf->psr4Path = 'src';

        $this->assertSame(
            'Tests\Unit\Service\PluginService\plugin\src\TestService',
            $this->pluginService->loadPluginImplementation($conf)
        );
        $this->assertSame([PluginConfigurationFile::TYPE_SERVICE], $conf->types);
    }

    public function testLoadPluginImplementation_GeneralPlugin()
    {
        $conf = new PluginConfigurationFile();
        $conf->directoryName = 'plugin-general';
        $conf->phpClass = 'Tests\Unit\Service\PluginService\plugin\src\TestPlugin';
        $conf->psr4Prefix = 'Tests\Unit\Service\PluginService\plugin\src';
        $conf->psr4Path = 'src';

        $this->assertSame(
            'Tests\Unit\Service\PluginService\plugin\src\TestPlugin',
            $this->pluginService->loadPluginImplementation($conf)
        );
        $this->assertSame([PluginConfigurationFile::TYPE_GENERAL], $conf->types);
    }

    public function testLoadPluginImplementation_ImplementsBoth()
    {
        $conf = new PluginConfigurationFile();
        $conf->directoryName = 'plugin-both';
        $conf->phpClass = 'Tests\Unit\Service\PluginService\plugin\src\TestBoth';
        $conf->psr4Prefix = 'Tests\Unit\Service\PluginService\plugin\src';
        $conf->psr4Path = 'src';

        $this->assertSame(
            'Tests\Unit\Service\PluginService\plugin\src\TestBoth',
            $this->pluginService->loadPluginImplementation($conf)
        );
        $this->assertSame(
            [PluginConfigurationFile::TYPE_GENERAL, PluginConfigurationFile::TYPE_SERVICE],
            $conf->types
        );
    }

    public function testGetPluginWithImplementation()
    {
        $conf1 = new PluginConfigurationDatabase();
        $conf1->directoryName = 'plugin-name';
        $conf1->active = true;
        $service1 = (new Plugin())->setName('S1')->setConfigurationDatabase($conf1);

        $conf2 = new PluginConfigurationDatabase();
        $conf2->directoryName = 'plugin-name';
        $conf2->active = false;
        $service2 = (new Plugin())->setName('S2')->setConfigurationDatabase($conf2);

        $conf3 = new PluginConfigurationDatabase();
        $conf3->directoryName = 'plugin-name';
        $conf3->active = true;
        $service3 = (new Plugin())->setName('S3')->setConfigurationDatabase($conf3);

        $conf4 = new PluginConfigurationDatabase();
        $conf4->directoryName = 'plugin-general';
        $conf4->active = true;
        $service4 = (new Plugin())->setName('S4')->setConfigurationDatabase($conf4);

        $conf5 = new PluginConfigurationDatabase();
        $conf5->directoryName = 'plugin-both';
        $conf5->active = true;
        $service5 = (new Plugin())->setName('S5')->setConfigurationDatabase($conf5);

        $this->om->persist($service1);
        $this->om->persist($service2);
        $this->om->persist($service3);
        $this->om->persist($service4);
        $this->om->persist($service5);
        $this->om->flush();
        $this->om->clear();

        $actual = $this->pluginService->getPluginWithImplementation(
            [$service1->getId(), $service2->getId(), $service4->getId(), $service5->getId()]
        );

        $this->assertSame(3, count($actual));
        $this->assertSame($service1->getId(), $actual[0]->getId());
        $this->assertSame($service4->getId(), $actual[1]->getId());
        $this->assertSame($service5->getId(), $actual[2]->getId());
        $this->assertInstanceOf(ServiceInterface::class, $actual[0]->getServiceImplementation());
        $this->assertNull($actual[1]->getServiceImplementation());
        $this->assertInstanceOf(ServiceInterface::class, $actual[2]->getServiceImplementation());
        $this->assertNull($actual[0]->getGeneralPluginImplementation());
        $this->assertInstanceOf(GeneralPluginInterface::class, $actual[1]->getGeneralPluginImplementation());
        $this->assertInstanceOf(GeneralPluginInterface::class, $actual[2]->getGeneralPluginImplementation());

        $this->assertSame([PluginConfigurationFile::TYPE_SERVICE], $actual[0]->getConfigurationFile()->types);
        $this->assertSame([PluginConfigurationFile::TYPE_GENERAL], $actual[1]->getConfigurationFile()->types);
        $this->assertSame(
            [PluginConfigurationFile::TYPE_GENERAL, PluginConfigurationFile::TYPE_SERVICE],
            $actual[2]->getConfigurationFile()->types
        );
    }

    public function testGetAccounts_NoCharacters()
    {
        $this->assertSame(
            [],
            $this->pluginService->getAccounts($this->testService1Impl, [])
        );
    }

    public function testGetAccounts()
    {
        $player = (new Player())->addGroup(new Group());
        $actual = $this->pluginService->getAccounts(
            $this->testService1Impl,
            [(new Character())->setId(123)->setPlayer($player)]
        );

        $this->assertSame(1, count($actual));
        $this->assertInstanceOf(ServiceAccountData::class, $actual[0]);
        $this->assertSame(123, $actual[0]->getCharacterId());

        $this->assertSame(
            [
                'ServiceInterface::getAccounts must return an array of AccountData objects.',
                'PluginService::getAccounts: Character ID does not match.'
            ],
            $this->log->getMessages()
        );
    }

    public function testGetAccounts_DeactivatedGroups()
    {
        $this->helper->emptyDb();
        $character = $this->helper->setupDeactivateAccount();

        $player = (new Player())->addGroup(new Group())->addCharacter($character);
        $actual = $this->pluginService->getAccounts(
            $this->testService1Impl,
            [(new Character())->setId(123)->setPlayer($player)]
        );

        $this->assertSame(1, count($actual));
    }

    public function testGetAccounts_Exception()
    {
        $this->expectException(Exception::class);
        $this->pluginService->getAccounts(
            $this->testService1Impl,
            [(new Character())->setId(202)->setPlayer(new Player())]
        );
    }

    public function testUpdatePlayerAccounts_Ok()
    {
        $this->helper->emptyDb();

        $conf1 = new PluginConfigurationDatabase();
        $conf1->directoryName = 'plugin-acc1'; // with action
        $service1 = (new Plugin())->setName('S1')->setConfigurationDatabase($conf1);

        $conf2 = new PluginConfigurationDatabase();
        $conf2->directoryName = 'plugin-acc2'; // no action
        $service2 = (new Plugin())->setName('S2')->setConfigurationDatabase($conf2);

        $conf3 = (new PluginConfigurationDatabase());
        $conf3->directoryName = 'plugin-missing-class-with-action'; // with action
        $service3 = (new Plugin())->setName('S3')->setConfigurationDatabase($conf3);

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

        $result = $this->pluginService->updatePlayerAccounts($player1, $player2);

        $this->assertSame([['serviceName' => 'S1', 'characterId' => 101]], $result);
        $this->assertSame(['PluginService::updatePlayerAccounts: S1: Test error'], $this->log->getMessages());
        $this->assertSame($player2->getId() . ' -> ' . $player1->getId(), TestService1::$moved);
    }

    public function testUpdatePlayerAccounts_YamlException()
    {
        $this->helper->emptyDb();

        $conf = new PluginConfigurationDatabase();
        $conf->directoryName = 'plugin-error-string';
        $service = (new Plugin())->setName('S1')->setConfigurationDatabase($conf);

        $player = (new Player())->setName('P1');

        $this->om->persist($service);
        $this->om->persist($player);
        $this->om->flush();

        $result = $this->pluginService->updatePlayerAccounts($player);

        $this->assertSame([], $result);
        $this->assertSame(
            ['Invalid file content in '.__DIR__.'/PluginService/plugin-error-string/plugin.yml'],
            $this->log->getMessages()
        );
    }

    public function testUpdatePlayerAccounts_GetAccountException()
    {
        $this->helper->emptyDb();

        $conf1 = new PluginConfigurationDatabase();
        $conf1->directoryName = 'plugin-acc1'; // with action
        $service1 = (new Plugin())->setName('S1')->setConfigurationDatabase($conf1);

        $player2 = (new Player())->setName('P2');
        $char22 = (new Character())->setId(202)->setName('C22')->setPlayer($player2);
        $player2->addCharacter($char22);

        $this->om->persist($service1);
        $this->om->persist($player2);
        $this->om->persist($char22);
        $this->om->flush();

        $result = $this->pluginService->updatePlayerAccounts($player2);

        $this->assertSame([], $result);
        $this->assertTrue(TestService1::$getAccountException);
    }

    public function testUpdateServiceAccount()
    {
        $char = (new Character())->setMain(true);
        $player = new Player();
        $char->setPlayer($player);
        $player->addCharacter($char);

        $result0 = $this->pluginService->updateServiceAccount(null, $this->testService1Impl);
        $this->assertSame('No character provided.', $result0);

        $result1 = $this->pluginService->updateServiceAccount($char, $this->testService1Impl);
        $this->assertNull($result1);

        $char->setId(102);
        $result2 = $this->pluginService->updateServiceAccount($char, $this->testService1Impl);
        $this->assertSame('Test error', $result2);
    }
}
