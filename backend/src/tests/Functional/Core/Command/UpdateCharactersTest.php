<?php declare(strict_types=1);

namespace Tests\Functional\Core\Command;

use Brave\Core\Entity\Character;
use Brave\Core\Repository\RepositoryFactory;
use League\OAuth2\Client\Provider\GenericProvider;
use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use Monolog\Logger;
use Monolog\Handler\TestHandler;
use Psr\Log\LoggerInterface;
use Swagger\Client\Eve\Api\AllianceApi;
use Swagger\Client\Eve\Api\CharacterApi;
use Swagger\Client\Eve\Api\CorporationApi;
use Swagger\Client\Eve\Model\GetAlliancesAllianceIdOk;
use Swagger\Client\Eve\Model\GetCharactersCharacterIdOk;
use Swagger\Client\Eve\Model\GetCorporationsCorporationIdOk;
use Tests\Functional\ConsoleTestCase;
use Tests\Helper;

class UpdateCharactersTest extends ConsoleTestCase
{
    /**
     * @var \Doctrine\ORM\EntityManagerInterface
     */
    private $em;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject
     */
    private $charApi;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject
     */
    private $corpApi;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject
     */
    private $alliApi;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject
     */
    private $oauth;

    public function setUp()
    {
        $h = new Helper();
        $h->emptyDb();
        $this->em = $h->getEm();

        $this->charApi = $this->createMock(CharacterApi::class);
        $this->corpApi = $this->createMock(CorporationApi::class);
        $this->alliApi = $this->createMock(AllianceApi::class);
        $this->oauth = $this->createMock(GenericProvider::class);
    }

    public function testExecuteErrorUpdate()
    {
        $c = (new Character())->setId(1)->setName('c1')
            ->setCharacterOwnerHash('coh1')->setAccessToken('at1');
        $this->em->persist($c);
        $this->em->flush();
        $this->charApi->method('getCharactersCharacterId')->willReturn(null);

        $output = $this->runConsoleApp('update-chars', ['--sleep' => 0], [
            CharacterApi::class => $this->charApi
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
        $this->charApi->method('getCharactersCharacterId')->willReturn(new GetCharactersCharacterIdOk([
            'name' => 'char xx', 'corporation_id' => 234
        ]));
        $this->corpApi->method('getCorporationsCorporationId')->willReturn(new GetCorporationsCorporationIdOk([
            'name' => 'The Corp.', 'ticker' => '-T-T-', 'alliance_id' => 212
        ]));
        $this->alliApi->method('getAlliancesAllianceId')->willReturn(new GetAlliancesAllianceIdOk([
            'name' => 'The Alli.', 'ticker' => '-A-'
        ]));

        // run
        $output = $this->runConsoleApp('update-chars', ['--sleep' => 0], [
            CharacterApi::class => $this->charApi,
            CorporationApi::class => $this->corpApi,
            AllianceApi::class => $this->alliApi,
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

        $this->charApi->method('getCharactersCharacterId')->willReturn(new GetCharactersCharacterIdOk([
            'name' => 'char1', 'corporation_id' => 1
        ]));
        $this->corpApi->method('getCorporationsCorporationId')->willReturn(new GetCorporationsCorporationIdOk([
            'name' => 'corp1', 'ticker' => 't'
        ]));
        $this->oauth->method('getAccessToken')->willReturn(null);
        $this->oauth->method('getResourceOwner')->willReturn(null);

        $output = $this->runConsoleApp('update-chars', ['--sleep' => 0], [
            CharacterApi::class => $this->charApi,
            CorporationApi::class => $this->corpApi,
            GenericProvider::class => $this->oauth,
        ]);

        $expectedOutput = [
            'Character 3: update OK, token NOK',
            'Corporation 1: update OK',
            'All done.',
        ];
        $this->assertSame(implode("\n", $expectedOutput)."\n", $output);
    }

    public function testExecuteValidToken()
    {
        $c = (new Character())->setId(3)->setName('char1')->setCharacterOwnerHash('coh3')
            ->setAccessToken('at3')->setRefreshToken('at3')->setValidToken(false);
        $this->em->persist($c);
        $this->em->flush();

        $this->charApi->method('getCharactersCharacterId')->willReturn(new GetCharactersCharacterIdOk([
            'name' => 'char1', 'corporation_id' => 1
        ]));
        $this->corpApi->method('getCorporationsCorporationId')->willReturn(new GetCorporationsCorporationIdOk([
            'name' => 'corp1', 'ticker' => 't'
        ]));
        $this->oauth->method('getAccessToken')->willReturn(null);
        $ro = $this->createMock(ResourceOwnerInterface::class);
        $ro->method('toArray')->willReturn([
            'CharacterOwnerHash' => 'coh3',
        ]);
        $this->oauth->method('getResourceOwner')->willReturn($ro);

        $output = $this->runConsoleApp('update-chars', ['--sleep' => 0], [
            CharacterApi::class => $this->charApi,
            CorporationApi::class => $this->corpApi,
            GenericProvider::class => $this->oauth,
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

        $this->charApi->method('getCharactersCharacterId')->willReturn(new GetCharactersCharacterIdOk([
            'name' => 'char1', 'corporation_id' => 1
        ]));
        $this->corpApi->method('getCorporationsCorporationId')->willReturn(new GetCorporationsCorporationIdOk([
            'name' => 'corp1', 'ticker' => 't'
        ]));
        $this->oauth->method('getAccessToken')->willReturn(null);
        $ro = $this->createMock(ResourceOwnerInterface::class);
        $ro->method('toArray')->willReturn([
            'UNKNOWN' => 'DATA',
        ]);
        $this->oauth->method('getResourceOwner')->willReturn($ro);

        $log = new Logger('Test');
        $log->pushHandler(new TestHandler());

        $output = $this->runConsoleApp('update-chars', ['--sleep' => 0], [
            CharacterApi::class => $this->charApi,
            CorporationApi::class => $this->corpApi,
            GenericProvider::class => $this->oauth,
            LoggerInterface::class => $log,
        ]);

        $expectedOutput = [
            'Character 3: update OK, token OK',
            'Corporation 1: update OK',
            'All done.',
        ];
        $this->assertSame(implode("\n", $expectedOutput)."\n", $output);
        $this->assertSame(
            'Unexpected result from OAuth verify.',
            $log->getHandlers()[0]->getRecords()[0]['message']
        );
        $this->assertSame(
            ['data' => ['UNKNOWN' => 'DATA']],
            $log->getHandlers()[0]->getRecords()[0]['context']
        );
    }
}
