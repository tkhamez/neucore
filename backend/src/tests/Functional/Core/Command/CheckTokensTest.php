<?php declare(strict_types=1);

namespace Tests\Functional\Core\Command;

use Brave\Core\Entity\Character;
use Brave\Core\Entity\Corporation;
use Brave\Core\Entity\Player;
use Brave\Core\Factory\RepositoryFactory;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use League\OAuth2\Client\Provider\GenericProvider;
use Psr\Log\LoggerInterface;
use Tests\Client;
use Tests\ConsoleTestCase;
use Tests\Helper;
use Tests\Logger;
use Tests\OAuthProvider;

class CheckTokensTest extends ConsoleTestCase
{
    /**
     * @var \Doctrine\ORM\EntityManagerInterface
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
        $h = new Helper();
        $h->emptyDb();
        $this->em = $h->getEm();

        $this->log = new Logger('Test');
        $this->client = new Client();
    }

    public function testExecuteUpdateCharNoToken()
    {
        $c = (new Character())->setId(1)->setName('c1')
            ->setCharacterOwnerHash('coh1')->setAccessToken('at1');
        $this->em->persist($c);
        $this->em->flush();
        $this->client->setResponse(new Response(200, [], '{"name": "char1"}')); // getCharactersCharacterId

        $output = $this->runConsoleApp('check-tokens', ['--sleep' => 0], [
            ClientInterface::class => $this->client,
            LoggerInterface::class => $this->log
        ]);

        $actual = explode("\n", $output);
        $this->assertSame(4, count($actual));
        $this->assertStringEndsWith('* Started "check-tokens"', $actual[0]);
        $this->assertStringEndsWith('Character 1: token N/A', $actual[1]);
        $this->assertStringEndsWith('* Finished "check-tokens"', $actual[2]);
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

        $output = $this->runConsoleApp('check-tokens', ['--sleep' => 0], [
            GenericProvider::class => new OAuthProvider($this->client),
        ]);

        $actual = explode("\n", $output);
        $this->assertSame(4, count($actual));
        $this->assertStringEndsWith('* Started "check-tokens"', $actual[0]);
        $this->assertStringEndsWith('Character 3: character deleted', $actual[1]);
        $this->assertStringEndsWith('* Finished "check-tokens"', $actual[2]);
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
        $this->em->persist($c);
        $this->em->flush();

        $this->client->setResponse(
            new Response(400) // for getResourceOwner()
        );

        $output = $this->runConsoleApp('check-tokens', ['--sleep' => 0], [
            ClientInterface::class => $this->client,
            GenericProvider::class => new OAuthProvider($this->client),
            LoggerInterface::class => $this->log
        ]);

        $actual = explode("\n", $output);
        $this->assertSame(4, count($actual));
        $this->assertStringEndsWith('* Started "check-tokens"', $actual[0]);
        $this->assertStringEndsWith('Character 3: token request failed', $actual[1]);
        $this->assertStringEndsWith('* Finished "check-tokens"', $actual[2]);
        $this->assertStringEndsWith('', $actual[3]);
    }

    public function testExecuteInvalidToken()
    {
        $c = (new Character())->setId(3)->setName('char1')
            ->setAccessToken('at3')->setRefreshToken('at3')->setExpires(time() - 1000);
        $this->em->persist($c);
        $this->em->flush();

        $this->client->setResponse(
            new Response(400, [], '{"error": "invalid_token"}') // for getAccessToken()
        );

        $output = $this->runConsoleApp('check-tokens', ['--sleep' => 0], [
            ClientInterface::class => $this->client,
            GenericProvider::class => new OAuthProvider($this->client),
            LoggerInterface::class => $this->log
        ]);

        $actual = explode("\n", $output);
        $this->assertSame(4, count($actual));
        $this->assertStringEndsWith('* Started "check-tokens"', $actual[0]);
        $this->assertStringEndsWith('Character 3: token NOK', $actual[1]);
        $this->assertStringEndsWith('* Finished "check-tokens"', $actual[2]);
        $this->assertStringEndsWith('', $actual[3]);
    }

    public function testExecuteCharacterDeleted()
    {
        $player = (new Player())->setName('p');
        $c = (new Character())->setId(3)->setName('char1')->setCharacterOwnerHash('coh3')
            ->setAccessToken('at3')->setRefreshToken('at3')->setValidToken(false)
            ->setExpires(time() - 60*60)->setPlayer($player);
        $this->em->persist($player);
        $this->em->persist($c);
        $this->em->flush();

        $this->client->setResponse(
            new Response(200, [], '{"access_token": "tok4"}'), // for getAccessToken()
            new Response(200, [], '{"CharacterOwnerHash": "coh4"}') // for getResourceOwner()
        );

        $output = $this->runConsoleApp('check-tokens', ['--sleep' => 0], [
            ClientInterface::class => $this->client,
            GenericProvider::class => new OAuthProvider($this->client),
        ]);

        $actual = explode("\n", $output);
        $this->assertSame(4, count($actual));
        $this->assertStringEndsWith('* Started "check-tokens"', $actual[0]);
        $this->assertStringEndsWith('Character 3: character deleted', $actual[1]);
        $this->assertStringEndsWith('* Finished "check-tokens"', $actual[2]);
        $this->assertStringEndsWith('', $actual[3]);

        # read result
        $this->em->clear();
        $repositoryFactory = new RepositoryFactory($this->em);
        $actualChars = $repositoryFactory->getCharacterRepository()->findBy([]);
        $this->assertSame(0, count($actualChars));
        $removedChar = $repositoryFactory->getRemovedCharacterRepository()->findBy([]);
        $this->assertSame(1, count($removedChar));
    }

    public function testExecuteValidToken()
    {
        $c = (new Character())->setId(3)->setName('char1')->setCharacterOwnerHash('coh3')
            ->setAccessToken('at3')->setRefreshToken('at3')->setValidToken(false)
            ->setExpires(time() - 60*60);
        $this->em->persist($c);
        $this->em->flush();

        $this->client->setResponse(
            new Response(200, [], '{"access_token": "tok4"}'), // for getAccessToken()
            new Response(200, [], '{"CharacterOwnerHash": "coh3"}') // for getResourceOwner()
        );

        $output = $this->runConsoleApp('check-tokens', ['--sleep' => 0], [
            ClientInterface::class => $this->client,
            GenericProvider::class => new OAuthProvider($this->client),
        ]);

        $actual = explode("\n", $output);
        $this->assertSame(4, count($actual));
        $this->assertStringEndsWith('* Started "check-tokens"', $actual[0]);
        $this->assertStringEndsWith('Character 3: token OK', $actual[1]);
        $this->assertStringEndsWith('* Finished "check-tokens"', $actual[2]);
        $this->assertStringEndsWith('', $actual[3]);

        # read result
        $this->em->clear();

        $repositoryFactory = new RepositoryFactory($this->em);

        $actualChars = $repositoryFactory->getCharacterRepository()->findBy([]);
        $this->assertSame(1, count($actualChars));
        $this->assertSame(3, $actualChars[0]->getId());
        $this->assertNull($actualChars[0]->getLastUpdate()); // token check does not change the update date
    }

    public function testExecuteValidTokenUnexpectedData()
    {
        $c = (new Character())->setId(3)->setName('char1')->setCharacterOwnerHash('coh3')
            ->setAccessToken('at3')->setRefreshToken('at3')->setValidToken(false);
        $this->em->persist($c);
        $this->em->flush();

        $this->client->setResponse(
            // Token has no expire time, so no call to GenericProvider->getAccessToken()
            new Response(200, [], '{"UNKNOWN": "DATA"}') // for getResourceOwner()
        );

        $output = $this->runConsoleApp('check-tokens', ['--sleep' => 0], [
            ClientInterface::class => $this->client,
            GenericProvider::class => new OAuthProvider($this->client),
            LoggerInterface::class => $this->log,
        ]);

        $actual = explode("\n", $output);
        $this->assertSame(4, count($actual));
        $this->assertStringEndsWith('* Started "check-tokens"', $actual[0]);
        $this->assertStringEndsWith('Character 3: token OK', $actual[1]);
        $this->assertStringEndsWith('* Finished "check-tokens"', $actual[2]);
        $this->assertStringEndsWith('', $actual[3]);
        $this->assertSame(
            'Unexpected result from OAuth verify.',
            $this->log->getHandler()->getRecords()[0]['message']
        );
        $this->assertSame(
            ['data' => ['UNKNOWN' => 'DATA']],
            $this->log->getHandler()->getRecords()[0]['context']
        );
    }
}