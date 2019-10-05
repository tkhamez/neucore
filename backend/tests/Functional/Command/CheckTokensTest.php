<?php declare(strict_types=1);

namespace Tests\Functional\Command;

use Neucore\Entity\Character;
use Neucore\Entity\Corporation;
use Neucore\Entity\Player;
use Neucore\Factory\RepositoryFactory;
use Doctrine\ORM\EntityManagerInterface;
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
     * @var EntityManagerInterface
     */
    private $em;

    /**
     * @var Logger
     */
    private $log;

    /**
     * @var Client
     */
    private $client;

    public function setUp()
    {
        $this->helper = new Helper();
        $this->helper->emptyDb();
        $this->em = $this->helper->getEm();

        $this->log = new Logger('Test');
        $this->client = new Client();
    }

    public function testExecuteUpdateCharNoToken()
    {
        $c = (new Character())->setId(1)->setName('c1')
            ->setCharacterOwnerHash('coh1')->setAccessToken('at1');
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

    public function testExecuteDeleteCharBiomassed()
    {
        $player = (new Player())->setName('p');
        $corp = (new Corporation())->setId(1000001);
        $char = (new Character())->setId(3)->setName('char1')->setCorporation($corp)->setPlayer($player);
        $this->em->persist($player);
        $this->em->persist($corp);
        $this->em->persist($char);
        $this->em->flush();

        $output = $this->runConsoleApp('check-tokens', ['--sleep' => 0]);

        $actual = explode("\n", $output);
        $this->assertSame(4, count($actual));
        $this->assertStringEndsWith('Started "check-tokens"', $actual[0]);
        $this->assertStringEndsWith('  Character 3: character deleted', $actual[1]);
        $this->assertStringEndsWith('Finished "check-tokens"', $actual[2]);
        $this->assertStringEndsWith('', $actual[3]);

        # read result
        $this->em->clear();
        $repositoryFactory = new RepositoryFactory($this->em);
        $actualChars = $repositoryFactory->getCharacterRepository()->findBy([]);
        $this->assertSame(0, count($actualChars));
        $removedChar = $repositoryFactory->getRemovedCharacterRepository()->findBy([]);
        $this->assertSame(1, count($removedChar));
    }

    public function testExecuteErrorUpdateToken()
    {
        $c = (new Character())->setId(3)->setName('char1')->setAccessToken('at3')->setRefreshToken('at3');
        $this->helper->addNewPlayerToCharacterAndFlush($c);

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

    public function testExecuteInvalidToken()
    {
        $c = (new Character())->setId(3)->setName('char1')
            ->setAccessToken('at3')->setRefreshToken('at3')->setExpires(time() - 1000);
        $this->helper->addNewPlayerToCharacterAndFlush($c);

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
        $c = (new Character())->setId(3)->setName('char1')->setCharacterOwnerHash('coh3')
            ->setAccessToken('at3')->setRefreshToken('at3')->setValidToken(false)
            ->setExpires(time() - 60*60)->setPlayer($player);
        $this->em->persist($player);
        $this->em->persist($c);
        $this->em->flush();

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
        $this->em->clear();
        $repositoryFactory = new RepositoryFactory($this->em);
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
        $c = (new Character())->setId(3)->setName('char1')->setCharacterOwnerHash('coh3')
            ->setAccessToken('at3')->setRefreshToken('at3')->setValidToken(false)
            ->setExpires(time() - 60*60);
        $this->helper->addNewPlayerToCharacterAndFlush($c);

        list($token, $keySet) = Helper::generateToken([], 'Name', 'coh3');
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
        $this->assertStringEndsWith('  Character 3: token OK', $actual[1]);
        $this->assertStringEndsWith('Finished "check-tokens"', $actual[2]);
        $this->assertStringEndsWith('', $actual[3]);

        # read result
        $this->em->clear();

        $repositoryFactory = new RepositoryFactory($this->em);

        $actualChars = $repositoryFactory->getCharacterRepository()->findBy([]);
        $this->assertSame(1, count($actualChars));
        $this->assertSame(3, $actualChars[0]->getId());
        $this->assertNull($actualChars[0]->getLastUpdate()); // token check does not change the update date
    }

    /**
     * @throws \Exception
     */
    public function testExecuteValidTokenUnexpectedData()
    {
        list($token, $keySet) = Helper::generateToken([], 'Name', 'coh3', 'invalid');

        $c = (new Character())->setId(3)->setName('char1')->setCharacterOwnerHash('coh3')
            ->setAccessToken($token)->setRefreshToken('at3')->setValidToken(false);
        $this->helper->addNewPlayerToCharacterAndFlush($c);

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
            'Unexpected JWT data.',
            $this->log->getHandler()->getRecords()[0]['message']
        );
        $this->assertSame(
            ['data' => Helper::parseToken($token)],
            $this->log->getHandler()->getRecords()[0]['context']
        );
    }
}
