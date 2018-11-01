<?php declare(strict_types=1);

namespace Tests\Functional\Core\Command;

use Brave\Core\Entity\Character;
use Brave\Core\Factory\EsiApiFactory;
use Brave\Core\Factory\RepositoryFactory;
use GuzzleHttp\Psr7\Response;
use League\OAuth2\Client\Provider\GenericProvider;
use Psr\Log\LoggerInterface;
use Tests\Functional\ConsoleTestCase;
use Tests\Helper;
use Tests\TestLogger;
use Tests\OAuthTestProvider;
use Tests\TestClient;

class UpdateCharactersTest extends ConsoleTestCase
{
    /**
     * @var \Doctrine\ORM\EntityManagerInterface
     */
    private $em;

    /**
     * @var TestLogger
     */
    private $log;

    /**
     * @var TestClient
     */
    private $client;

    public function setUp()
    {
        $h = new Helper();
        $h->emptyDb();
        $this->em = $h->getEm();

        $this->log = new TestLogger('Test');
        $this->client = new TestClient();
    }

    public function testExecuteErrorUpdate()
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

        $expectedOutput = [
            'Character 1: error updating.',
            'All done.',
        ];
        $this->assertSame(implode("\n", $expectedOutput)."\n", $output);
    }

    public function testExecuteInvalidToken()
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
            new Response(200, [], 'invalid'), // for getResourceOwner()
            new Response(200, [], '{
                "name": "corp1",
                "ticker": "t"
            }')
        );

        $output = $this->runConsoleApp('update-chars', ['--sleep' => 0], [
            EsiApiFactory::class => (new EsiApiFactory())->setClient($this->client),
            GenericProvider::class => new OAuthTestProvider($this->client),
            LoggerInterface::class => $this->log
        ]);

        $expectedOutput = [
            'Character 3: update OK, token NOK',
            'Corporation 1: update OK',
            'All done.',
        ];
        $this->assertSame(implode("\n", $expectedOutput)."\n", $output);
    }

    public function testExecuteCharacterDeleted()
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
            new Response(200, [], '{"CharacterOwnerHash": "coh4"}'), // for getResourceOwner()
            new Response(200, [], '{
                "name": "corp1",
                "ticker": "t"
            }')
        );

        $output = $this->runConsoleApp('update-chars', ['--sleep' => 0], [
            EsiApiFactory::class => (new EsiApiFactory())->setClient($this->client),
            GenericProvider::class => new OAuthTestProvider($this->client),
        ]);

        $expectedOutput = [
            'Character 3: update OK, character deleted',
            'Corporation 101: update OK',
            'All done.',
        ];
        $this->assertSame(implode("\n", $expectedOutput)."\n", $output);

        # read result
        $this->em->clear();

        $repositoryFactory = new RepositoryFactory($this->em);

        $actualChars = $repositoryFactory->getCharacterRepository()->findBy([]);
        $this->assertSame(0, count($actualChars));
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
            GenericProvider::class => new OAuthTestProvider($this->client),
        ]);

        $expectedOutput = [
            'Character 3: update OK, token OK',
            'Corporation 101: update OK',
            'Alliance 212: update OK',
            'All done.',
        ];
        $this->assertSame(implode("\n", $expectedOutput)."\n", $output);

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
            GenericProvider::class => new OAuthTestProvider($this->client),
            LoggerInterface::class => $this->log,
        ]);

        $expectedOutput = [
            'Character 3: update OK, token OK',
            'Corporation 1: update OK',
            'All done.',
        ];
        $this->assertSame(implode("\n", $expectedOutput)."\n", $output);
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
