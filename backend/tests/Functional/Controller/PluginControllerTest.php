<?php

declare(strict_types=1);

namespace Tests\Functional\Controller;

use Doctrine\Persistence\ObjectManager;
use Neucore\Data\PluginConfigurationDatabase;
use Neucore\Entity\Plugin;
use Neucore\Entity\Role;
use Neucore\Plugin\CoreCharacter;
use Neucore\Plugin\CoreGroup;
use Neucore\Plugin\CoreRole;
use Psr\Log\LoggerInterface;
use Tests\Functional\Controller\PluginController\TestService1;
use Tests\Functional\WebTestCase;
use Tests\Helper;
use Tests\Logger;

class PluginControllerTest extends WebTestCase
{
    private Helper $helper;

    private ObjectManager $om;

    protected function setUp(): void
    {
        $this->helper = new Helper();
        $this->om = $this->helper->getObjectManager();
        $this->helper->emptyDb();
    }

    protected function tearDown(): void
    {
        unset($_SESSION['character_id']);
    }

    public function testRequest_404_NoPlugin()
    {
        $this->helper->addCharacterMain('User 100', 100);
        $this->loginUser(100);

        $response = $this->runApp('GET', '/plugin/1/auth');
        $this->assertStringStartsWith('Plugin not found.', $response->getBody()->__toString());
        $this->assertSame(404, $response->getStatusCode());
    }

    public function testRequest403_MissingGroup()
    {
        $this->helper->addCharacterMain('User 100', 100);
        $this->loginUser(100);
        $configuration = new PluginConfigurationDatabase();
        $configuration->directoryName = 'plugin1';
        $configuration->requiredGroups = [1];
        $plugin = (new Plugin())->setName('Plugin 1')->setConfigurationDatabase($configuration);
        $this->om->persist($plugin);
        $this->om->flush();

        $response = $this->runApp(
            'GET',
            '/plugin/'.$plugin->getId().'/auth',
            null,
            null,
            [],
            [['NEUCORE_PLUGINS_INSTALL_DIR', __DIR__ . '/PluginController']],
        );
        $this->assertStringStartsWith('Not allowed to use this plugin.', $response->getBody()->__toString());
        $this->assertSame(403, $response->getStatusCode());
    }

    public function testRequest404_NoImplementation()
    {
        $this->helper->addCharacterMain('User 100', 100);
        $this->loginUser(100);
        $configuration = new PluginConfigurationDatabase();
        $configuration->directoryName = 'plugin2';
        $plugin = (new Plugin())->setName('Plugin 2')->setConfigurationDatabase($configuration);
        $this->om->persist($plugin);
        $this->om->flush();

        $response = $this->runApp(
            'GET',
            '/plugin/'.$plugin->getId().'/auth',
            null,
            null,
            [],
            [['NEUCORE_PLUGINS_INSTALL_DIR', __DIR__ . '/PluginController']],
        );
        $this->assertStringStartsWith('Plugin implementation not found.', $response->getBody()->__toString());
        $this->assertSame(404, $response->getStatusCode());
    }

    public function testRequest_Success()
    {
        $character = $this->helper->addCharacterMain('User 100', 100, [Role::USER], ['Group 1', 'Group 2']);
        $player = $character->getPlayer();
        $this->helper->addCharacterToPlayer('User 200', 200, $player);
        $player->addManagerGroup($player->getGroups()[1]);
        $this->loginUser(100);
        $configuration = new PluginConfigurationDatabase();
        $configuration->directoryName = 'plugin1';
        $configuration->requiredGroups = [$player->getGroups()[0]->getId()];
        $plugin = (new Plugin())->setName('Plugin 1')->setConfigurationDatabase($configuration);
        $this->om->persist($plugin);
        $this->om->flush();

        $response = $this->runApp(
            'GET',
            '/plugin/'.$plugin->getId().'/auth',
            null,
            null,
            [],
            [['NEUCORE_PLUGINS_INSTALL_DIR', __DIR__ . '/PluginController']],
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('Response from plugin.', $response->getBody()->__toString());

        $this->assertSame('auth', TestService1::$data['name']);

        $this->assertInstanceOf(CoreCharacter::class, TestService1::$data['main']);
        $this->assertSame(100, TestService1::$data['main']->id);

        $this->assertSame(2, count(TestService1::$data['characters']));
        $this->assertInstanceOf(CoreCharacter::class, TestService1::$data['characters'][0]);
        $this->assertInstanceOf(CoreCharacter::class, TestService1::$data['characters'][1]);
        $this->assertSame(100, TestService1::$data['characters'][0]->id);
        $this->assertSame(200, TestService1::$data['characters'][1]->id);

        $this->assertSame(2, count(TestService1::$data['memberGroups']));
        $this->assertInstanceOf(CoreGroup::class, TestService1::$data['memberGroups'][0]);
        $this->assertInstanceOf(CoreGroup::class, TestService1::$data['memberGroups'][1]);
        $this->assertSame('Group 1', TestService1::$data['memberGroups'][0]->name);
        $this->assertSame('Group 2', TestService1::$data['memberGroups'][1]->name);

        $this->assertSame(1, count(TestService1::$data['managerGroups']));
        $this->assertInstanceOf(CoreGroup::class, TestService1::$data['managerGroups'][0]);
        $this->assertSame('Group 2', TestService1::$data['managerGroups'][0]->name);

        $this->assertSame(1, count(TestService1::$data['roles']));
        $this->assertInstanceOf(CoreRole::class, TestService1::$data['roles'][0]);
        $this->assertSame(Role::USER, TestService1::$data['roles'][0]->name);
    }

    public function testRequest_Success_NotLoggedIn()
    {
        $configuration = new PluginConfigurationDatabase();
        $configuration->directoryName = 'plugin1';
        $plugin = (new Plugin())->setName('Plugin 1')->setConfigurationDatabase($configuration);
        $this->om->persist($plugin);
        $this->om->flush();

        $response = $this->runApp(
            'GET',
            '/plugin/'.$plugin->getId().'/test',
            null,
            null,
            [],
            [['NEUCORE_PLUGINS_INSTALL_DIR', __DIR__ . '/PluginController']],
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringStartsWith('Response from plugin.', $response->getBody()->__toString());
        $this->assertSame('test', TestService1::$data['name']);
        $this->assertNull(TestService1::$data['main']);
    }

    public function testRequest302_Exception_ServicePlugin()
    {
        $this->helper->addCharacterMain('User 100', 100);
        $this->loginUser(100);
        $configuration = new PluginConfigurationDatabase();
        $configuration->directoryName = 'plugin1';
        $plugin = (new Plugin())->setName('Plugin 1')->setConfigurationDatabase($configuration);
        $this->om->persist($plugin);
        $this->om->flush();

        $logger = new Logger('Test');
        $response = $this->runApp(
            'GET',
            '/plugin/'.$plugin->getId().'/auth?error=1',
            null,
            null,
            [LoggerInterface::class => $logger],
            [['NEUCORE_PLUGINS_INSTALL_DIR', __DIR__ . '/PluginController']],
        );

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame(
            '/#Service/'.$plugin->getId().'/?message=Unknown%20error.',
            $response->getHeader('Location')[0]
        );
        $this->assertSame('Exception from service plugin.', $logger->getHandler()->getRecords()[0]['message']);
    }

    public function testRequest200_Exception_GeneralPlugin()
    {
        $this->helper->addCharacterMain('User 100', 100);
        $this->loginUser(100);
        $configuration = new PluginConfigurationDatabase();
        $configuration->directoryName = 'plugin3';
        $plugin = (new Plugin())->setName('Plugin 3')->setConfigurationDatabase($configuration);
        $this->om->persist($plugin);
        $this->om->flush();

        $logger = new Logger('Test');
        $response = $this->runApp(
            'GET',
            '/plugin/'.$plugin->getId().'/test',
            null,
            null,
            [LoggerInterface::class => $logger],
            [['NEUCORE_PLUGINS_INSTALL_DIR', __DIR__ . '/PluginController']],
        );

        $this->assertSame('Exception from general plugin.', $logger->getHandler()->getRecords()[0]['message']);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringStartsWith('Error from plugin.', $response->getBody()->__toString());
    }
}
