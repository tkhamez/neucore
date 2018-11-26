<?php declare(strict_types=1);

namespace Tests\Functional\Core\Command;

use Brave\Core\Entity\Character;
use Brave\Core\Entity\Player;
use Brave\Core\Factory\EsiApiFactory;
use Brave\Core\Factory\RepositoryFactory;
use GuzzleHttp\Psr7\Response;
use League\OAuth2\Client\Provider\GenericProvider;
use Psr\Log\LoggerInterface;
use Tests\Client;
use Tests\ConsoleTestCase;
use Tests\Helper;
use Tests\Logger;
use Tests\OAuthProvider;

class UpdateCharactersTest extends ConsoleTestCase
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

    public function testExecuteErrorUpdateChar()
    {
        $c = (new Character())->setId(1)->setName('c1')
            ->setCharacterOwnerHash('coh1')->setAccessToken('at1');
        $this->em->persist($c);
        $this->em->flush();
        $this->client->setResponse(new Response(500));

        $output = $this->runConsoleApp('update-chars', ['--sleep' => 0], [
            EsiApiFactory::class => (new EsiApiFactory())->setClient($this->client),
            LoggerInterface::class => $this->log
        ]);

        $actual = explode("\n", $output);
        $this->assertSame(4, count($actual));
        $this->assertStringEndsWith('* Started "update-chars"', $actual[0]);
        $this->assertStringEndsWith('Character 1: update failed', $actual[1]);
        $this->assertStringEndsWith('* Finished "update-chars"', $actual[2]);
        $this->assertStringEndsWith('', $actual[3]);
    }

    public function testExecuteDeleteCharBiomassed()
    {
        $player = (new Player())->setName('p');
        $char = (new Character())->setId(3)->setName('char1')->setPlayer($player);
        $this->em->persist($player);
        $this->em->persist($char);
        $this->em->flush();

        $this->client->setResponse(
        // for getCharactersCharacterId()
            new Response(200, [], '{
                "name": "char1",
                "corporation_id": 1000001
            }'),

            // for getCorporationsCorporationId()
            new Response(200, [], '{
                "name": "corp1",
                "ticker": "t"
            }')
        );

        $output = $this->runConsoleApp('update-chars', ['--sleep' => 0], [
            EsiApiFactory::class => (new EsiApiFactory())->setClient($this->client),
            GenericProvider::class => new OAuthProvider($this->client),
        ]);

        $actual = explode("\n", $output);
        $this->assertSame(5, count($actual));
        $this->assertStringEndsWith('* Started "update-chars"', $actual[0]);
        $this->assertStringEndsWith('Character 3: update OK, character deleted', $actual[1]);
        $this->assertStringEndsWith('Corporation 1000001: update OK', $actual[2]);
        $this->assertStringEndsWith('* Finished "update-chars"', $actual[3]);
        $this->assertStringEndsWith('', $actual[4]);

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
            // for getCharactersCharacterId()
            new Response(200, [], '{
                "name": "char1",
                "corporation_id": 1
            }'),

            new Response(400), // for getResourceOwner()

            // for getCorporationsCorporationId()
            new Response(200, [], '{
                "name": "corp1",
                "ticker": "t"
            }')
        );

        $output = $this->runConsoleApp('update-chars', ['--sleep' => 0], [
            EsiApiFactory::class => (new EsiApiFactory())->setClient($this->client),
            GenericProvider::class => new OAuthProvider($this->client),
            LoggerInterface::class => $this->log
        ]);

        $actual = explode("\n", $output);
        $this->assertSame(5, count($actual));
        $this->assertStringEndsWith('* Started "update-chars"', $actual[0]);
        $this->assertStringEndsWith('Character 3: update OK, token failed', $actual[1]);
        $this->assertStringEndsWith('Corporation 1: update OK', $actual[2]);
        $this->assertStringEndsWith('* Finished "update-chars"', $actual[3]);
        $this->assertStringEndsWith('', $actual[4]);
    }

    public function testExecuteInvalidToken()
    {
        $c = (new Character())->setId(3)->setName('char1')
            ->setAccessToken('at3')->setRefreshToken('at3')->setExpires(time() - 1000);
        $this->em->persist($c);
        $this->em->flush();

        $this->client->setResponse(
            // for getCharactersCharacterId()
            new Response(200, [], '{
                "name": "char1",
                "corporation_id": 1
            }'),

            new Response(400, [], '{"error": "invalid_token"}'), // for getAccessToken()

            // for getCorporationsCorporationId()
            new Response(200, [], '{
                "name": "corp1",
                "ticker": "t"
            }')
        );

        $output = $this->runConsoleApp('update-chars', ['--sleep' => 0], [
            EsiApiFactory::class => (new EsiApiFactory())->setClient($this->client),
            GenericProvider::class => new OAuthProvider($this->client),
            LoggerInterface::class => $this->log
        ]);

        $actual = explode("\n", $output);
        $this->assertSame(5, count($actual));
        $this->assertStringEndsWith('* Started "update-chars"', $actual[0]);
        $this->assertStringEndsWith('Character 3: update OK, token NOK', $actual[1]);
        $this->assertStringEndsWith('Corporation 1: update OK', $actual[2]);
        $this->assertStringEndsWith('* Finished "update-chars"', $actual[3]);
        $this->assertStringEndsWith('', $actual[4]);
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
            new Response(200, [], '{
                "name": "char1",
                "corporation_id": 101
            }'),
            new Response(200, [], '{"access_token": "tok4"}'), // for getAccessToken()
            new Response(200, [], '{"CharacterOwnerHash": "coh4"}'), // for getResourceOwner()
            new Response(200, [], '{
                "name": "corp1",
                "ticker": "t"
            }')
        );

        $output = $this->runConsoleApp('update-chars', ['--sleep' => 0], [
            EsiApiFactory::class => (new EsiApiFactory())->setClient($this->client),
            GenericProvider::class => new OAuthProvider($this->client),
        ]);

        $actual = explode("\n", $output);
        $this->assertSame(5, count($actual));
        $this->assertStringEndsWith('* Started "update-chars"', $actual[0]);
        $this->assertStringEndsWith('Character 3: update OK, character deleted', $actual[1]);
        $this->assertStringEndsWith('Corporation 101: update OK', $actual[2]);
        $this->assertStringEndsWith('* Finished "update-chars"', $actual[3]);
        $this->assertStringEndsWith('', $actual[4]);

        # read result
        $this->em->clear();
        $repositoryFactory = new RepositoryFactory($this->em);
        $actualChars = $repositoryFactory->getCharacterRepository()->findBy([]);
        $this->assertSame(0, count($actualChars));
        $removedChar = $repositoryFactory->getRemovedCharacterRepository()->findBy([]);
        $this->assertSame(1, count($removedChar));
    }

    public function testExecuteValidTokenWithAlliance()
    {
        $c = (new Character())->setId(3)->setName('char1')->setCharacterOwnerHash('coh3')
            ->setAccessToken('at3')->setRefreshToken('at3')->setValidToken(false)
            ->setExpires(time() - 60*60);
        $this->em->persist($c);
        $this->em->flush();

        $this->client->setResponse(
            new Response(200, [], '{
                "name": "char1",
                "corporation_id": 101
            }'),
            new Response(200, [], '{"access_token": "tok4"}'), // for getAccessToken()
            new Response(200, [], '{"CharacterOwnerHash": "coh3"}'), // for getResourceOwner()
            new Response(200, [], '{
                "name": "corp1",
                "ticker": "t",
                "alliance_id": 212
            }'),
            new Response(200, [], '{
                "name": "The Alli.",
                "ticker": "-A-"
            }')
        );

        $output = $this->runConsoleApp('update-chars', ['--sleep' => 0], [
            EsiApiFactory::class => (new EsiApiFactory())->setClient($this->client),
            GenericProvider::class => new OAuthProvider($this->client),
        ]);

        $actual = explode("\n", $output);
        $this->assertSame(6, count($actual));
        $this->assertStringEndsWith('* Started "update-chars"', $actual[0]);
        $this->assertStringEndsWith('Character 3: update OK, token OK', $actual[1]);
        $this->assertStringEndsWith('Corporation 101: update OK', $actual[2]);
        $this->assertStringEndsWith('Alliance 212: update OK', $actual[3]);
        $this->assertStringEndsWith('* Finished "update-chars"', $actual[4]);
        $this->assertStringEndsWith('', $actual[5]);

        # read result
        $this->em->clear();

        $repositoryFactory = new RepositoryFactory($this->em);

        $actualChars = $repositoryFactory->getCharacterRepository()->findBy([]);
        $this->assertSame(1, count($actualChars));
        $this->assertSame(3, $actualChars[0]->getId());
        $this->assertNotNull($actualChars[0]->getLastUpdate());
        $this->assertSame(101, $actualChars[0]->getCorporation()->getId());
        $this->assertSame(212, $actualChars[0]->getCorporation()->getAlliance()->getId());

        $actualCorps = $repositoryFactory->getCorporationRepository()->findBy([]);
        $this->assertSame(1, count($actualCorps));
        $this->assertSame(101, $actualCorps[0]->getId());

        $actualAlliances = $repositoryFactory->getAllianceRepository()->findBy([]);
        $this->assertSame(1, count($actualAlliances));
        $this->assertSame(212, $actualAlliances[0]->getId());
    }

    public function testExecuteValidTokenUnexpectedData()
    {
        $c = (new Character())->setId(3)->setName('char1')->setCharacterOwnerHash('coh3')
            ->setAccessToken('at3')->setRefreshToken('at3')->setValidToken(false);
        $this->em->persist($c);
        $this->em->flush();

        $this->client->setResponse(
            new Response(200, [], '{
                "name": "char1",
                "corporation_id": 1
            }'),
            // Token has no expire time, so no call to GenericProvider->getAccessToken()
            new Response(200, [], '{"UNKNOWN": "DATA"}'), // for getResourceOwner()
            new Response(200, [], '{
                "name": "corp1",
                "ticker": "t"
            }')
        );

        $output = $this->runConsoleApp('update-chars', ['--sleep' => 0], [
            EsiApiFactory::class => (new EsiApiFactory())->setClient($this->client),
            GenericProvider::class => new OAuthProvider($this->client),
            LoggerInterface::class => $this->log,
        ]);

        $actual = explode("\n", $output);
        $this->assertSame(5, count($actual));
        $this->assertStringEndsWith('* Started "update-chars"', $actual[0]);
        $this->assertStringEndsWith('Character 3: update OK, token OK', $actual[1]);
        $this->assertStringEndsWith('Corporation 1: update OK', $actual[2]);
        $this->assertStringEndsWith('* Finished "update-chars"', $actual[3]);
        $this->assertStringEndsWith('', $actual[4]);
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
