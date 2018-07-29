<?php declare(strict_types=1);

namespace Tests\Unit\Core\Service;

use Brave\Core\Entity\Alliance;
use Brave\Core\Entity\AllianceRepository;
use Brave\Core\Entity\CharacterRepository;
use Brave\Core\Entity\Corporation;
use Brave\Core\Entity\CorporationRepository;
use Brave\Core\Service\EsiCharacter;
use Brave\Core\Service\EsiApi;
use Brave\Core\Service\OAuthToken;
use Monolog\Logger;
use Monolog\Handler\TestHandler;
use League\OAuth2\Client\Provider\GenericProvider;
use Swagger\Client\Eve\Api\AllianceApi;
use Swagger\Client\Eve\Api\CorporationApi;
use Swagger\Client\Eve\Api\CharacterApi;
use Swagger\Client\Eve\Model\GetAlliancesAllianceIdOk;
use Swagger\Client\Eve\Model\GetCharactersCharacterIdOk;
use Swagger\Client\Eve\Model\GetCorporationsCorporationIdOk;
use Tests\Helper;
use Tests\WriteErrorListener;

class EsiCharacterTest extends \PHPUnit\Framework\TestCase
{
    private $testHelper;

    private $em;

    private $alliApi;

    private $charApi;

    private $corpApi;

    private $alliRepo;

    private $corpRepo;

    private $charRepo;

    private $cs;

    private $csError;

    public function setUp()
    {
        $this->testHelper = new Helper();
        $this->em = $this->testHelper->getEm();

        $log = new Logger('Test');
        $log->pushHandler(new TestHandler());

        $oauth = $this->createMock(GenericProvider::class);
        $ts = new OAuthToken($oauth, $this->em, $log);

        $this->alliApi = $this->createMock(AllianceApi::class);
        $this->corpApi = $this->createMock(CorporationApi::class);
        $this->charApi = $this->createMock(CharacterApi::class);
        $esi = new EsiApi($log, $ts, $this->alliApi, $this->corpApi, $this->charApi);

        $this->alliRepo = new AllianceRepository($this->em);
        $this->corpRepo = new CorporationRepository($this->em);
        $this->charRepo = new CharacterRepository($this->em);
        $this->cs = new EsiCharacter($log, $esi, $this->em, $this->alliRepo, $this->corpRepo, $this->charRepo);

        // a second EsiCharacter instance with another EntityManager that throws an exception on flush.
        $em = (new Helper())->getEm(true);
        $em->getEventManager()->addEventListener(\Doctrine\ORM\Events::onFlush, new WriteErrorListener());
        $this->csError = new EsiCharacter($log, $esi, $em, $this->alliRepo, $this->corpRepo, $this->charRepo);
    }

    public function testGetEsiApi()
    {
        $this->assertInstanceOf(EsiApi::class, $this->cs->getEsiApi());
    }

    public function testFetchCharacterInvalidId()
    {
        $char = $this->cs->fetchCharacter(-1);
        $this->assertNull($char);
    }

    public function testFetchCharacterNotFound()
    {
        $this->charApi->method('getCharactersCharacterId')->will(
            $this->throwException(new \Exception('Not Found.', 404))
        );

        $char = $this->cs->fetchCharacter(123);
        $this->assertNull($char);
    }

    public function testFetchCharacterNotInDB()
    {
        $this->testHelper->emptyDb();

        $this->charApi->method('getCharactersCharacterId')->willReturn(new GetCharactersCharacterIdOk([
            'name' => 'newc', 'corporation_id' => 234
        ]));

        $char = $this->cs->fetchCharacter(123);
        $this->assertNull($char);
    }

    public function testFetchCharacterCorpNotFound()
    {
        $this->testHelper->emptyDb();
        $this->testHelper->addCharacterMain('newc', 123, []);

        $this->charApi->method('getCharactersCharacterId')->willReturn(new GetCharactersCharacterIdOk([
            'name' => 'newc', 'corporation_id' => 234
        ]));
        $this->corpApi->method('getCorporationsCorporationId')->will(
            $this->throwException(new \Exception('Not Found.', 404))
        );

        $char = $this->cs->fetchCharacter(123);
        $this->assertNull($char);
    }

    public function testFetchCharacterUpdateCorpFalse()
    {
        $this->testHelper->emptyDb();
        $this->testHelper->addCharacterMain('newc', 123, []);
        $oldCorp = (new Corporation())->setId(234)->setName('old-name')->setTicker('t');
        $this->em->persist($oldCorp);
        $this->em->flush();

        $this->charApi->method('getCharactersCharacterId')->willReturn(new GetCharactersCharacterIdOk([
            'name' => 'newc', 'corporation_id' => 234
        ]));

        $char = $this->cs->fetchCharacter(123, false);

        $this->assertSame(123, $char->getId());
        $this->assertSame('newc', $char->getName());
        $this->assertSame(234, $char->getCorporation()->getId());
        $this->assertSame('old-name', $char->getCorporation()->getName());
    }

    public function testFetchCharacter()
    {
        $this->testHelper->emptyDb();
        $char = $this->testHelper->addCharacterMain('newc', 123, []);
        $char->setLastUpdate(new \DateTime('2018-03-26 17:24:30'));
        $this->em->flush();

        $this->charApi->method('getCharactersCharacterId')->willReturn(new GetCharactersCharacterIdOk([
            'name' => 'newc', 'corporation_id' => 234
        ]));
        $this->corpApi->method('getCorporationsCorporationId')->willReturn(new GetCorporationsCorporationIdOk([
            'name' => 'The Corp.', 'ticker' => '-HAT-', 'alliance_id' => null
        ]));

        $char = $this->cs->fetchCharacter(123);
        $this->assertSame(123, $char->getId());
        $this->assertSame('newc', $char->getName());
        $this->assertSame(234, $char->getCorporation()->getId());
        $this->assertNull($char->getCorporation()->getAlliance());

        $this->em->clear();
        $cDb = $this->charRepo->find(123);
        $this->assertSame(234, $cDb->getCorporation()->getId());
        $this->assertSame('UTC', $cDb->getLastUpdate()->getTimezone()->getName());
        $this->assertGreaterThan('2018-03-26 17:24:30', $cDb->getLastUpdate()->format('Y-m-d H:i:s'));
    }

    public function testFetchCorporationInvalidId()
    {
        $corp = $this->cs->fetchCorporation(-1);
        $this->assertNull($corp);
    }

    public function testFetchCorporationChecksEsiWithUpdateFalse()
    {
        $this->testHelper->emptyDb();
        $this->corpApi->method('getCorporationsCorporationId')->will(
            $this->throwException(new \Exception('Not Found.', 404))
        );

        $corp = $this->cs->fetchCorporation(234, false);
        $this->assertNull($corp);
    }

    public function testFetchCorporationNoUpdate()
    {
        $this->testHelper->emptyDb();
        $oldCorp = (new Corporation())->setId(234)->setName('old-name')->setTicker('t');
        $this->em->persist($oldCorp);
        $this->em->flush();

        $corp = $this->cs->fetchCorporation(234, false);
        $this->assertSame(234, $corp->getId());
        $this->assertSame('old-name', $corp->getName());
        $this->assertSame('t', $corp->getTicker());
        $this->assertNull($corp->getAlliance());
    }

    public function testFetchCorporationNoFlush()
    {
        $this->testHelper->emptyDb();

        $this->corpApi->method('getCorporationsCorporationId')->willReturn(new GetCorporationsCorporationIdOk([
            'name' => 'The Corp.', 'ticker' => '-HAT-', 'alliance_id' => null
        ]));

        $corp = $this->cs->fetchCorporation(234, true, false);
        $this->assertSame(234, $corp->getId());
        $this->assertSame('The Corp.', $corp->getName());
        $this->assertSame('-HAT-', $corp->getTicker());
        $this->assertNull($corp->getAlliance());

        $this->em->clear();
        $cDb = $this->corpRepo->find(234);
        $this->assertNull($cDb);
    }

    public function testFetchCorporation()
    {
        $this->testHelper->emptyDb();

        $this->corpApi->method('getCorporationsCorporationId')->willReturn(new GetCorporationsCorporationIdOk([
            'name' => 'The Corp.', 'ticker' => '-HAT-', 'alliance_id' => 345
        ]));
        $this->alliApi->method('getAlliancesAllianceId')->willReturn(new GetAlliancesAllianceIdOk([
            'name' => 'The A.', 'ticker' => '-A-'
        ]));

        $corp = $this->cs->fetchCorporation(234, true);
        $this->assertSame(234, $corp->getId());
        $this->assertSame('The Corp.', $corp->getName());
        $this->assertSame('-HAT-', $corp->getTicker());
        $this->assertSame(345, $corp->getAlliance()->getId());
        $this->assertSame('The A.', $corp->getAlliance()->getName());
        $this->assertSame('-A-', $corp->getAlliance()->getTicker());
        $this->assertSame('UTC', $corp->getLastUpdate()->getTimezone()->getName());
        $this->assertGreaterThan('2018-07-29 16:30:30', $corp->getLastUpdate()->format('Y-m-d H:i:s'));

        $this->em->clear();
        $cDb = $this->corpRepo->find(234);
        $aDb = $this->alliRepo->find(345);
        $this->assertNotNull($cDb);
        $this->assertNotNull($aDb);
    }

    public function testFetchCorporationNoAllianceRemovesAlliance()
    {
        $this->testHelper->emptyDb();
        $alli = (new Alliance())->setId(100)->setName('A')->setTicker('a');
        $corp = (new Corporation())->setId(200)->setName('C')->setTicker('c')->setAlliance($alli);
        $this->em->persist($alli);
        $this->em->persist($corp);
        $this->em->flush();
        $this->em->clear();

        $this->corpApi->method('getCorporationsCorporationId')->willReturn(new GetCorporationsCorporationIdOk([
            'name' => 'C', 'ticker' => 'c', 'alliance_id' => null
        ]));

        $corpResult = $this->cs->fetchCorporation(200, true);
        $this->assertNull($corpResult->getAlliance());
        $this->em->clear();

        // load from DB
        $corporation = $this->corpRepo->find(200);
        $this->assertNull($corporation->getAlliance());
        $alliance = $this->alliRepo->find(100);
        $this->assertSame([], $alliance->getCorporations());
    }

    public function testFetchAllianceInvalidId()
    {
        $alli = $this->cs->fetchAlliance(-1);
        $this->assertNull($alli);
    }

    public function testFetchAllianceChecksEsiWithUpdateFalse()
    {
        $this->alliApi->method('getAlliancesAllianceId')->will(
            $this->throwException(new \Exception('Not Found.', 404))
        );

        $alli = $this->cs->fetchAlliance(345, false);
        $this->assertNull($alli);
    }

    public function testFetchAllianceNoUpdate()
    {
        $this->testHelper->emptyDb();
        $oldAlli = (new Alliance())->setId(345)->setName('will-be-updated')->setTicker('t');
        $this->em->persist($oldAlli);
        $this->em->flush();
        $this->em->clear();

        $this->alliApi->method('getAlliancesAllianceId')->willReturn(new GetAlliancesAllianceIdOk([
            'name' => 'The A.', 'ticker' => '-A-'
        ]));

        $alli = $this->cs->fetchAlliance(345, false);
        $this->assertSame(345, $alli->getId());
        $this->assertSame('will-be-updated', $alli->getName());
        $this->assertSame('t', $alli->getTicker());
    }

    public function testFetchAllianceNoFlush()
    {
        $this->testHelper->emptyDb();

        $this->alliApi->method('getAlliancesAllianceId')->willReturn(new GetAlliancesAllianceIdOk([
            'name' => 'The A.', 'ticker' => '-A-'
        ]));

        $alli = $this->cs->fetchAlliance(345, true, false);
        $this->assertSame(345, $alli->getId());
        $this->assertSame('The A.', $alli->getName());
        $this->assertSame('-A-', $alli->getTicker());

        $this->em->clear();
        $aDb = $this->alliRepo->find(345);
        $this->assertNull($aDb);
    }

    public function testFetchAlliance()
    {
        $this->testHelper->emptyDb();

        $this->alliApi->method('getAlliancesAllianceId')->willReturn(new GetAlliancesAllianceIdOk([
            'name' => 'The A.', 'ticker' => '-A-'
        ]));

        $alli = $this->cs->fetchAlliance(345);
        $this->assertSame(345, $alli->getId());
        $this->assertSame('The A.', $alli->getName());
        $this->assertSame('-A-', $alli->getTicker());
        $this->assertSame('UTC', $alli->getLastUpdate()->getTimezone()->getName());
        $this->assertGreaterThan('2018-07-29 16:30:30', $alli->getLastUpdate()->format('Y-m-d H:i:s'));

        $this->em->clear();
        $aDb = $this->alliRepo->find(345);
        $this->assertSame(345, $aDb->getId());
    }

    public function testFetchAllianceCreateFlushError()
    {
        $this->testHelper->emptyDb();

        $this->alliApi->method('getAlliancesAllianceId')->willReturn(new GetAlliancesAllianceIdOk([
            'name' => 'A', 'ticker' => 'A'
        ]));

        $alli = $this->csError->fetchAlliance(345, true);
        $this->assertNull($alli);
    }
}
