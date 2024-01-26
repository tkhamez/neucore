<?php
/** @noinspection DuplicatedCode */

namespace Tests\Functional\Command;

use Doctrine\Persistence\ObjectManager;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use Neucore\Command\UpdateCorporations;
use Neucore\Entity\Corporation;
use Neucore\Factory\RepositoryFactory;
use Psr\Log\LoggerInterface;
use Tests\Client;
use Tests\Functional\ConsoleTestCase;
use Tests\Helper;
use Tests\Logger;

class UpdateCorporationsTest extends ConsoleTestCase
{
    private ObjectManager $om;

    private Logger $log;

    private Client $client;

    protected function setUp(): void
    {
        $helper = new Helper();
        $helper->emptyDb();
        $this->om = $helper->getObjectManager();

        $this->log = new Logger();
        $this->client = new Client();
    }

    public function testExecuteError()
    {
        $c = (new Corporation())->setId(101);
        $this->om->persist($c);
        $this->om->flush();
        $this->client->setResponse(new Response(500), new Response(500));

        $output = $this->runConsoleApp('update-corporations', ['--sleep' => 0], [
            ClientInterface::class => $this->client,
            LoggerInterface::class => $this->log
        ]);

        $actual = explode("\n", $output);
        $this->assertSame(4, count($actual));
        $this->assertStringEndsWith('Started "update-corporations"', $actual[0]);
        $this->assertStringEndsWith('  Corporation 101: ' . UpdateCorporations::UPDATE_NOK, $actual[1]);
        $this->assertStringEndsWith('Finished "update-corporations"', $actual[2]);
        $this->assertSame('', $actual[3]);
    }

    public function testExecuteInvalidCorp()
    {
        $c = (new Corporation())->setId(0);
        $this->om->persist($c);
        $this->om->flush();

        $output = $this->runConsoleApp('update-corporations', ['--sleep' => 0]);

        $actual = explode("\n", $output);
        $this->assertSame(4, count($actual));
        $this->assertStringEndsWith('Started "update-corporations"', $actual[0]);
        $this->assertStringEndsWith('  Corporation 0: ' . UpdateCorporations::UPDATE_NOK, $actual[1]);
        $this->assertStringEndsWith('Finished "update-corporations"', $actual[2]);
        $this->assertSame('', $actual[3]);
    }

    public function testExecuteOk()
    {
        $c = (new Corporation())->setId(101);
        $this->om->persist($c);
        $this->om->flush();

        $this->client->setResponse(
            new Response(200, [], '{"name": "corp1", "ticker": "t", "alliance_id": 212}'),
            new Response(200, [], '{"name": "The Alli.", "ticker": "-A-"}')
        );

        $output = $this->runConsoleApp('update-corporations', ['--sleep' => 0], [
            ClientInterface::class => $this->client,
        ]);

        $actual = explode("\n", $output);
        $this->assertSame(5, count($actual));
        $this->assertStringEndsWith('Started "update-corporations"', $actual[0]);
        $this->assertStringEndsWith('  Corporation 101: ' . UpdateCorporations::UPDATE_OK, $actual[1]);
        $this->assertStringEndsWith('  Alliance 212: ' . UpdateCorporations::UPDATE_OK, $actual[2]);
        $this->assertStringEndsWith('Finished "update-corporations"', $actual[3]);
        $this->assertSame('', $actual[4]);

        // read result
        $this->om->clear();

        $repositoryFactory = new RepositoryFactory($this->om);

        $actualCorps = $repositoryFactory->getCorporationRepository()->findBy([]);
        $this->assertSame(1, count($actualCorps));
        $this->assertSame(101, $actualCorps[0]->getId());
        $this->assertSame(212, $actualCorps[0]->getAlliance()->getId());

        $actualAlliances = $repositoryFactory->getAllianceRepository()->findBy([]);
        $this->assertSame(1, count($actualAlliances));
        $this->assertSame(212, $actualAlliances[0]->getId());
    }
}
