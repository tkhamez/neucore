<?php

declare(strict_types=1);

namespace Tests\Functional\Command;

require_once __DIR__ . '/UpdateServiceAccounts/plugin1/src/TestService1.php';

use Neucore\Data\PluginConfigurationDatabase;
use Neucore\Entity\Plugin;
use Psr\Log\LoggerInterface;
use Tests\Functional\Command\UpdateServiceAccounts\TestService1;
use Tests\Functional\ConsoleTestCase;
use Tests\Helper;
use Tests\Logger;

class UpdateServiceAccountsTest extends ConsoleTestCase
{
    public function testExecute()
    {
        $this->setUpDb();

        $output = $this->runConsoleApp(
            'update-service-accounts',
            ['--sleep' => 0],
            [LoggerInterface::class => new Logger()],
            [['NEUCORE_PLUGINS_INSTALL_DIR', __DIR__ . '/UpdateServiceAccounts']],
        );

        $actual = explode("\n", $output);
        $this->assertSame(7, count($actual));
        $this->assertStringEndsWith('Started "update-service-accounts"', $actual[0]);
        $this->assertStringEndsWith('  Updating S1 ...', $actual[1]);
        $this->assertStringEndsWith('  Test exception.', $actual[2]);
        $this->assertStringEndsWith('  updatePlayerAccount exception', $actual[3]);
        $this->assertStringEndsWith(
            '  Updated S1: 2 accounts updated, 2 updates failed, 2 characters or players not found.',
            $actual[4]
        );
        $this->assertStringEndsWith('Finished "update-service-accounts"', $actual[5]);
        $this->assertSame('', $actual[6]);
    }

    private function setUpDb(): void
    {
        $helper = new Helper();
        $helper->emptyDb();
        $em = $helper->getEm();

        $player = $helper->addCharacterMain('C1', 101)->getPlayer();

        TestService1::$playerId = $player->getId();

        $conf1 = new PluginConfigurationDatabase();
        $conf1->directoryName = 'plugin1';
        $conf1->active = true;
        $service1 = (new Plugin())->setName('S1')->setConfigurationDatabase($conf1);

        $conf2 = new PluginConfigurationDatabase();
        $conf2->directoryName = 'plugin2';
        $conf2->active = true;
        $service2 = (new Plugin())->setName('S2')->setConfigurationDatabase($conf2);

        // Inactive service, will be ignored.
        $conf3 = new PluginConfigurationDatabase();
        $service3 = (new Plugin())->setName('S3')->setConfigurationDatabase($conf3);

        $em->persist($service1);
        $em->persist($service2);
        $em->persist($service3);
        $em->flush();
    }
}
