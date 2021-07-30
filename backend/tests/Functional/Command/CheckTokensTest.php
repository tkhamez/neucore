<?php
/** @noinspection DuplicatedCode */

declare(strict_types=1);

namespace Tests\Functional\Command;

use Doctrine\Persistence\ObjectManager;
use Neucore\Entity\Character;
use Neucore\Entity\Corporation;
use Neucore\Entity\Player;
use Neucore\Factory\RepositoryFactory;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use Psr\Log\LoggerInterface;
use Tests\Client;
use Tests\Functional\ConsoleTestCase;
use Tests\Helper;
use Tests\Logger;

class CheckTokensTest extends ConsoleTestCase
{
    /**
     * @var Helper
     */
    private $helper;

    /**
     * @var ObjectManager
     */
    private $om;

    /**
     * @var Logger
     */
    private $log;

    /**
     * @var Client
     */
    private $client;

    protected function setUp(): void
    {
        $this->helper = new Helper();
        $this->helper->emptyDb();
        $this->om = $this->helper->getObjectManager();

        $this->log = new Logger('Test');
        $this->client = new Client();
    }

    /**
     * @throws \Exception
     */
    public function testExecuteUpdateCharNoToken()
    {
        $c = (new Character())->setId(1)->setName('c1')->setCharacterOwnerHash('coh1');
        $this->helper->addNewPlayerToCharacterAndFlush($c);

        $this->client->setResponse(new Response(200, [], '{"name": "char1"}')); // getCharactersCharacterId

        $output = $this->runConsoleApp('check-tokens', ['--sleep' => 0], [
            ClientInterface::class => $this->client,
            LoggerInterface::class => $this->log
        ]);

        $actual = explode("\n", $output);
        $this->assertSame(4, count($actual));
        $this->assertStringEndsWith('Started "check-tokens"', $actual[0]);
        $this->assertStringEndsWith('  Character 1: token N/A', $actual[1]);
        $this->assertStringEndsWith('Finished "check-tokens"', $actual[2]);
        $this->assertStringEndsWith('', $actual[3]);
    }

    /**
     * @throws \Exception
     */
    public function testExecuteDeleteCharBiomassed()
    {
        $player = (new Player())->setName('p');
        $corp = (new Corporation())->setId(1000001);
        $char = (new Character())->setId(3)->setName('char1')->setCorporation($corp)->setPlayer($player);
        $this->om->persist($player);
        $this->om->persist($corp);
        $this->om->persist($char);
        $this->om->flush();

        $output = $this->runConsoleApp('check-tokens', ['--sleep' => 0]);

        $actual = explode("\n", $output);
        $this->assertSame(4, count($actual));
        $this->assertStringEndsWith('Started "check-tokens"', $actual[0]);
        $this->assertStringEndsWith('  Character 3: character deleted', $actual[1]);
        $this->assertStringEndsWith('Finished "check-tokens"', $actual[2]);
        $this->assertStringEndsWith('', $actual[3]);

        # read result
        $this->om->clear();
        $repositoryFactory = new RepositoryFactory($this->om);
        $actualChars = $repositoryFactory->getCharacterRepository()->findBy([]);
        $this->assertSame(0, count($actualChars));
        $removedChar = $repositoryFactory->getRemovedCharacterRepository()->findBy([]);
        $this->assertSame(1, count($removedChar));
    }

    /**
     * @throws \Exception
     */
    public function testExecuteErrorUpdateToken()
    {
        $c = (new Character())->setId(3)->setName('char1');
        $this->helper->addNewPlayerToCharacterAndFlush($c);
        $this->helper->createOrUpdateEsiToken($c);

        $output = $this->runConsoleApp('check-tokens', ['--sleep' => 0], [
            ClientInterface::class => $this->client,
            LoggerInterface::class => $this->log
        ]);

        $actual = explode("\n", $output);
        $this->assertSame(4, count($actual));
        $this->assertStringEndsWith('Started "check-tokens"', $actual[0]);
        $this->assertStringEndsWith('  Character 3: token parse error', $actual[1]);
        $this->assertStringEndsWith('Finished "check-tokens"', $actual[2]);
        $this->assertStringEndsWith('', $actual[3]);
    }

    /**
     * @throws \Exception
     */
    public function testExecuteInvalidToken()
    {
        $c = (new Character())->setId(3)->setName('char1');
        $this->helper->addNewPlayerToCharacterAndFlush($c);
        $this->helper->createOrUpdateEsiToken($c, time() - 1000);

        $this->client->setResponse(
            new Response(400, [], '{"error": "invalid_grant"}') // for getAccessToken()
        );

        $output = $this->runConsoleApp('check-tokens', ['--sleep' => 0], [
            ClientInterface::class => $this->client,
            LoggerInterface::class => $this->log
        ]);

        $actual = explode("\n", $output);
        $this->assertSame(4, count($actual));
        $this->assertStringEndsWith('Started "check-tokens"', $actual[0]);
        $this->assertStringEndsWith('  Character 3: token NOK', $actual[1]);
        $this->assertStringEndsWith('Finished "check-tokens"', $actual[2]);
        $this->assertStringEndsWith('', $actual[3]);
    }

    /**
     * @throws \Exception
     */
    public function testExecuteCharacterDeleted()
    {
        // This should not be possible in real scenario because the
        // tokens should already be invalid after a character transfer - right!?.

        $player = (new Player())->setName('p');
        $c = (new Character())->setId(3)->setName('char1')->setCharacterOwnerHash('coh3')->setPlayer($player);
        $this->om->persist($player);
        $this->helper->createOrUpdateEsiToken($c, time() - 60*60)->setValidToken(false);

        list($token, $keySet) = Helper::generateToken();
        $this->client->setResponse(
            new Response(200, [], '{"access_token": ' . json_encode($token) . '}'), // for getAccessToken()
            new Response(200, [], '{"keys": ' . json_encode($keySet) . '}') // for SSO JWT key set
        );

        $output = $this->runConsoleApp('check-tokens', ['--sleep' => 0], [
            ClientInterface::class => $this->client,
        ]);

        $actual = explode("\n", $output);
        $this->assertSame(4, count($actual));
        $this->assertStringEndsWith('Started "check-tokens"', $actual[0]);
        $this->assertStringEndsWith('  Character 3: character deleted', $actual[1]);
        $this->assertStringEndsWith('Finished "check-tokens"', $actual[2]);
        $this->assertStringEndsWith('', $actual[3]);

        # read result
        $this->om->clear();
        $repositoryFactory = new RepositoryFactory($this->om);
        $actualChars = $repositoryFactory->getCharacterRepository()->findBy([]);
        $this->assertSame(0, count($actualChars));
        $removedChar = $repositoryFactory->getRemovedCharacterRepository()->findBy([]);
        $this->assertSame(1, count($removedChar));
    }

    /**
     * @throws \Exception
     */
    public function testExecuteValidToken()
    {
        $c = (new Character())->setId(3)->setName('char1')->setCharacterOwnerHash('coh3');
        $this->helper->addNewPlayerToCharacterAndFlush($c);
        $this->helper->createOrUpdateEsiToken($c, time() - 60*60)->setValidToken(false);

        list($token, $keySet) = Helper::generateToken(['scope1', 'scope2'], 'Name', 'coh3');
        $this->client->setResponse(
            new Response( // for getAccessToken()
                200,
                [],
                '{"access_token": '.json_encode($token).', "refresh_token": "rt", "expires": '.(time()+60*60).'}'
            ),
            new Response(200, [], '{"keys": ' . json_encode($keySet) . '}') // for SSO JWT key set
        );

        $output = $this->runConsoleApp('check-tokens', ['--sleep' => 0], [
            ClientInterface::class => $this->client,
        ]);

        $actual = explode("\n", $output);
        $this->assertSame(4, count($actual));
        $this->assertStringEndsWith('Started "check-tokens"', $actual[0]);
        $this->assertStringEndsWith('  Character 3: token OK', $actual[1]);
        $this->assertStringEndsWith('Finished "check-tokens"', $actual[2]);
        $this->assertStringEndsWith('', $actual[3]);

        # read result
        $this->om->clear();

        $repositoryFactory = new RepositoryFactory($this->om);

        $actualChars = $repositoryFactory->getCharacterRepository()->findBy([]);
        $this->assertSame(1, count($actualChars));
        $this->assertSame(3, $actualChars[0]->getId());
        $this->assertSame('char1', $actualChars[0]->getName());
        $this->assertNull($actualChars[0]->getLastUpdate()); // token check does not change the update date
        $nameChange = $repositoryFactory->getCharacterNameChangeRepository()->findBy([]);
        $this->assertSame(1, count($nameChange));
        $this->assertSame('Name', $nameChange[0]->getOldName());
    }

    /**
     * @throws \Exception
     */
    public function testExecuteValidTokenUnexpectedData()
    {
        list($token, $keySet) = Helper::generateToken(['scope1', 'scope2'], 'Name', 'coh3', 'invalid');

        $c = (new Character())->setId(3)->setName('char1')->setCharacterOwnerHash('coh3');
        $this->helper->addNewPlayerToCharacterAndFlush($c);
        $this->helper->createOrUpdateEsiToken($c, 123456, $token)->setValidToken(false);

        $this->client->setResponse(
            // Token has no expire time, so no call to GenericProvider->getAccessToken()
            new Response(200, [], '{"keys": ' . json_encode($keySet) . '}') // for SSO JWT key set
        );

        $output = $this->runConsoleApp('check-tokens', ['--sleep' => 0], [
            ClientInterface::class => $this->client,
            LoggerInterface::class => $this->log,
        ]);

        $actual = explode("\n", $output);
        $this->assertSame(4, count($actual));
        $this->assertStringEndsWith('Started "check-tokens"', $actual[0]);
        $this->assertStringEndsWith('  Character 3: token OK', $actual[1]);
        $this->assertStringEndsWith('Finished "check-tokens"', $actual[2]);
        $this->assertStringEndsWith('', $actual[3]);
        $this->assertSame(
            'Unexpected JWT data, missing character owner hash.',
            $this->log->getHandler()->getRecords()[0]['message']
        );
    }
}
