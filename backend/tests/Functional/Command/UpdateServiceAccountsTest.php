<?php

declare(strict_types=1);

namespace Tests\Functional\Command;

use Neucore\Entity\Service;
use Neucore\Data\ServiceConfiguration;
use Psr\Log\LoggerInterface;
use Tests\Functional\ConsoleTestCase;
use Tests\Helper;
use Tests\Logger;

class UpdateServiceAccountsTest extends ConsoleTestCase
{
    public function testExecute()
    {
        $this->setUpDb();

        $output = $this->runConsoleApp('update-service-accounts', ['--sleep' => 0], [
            LoggerInterface::class => new Logger('test')
        ]);

        $actual = explode("\n", $output);
        $this->assertSame(9, count($actual));
        $this->assertStringEndsWith('Started "update-service-accounts"', $actual[0]);
        $this->assertStringEndsWith('  Updating S1 ...', $actual[1]);
        $this->assertStringEndsWith('  Test exception.', $actual[2]);
        $this->assertStringEndsWith('  updatePlayerAccount exception', $actual[3]);
        $this->assertStringEndsWith(
            '  Updated S1: 2 accounts updated, 2 updates failed, 2 characters or players not found.',
            $actual[4]
        );
        $this->assertStringEndsWith('  Updating S2 ...', $actual[5]);
        $this->assertStringEndsWith('Service implementation not found for S2', $actual[6]);
        $this->assertStringEndsWith('Finished "update-service-accounts"', $actual[7]);
        $this->assertSame('', $actual[8]);
    }

    private function setUpDb(): void
    {
        $helper = new Helper();
        $helper->emptyDb();
        $em = $helper->getEm();

        $player = $helper->addCharacterMain('C1', 101)->getPlayer();

        UpdateServiceAccountsTest_TestService::$playerId = $player->getId();

        $conf1 = new ServiceConfiguration();
        $conf1->active = true;
        $conf1->phpClass = 'Tests\Functional\Command\UpdateServiceAccountsTest_TestService';
        $service1 = (new Service())->setName('S1')->setConfiguration($conf1);

        $conf2 = new ServiceConfiguration();
        $conf2->active = true;
        $conf2->phpClass = 'TestsService';
        $service2 = (new Service())->setName('S2')->setConfiguration($conf2);

        // Inactive service, will be ignored.
        $conf3 = new ServiceConfiguration();
        $service3 = (new Service())->setName('S3')->setConfiguration($conf3);

        $em->persist($service1);
        $em->persist($service2);
        $em->persist($service3);
        $em->flush();
    }
}
