<?php

declare(strict_types=1);

namespace Tests\Functional\Command;

use Neucore\Entity\Service;
use Neucore\Entity\ServiceConfiguration;
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
        $this->assertSame(5, count($actual));
        $this->assertStringEndsWith('Started "update-service-accounts"', $actual[0]);
        $this->assertStringEndsWith(
            '  Updated S1: 1 accounts updated, 1 updates failed, 1 without a character.',
            $actual[1]
        );
        $this->assertStringEndsWith('Service implementation not found for S2', $actual[2]);
        $this->assertStringEndsWith('Finished "update-service-accounts"', $actual[3]);
        $this->assertSame('', $actual[4]);
    }

    private function setUpDb(): void
    {
        $helper = new Helper();
        $helper->emptyDb();
        $em = $helper->getEm();

        $helper->addCharacterMain('C1', 101);

        $conf1 = new ServiceConfiguration();
        $conf1->phpClass = 'Tests\Functional\Command\UpdateServiceAccountsTest_TestService';
        $service1 = (new Service())->setName('S1')->setConfiguration($conf1);

        $conf2 = new ServiceConfiguration();
        $conf2->phpClass = 'TestsService';
        $service2 = (new Service())->setName('S2')->setConfiguration($conf2);

        $em->persist($service1);
        $em->persist($service2);
        $em->flush();
    }
}
