<?php

declare(strict_types=1);

namespace Tests\Functional\Command;

require_once __DIR__ . '/PluginTest/plugin1/src/TestService1.php';

use Doctrine\Persistence\ObjectManager;
use Neucore\Data\PluginConfigurationDatabase;
use Neucore\Entity\Plugin;
use Psr\Log\LoggerInterface;
use Tests\Functional\Command\PluginTest\TestService1;
use Tests\Functional\ConsoleTestCase;
use Tests\Helper;
use Tests\Logger;

class PluginTest extends ConsoleTestCase
{
    private ObjectManager $om;

    protected function setUp(): void
    {
        $helper = new Helper();
        $helper->emptyDb();
        $this->om = $helper->getObjectManager();
    }

    public function _testExecuteNotFound()
    {
        $output = $this->runConsoleApp('plugin', ['id' => 1]);
        $this->assertSame('Plugin 1 not found or not active.', trim($output));
    }

    public function _testExecuteImplNotFound()
    {
        $output = $this->runConsoleApp('plugin', ['id' => 2]);
        $this->assertSame(
            'Plugin 1 implementation not found or does not implement GeneralInterface.',
            trim($output),
        );
    }

    public function testExecute()
    {
        $configuration = new PluginConfigurationDatabase();
        $configuration->directoryName = 'plugin1';
        $configuration->active = true;
        $plugin = (new Plugin())->setName('Plugin 1')->setConfigurationDatabase($configuration);
        $this->om->persist($plugin);
        $this->om->flush();

        $output = $this->runConsoleApp(
            'plugin',
            ['id' => $plugin->getId(), 'args' => ['a1', 'a2']],
            [],
            [['NEUCORE_PLUGINS_INSTALL_DIR', __DIR__ . '/PluginTest']],
            false,
            ['argument', '--opt1', 'o1', '--opt2', 'o2', '-oignored', '--opt3'],
        );

        $this->assertSame(['a1', 'a2'], TestService1::$arguments);
        $this->assertSame(['opt1' => 'o1', 'opt2' => 'o2', 'opt3' => ''], TestService1::$options);
        $this->assertSame('Test done.', $output);
    }

    public function _testExecuteException()
    {
        $log = new Logger();

        $output = $this->runConsoleApp('plugin', ['id' => 1], [LoggerInterface::class => $log]);

        $this->assertSame('TODO' . PHP_EOL, $output);
        $this->assertSame('TODO' . PHP_EOL, $log->getMessages());
    }
}
