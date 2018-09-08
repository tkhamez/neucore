<?php declare(strict_types=1);

namespace Tests\Functional\Core\Command;

use Brave\Core\Entity\Character;
use Brave\Core\Factory\EsiApiFactory;
use Brave\Core\Factory\RepositoryFactory;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use League\OAuth2\Client\Provider\GenericProvider;
use Psr\Log\LoggerInterface;
use Tests\Functional\ConsoleTestCase;
use Tests\Helper;
use Tests\Logger;
use Tests\OAuthTestProvider;

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
     * @var \PHPUnit\Framework\MockObject\MockObject|ClientInterface
     */
    private $client;

    public function setUp()
    {
        $h = new Helper();
        $h->emptyDb();
        $this->em = $h->getEm();

        $this->log = new Logger('Test');
        $this->client = $this->createMock(ClientInterface::class);
    }

    public function testExecuteErrorUpdate()
    {
        $c = (new Character())->setId(1)->setName('c1')
            ->setCharacterOwnerHash('coh1')->setAccessToken('at1');
        $this->em->persist($c);
        $this->em->flush();
        $this->client->method('send')->willReturn(new Response(500));

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

    public function testExecuteWithoutTokenWithCorpAndAlliance()
    {
        // setup
        $c1 = (new Character())->setId(1122)->setName('c11')
            ->setCharacterOwnerHash('coh11')->setAccessToken('at11');
        $c2 = (new Character())->setId(2233)->setName('c22')
            ->setCharacterOwnerHash('coh22')->setAccessToken('at22');
        $this->em->persist($c1);
        $this->em->persist($c2);
        $this->em->flush();

        $this->client->method('send')->willReturn(
            new Response(200, [], '{
                "name": "char xx",
                "corporation_id": 234
            }'),
            new Response(200, [], '{
                "name": "char yy",
                "corporation_id": 234
            }'),
            new Response(200, [], '{
                "name": "The Corp.",
                "ticker": "-T-T-",
                "alliance_id": 212
            }'),
            new Response(200, [], '{
                "name": "The Alli.",
                "ticker": "-A-"
            }')
        );

        // run
        $output = $this->runConsoleApp('update-chars', ['--sleep' => 0], [
            EsiApiFactory::class => (new EsiApiFactory())->setClient($this->client)
        ]);

        $this->em->clear();

        $expectedOutput = [
            'Character 1122: update OK, token N/A',
            'Character 2233: update OK, token N/A',
            'Corporation 234: update OK',
            'Alliance 212: update OK',
            'All done.',
        ];
        $this->assertSame(implode("\n", $expectedOutput)."\n", $output);

        # read result
        $this->em->clear();

        $repositoryFactory = new RepositoryFactory($this->em);

        $actualChars = $repositoryFactory->getCharacterRepository()->findBy([]);
        $this->assertSame(1122, $actualChars[0]->getId());
        $this->assertSame(2233, $actualChars[1]->getId());
        $this->assertNotNull($actualChars[0]->getLastUpdate());
        $this->assertNotNull($actualChars[1]->getLastUpdate());
        $this->assertSame(234, $actualChars[0]->getCorporation()->getId());
        $this->assertSame(234, $actualChars[1]->getCorporation()->getId());
        $this->assertSame(212, $actualChars[0]->getCorporation()->getAlliance()->getId());
        $this->assertSame(212, $actualChars[1]->getCorporation()->getAlliance()->getId());

        $actualCorps = $repositoryFactory->getCorporationRepository()->findBy([]);
        $this->assertSame(234, $actualCorps[0]->getId());

        $actualAlliances = $repositoryFactory->getAllianceRepository()->findBy([]);
        $this->assertSame(212, $actualAlliances[0]->getId());
    }

    public function testExecuteInvalidToken()
    {
        $c = (new Character())->setId(3)->setName('char1')->setCharacterOwnerHash('coh3')
            ->setAccessToken('at3')->setRefreshToken('at3')->setValidToken(false);
        $this->em->persist($c);
        $this->em->flush();

        $this->client->method('send')->willReturn(
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

    public function testExecuteValidTokenOk()
    {
        $c = (new Character())->setId(3)->setName('char1')->setCharacterOwnerHash('coh3')
            ->setAccessToken('at3')->setRefreshToken('at3')->setValidToken(false)
            ->setExpires(time() - 60*60);
        $this->em->persist($c);
        $this->em->flush();

        $this->client->method('send')->willReturn(
            new Response(200, [], '{
                "name": "char1",
                "corporation_id": 1
            }'),
            new Response(200, [], '{"access_token": "tok4"}'), // for getAccessToken()
            new Response(200, [], '{"CharacterOwnerHash": "coh3"}'), // for getResourceOwner()
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
            'Character 3: update OK, token OK',
            'Corporation 1: update OK',
            'All done.',
        ];
        $this->assertSame(implode("\n", $expectedOutput)."\n", $output);
    }

    public function testExecuteValidTokenUnexpectedData()
    {
        $c = (new Character())->setId(3)->setName('char1')->setCharacterOwnerHash('coh3')
            ->setAccessToken('at3')->setRefreshToken('at3')->setValidToken(false);
        $this->em->persist($c);
        $this->em->flush();

        $this->client->method('send')->willReturn(
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
            $this->log->getHandlers()[0]->getRecords()[0]['message']
        );
        $this->assertSame(
            ['data' => ['UNKNOWN' => 'DATA']],
            $this->log->getHandlers()[0]->getRecords()[0]['context']
        );
    }
}
