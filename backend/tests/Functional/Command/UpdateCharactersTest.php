<?php

/** @noinspection DuplicatedCode */

declare(strict_types=1);

namespace Tests\Functional\Command;

use Doctrine\Persistence\ObjectManager;
use Neucore\Entity\Character;
use Neucore\Factory\RepositoryFactory;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use Psr\Log\LoggerInterface;
use Tests\Client;
use Tests\Functional\ConsoleTestCase;
use Tests\Helper;
use Tests\Logger;

class UpdateCharactersTest extends ConsoleTestCase
{
    private Helper $helper;

    private ObjectManager $om;

    private Logger $log;

    private Client $client;

    protected function setUp(): void
    {
        $this->helper = new Helper();
        $this->helper->emptyDb();
        $this->om = $this->helper->getObjectManager();

        $this->log = new Logger();
        $this->client = new Client();
    }

    public function testExecuteError()
    {
        $c = (new Character())->setId(1)->setName('c1');
        $this->helper->addNewPlayerToCharacterAndFlush($c);
        $this->client->setResponse(new Response(500), new Response(500));

        $output = $this->runConsoleApp('update-chars', ['--sleep' => 0], [
            ClientInterface::class => $this->client,
            LoggerInterface::class => $this->log,
        ]);

        $actual = explode("\n", $output);
        $this->assertSame(4, count($actual));
        $this->assertStringEndsWith('Started "update-chars"', $actual[0]);
        $this->assertStringEndsWith('  Character 1: update NOK', $actual[1]);
        $this->assertStringEndsWith('Finished "update-chars"', $actual[2]);
        $this->assertSame('', $actual[3]);
    }

    public function testExecuteOk()
    {
        $c = (new Character())->setId(3)->setName('char1');
        $this->helper->addNewPlayerToCharacterAndFlush($c);
        $this->helper->addCharacterMain('char2', 6);

        $this->client->setResponse(
            new Response(200, [], '[
                {"id": 3, "name": "char1", "category": "character"},
                {"id": 6, "name": "char2 changed", "category": "character"}
            ]'), // postUniverseNames
            new Response(200, [], '[
                {"character_id": 3, "corporation_id": 101},
                {"character_id": 6, "corporation_id": 101}
            ]'), // postCharactersAffiliation
        );

        $output = $this->runConsoleApp('update-chars', ['--sleep' => 0], [
            ClientInterface::class => $this->client,
        ]);

        $actual = explode("\n", $output);
        $this->assertSame(4, count($actual));
        $this->assertStringEndsWith('Started "update-chars"', $actual[0]);
        $this->assertStringEndsWith('  Characters 3,6: update OK', $actual[1]);
        $this->assertStringEndsWith('Finished "update-chars"', $actual[2]);
        $this->assertSame('', $actual[3]);

        // read result
        $this->om->clear();

        $repositoryFactory = new RepositoryFactory($this->om);

        $actualChars = $repositoryFactory->getCharacterRepository()->findBy([]);
        $this->assertSame(2, count($actualChars));
        $this->assertSame(3, $actualChars[0]->getId());
        $this->assertSame("char1", $actualChars[0]->getName());
        $this->assertSame(6, $actualChars[1]->getId());
        $this->assertSame("char2 changed", $actualChars[1]->getName());
        $this->assertNotNull($actualChars[0]->getLastUpdate());
        $this->assertNotNull($actualChars[1]->getLastUpdate());

        $actualCorps = $repositoryFactory->getCorporationRepository()->findBy([]);
        $this->assertSame(1, count($actualCorps));
        $this->assertSame(101, $actualCorps[0]->getId());

        $renamed = $repositoryFactory->getCharacterNameChangeRepository()->findBy([]);
        $this->assertSame(1, count($renamed));
        $this->assertSame(6, $renamed[0]->getCharacter()->getId());
        $this->assertSame('char2', $renamed[0]->getOldName());
    }
}
