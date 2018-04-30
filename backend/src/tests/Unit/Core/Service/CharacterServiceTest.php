<?php declare(strict_types=1);

namespace Tests\Unit\Core\Service;

use Brave\Core\Entity\Alliance;
use Brave\Core\Entity\AllianceRepository;
use Brave\Core\Entity\CharacterRepository;
use Brave\Core\Entity\Corporation;
use Brave\Core\Entity\CorporationRepository;
use Brave\Core\Service\CharacterService;
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

class CharacterServiceTest extends \PHPUnit\Framework\TestCase
{
    private $testHelper;

    private $em;

    private $alliApi;

    private $charApi;

    private $corpApi;

    private $ar;

    private $corpR;

    private $charR;

    private $cs;

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

        $this->ar = new AllianceRepository($this->em);
        $this->corpR = new CorporationRepository($this->em);
        $this->charR = new CharacterRepository($this->em);
        $this->cs = new CharacterService($log, $esi, $this->em, $this->ar, $this->corpR, $this->charR);
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

    public function testFetchCharacterNoFlush()
    {
        $this->testHelper->emptyDb();
        $this->testHelper->addCharacterMain('newc', 123, []);

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
        $cDb = $this->charR->find(123);
        $this->assertNull($cDb->getCorporation());
    }

    public function testFetchCharacterFlush()
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

        $char = $this->cs->fetchCharacter(123, true);
        $this->assertSame(123, $char->getId());
        $this->assertSame('newc', $char->getName());
        $this->assertSame(234, $char->getCorporation()->getId());
        $this->assertNull($char->getCorporation()->getAlliance());

        $this->em->clear();
        $cDb = $this->charR->find(123);
        $this->assertSame(234, $cDb->getCorporation()->getId());
        $this->assertSame('UTC', $cDb->getLastUpdate()->getTimezone()->getName());
        $this->assertGreaterThan('2018-03-26 17:24:30', $cDb->getLastUpdate()->format('Y-m-d H:i:s'));
    }

    public function testFetchCorporationInvalidId()
    {
        $corp = $this->cs->fetchCorporation(-1);
        $this->assertNull($corp);
    }

    public function testFetchCorporationNotFound()
    {
        $this->corpApi->method('getCorporationsCorporationId')->will(
            $this->throwException(new \Exception('Not Found.', 404))
        );

        $corp = $this->cs->fetchCorporation(234);
        $this->assertNull($corp);
    }

    public function testFetchCorporationUpdateNoFlushNoAlliance()
    {
        $this->testHelper->emptyDb();
        $oldCorp = (new Corporation())->setId(234)->setName('will-be-updated')->setTicker('t');
        $this->em->persist($oldCorp);
        $this->em->flush();

        $this->corpApi->method('getCorporationsCorporationId')->willReturn(new GetCorporationsCorporationIdOk([
            'name' => 'The Corp.', 'ticker' => '-HAT-', 'alliance_id' => null
        ]));

        $corp = $this->cs->fetchCorporation(234);
        $this->assertSame(234, $corp->getId());
        $this->assertSame('The Corp.', $corp->getName());
        $this->assertSame('-HAT-', $corp->getTicker());
        $this->assertNull($corp->getAlliance());

        $this->em->clear();
        $cDb = $this->corpR->find(234);
        $this->assertSame('will-be-updated', $cDb->getName());
    }

    public function testFetchCorporationCreateFlushNoAlliance()
    {
        $this->testHelper->emptyDb();

        $this->corpApi->method('getCorporationsCorporationId')->willReturn(new GetCorporationsCorporationIdOk([
            'name' => 'The Corp.', 'ticker' => '-HAT-', 'alliance_id' => null
        ]));

        $corp = $this->cs->fetchCorporation(234, true);
        $this->assertSame(234, $corp->getId());
        $this->assertSame('The Corp.', $corp->getName());
        $this->assertSame('-HAT-', $corp->getTicker());
        $this->assertNull($corp->getAlliance());

        $this->em->clear();
        $cDb = $this->corpR->find(234);
        $this->assertNotNull($cDb);
    }

    public function testFetchCorporationAllianceNotFound()
    {
        $this->testHelper->emptyDb();

        $this->corpApi->method('getCorporationsCorporationId')->willReturn(new GetCorporationsCorporationIdOk([
            'name' => 'The Corp.', 'ticker' => '-HAT-', 'alliance_id' => 345
        ]));
        $this->alliApi->method('getAlliancesAllianceId')->will(
            $this->throwException(new \Exception('Not Found.', 404))
        );

        $corp = $this->cs->fetchCorporation(234, true);
        $this->assertNull($corp);
    }

    public function testFetchCorporationWithAlliance()
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

        $this->em->clear();
        $cDb = $this->corpR->find(234);
        $aDb = $this->ar->find(345);
        $this->assertNotNull($cDb);
        $this->assertNotNull($aDb);
    }

    public function testFetchAllianceInvalidId()
    {
        $alli = $this->cs->fetchAlliance(-1);
        $this->assertNull($alli);
    }

    public function testFetchAllianceNotFound()
    {
        $this->alliApi->method('getAlliancesAllianceId')->will(
            $this->throwException(new \Exception('Not Found.', 404))
        );

        $alli = $this->cs->fetchAlliance(345);
        $this->assertNull($alli);
    }

    public function testFetchAllianceUpdateNoFlush()
    {
        $this->testHelper->emptyDb();
        $oldAlli = (new Alliance())->setId(345)->setName('will-be-updated')->setTicker('t');
        $this->em->persist($oldAlli);
        $this->em->flush();

        $this->alliApi->method('getAlliancesAllianceId')->willReturn(new GetAlliancesAllianceIdOk([
            'name' => 'The A.', 'ticker' => '-A-'
        ]));

        $alli = $this->cs->fetchAlliance(345);
        $this->assertSame(345, $alli->getId());
        $this->assertSame('The A.', $alli->getName());
        $this->assertSame('-A-', $alli->getTicker());

        $this->em->clear();
        $aDb = $this->ar->find(345);
        $this->assertSame('will-be-updated', $aDb->getName());
    }

    public function testFetchAllianceCreateFlush()
    {
        $this->testHelper->emptyDb();

        $this->alliApi->method('getAlliancesAllianceId')->willReturn(new GetAlliancesAllianceIdOk([
            'name' => 'The A.', 'ticker' => '-A-'
        ]));

        $alli = $this->cs->fetchAlliance(345, true);
        $this->assertSame(345, $alli->getId());
        $this->assertSame('The A.', $alli->getName());
        $this->assertSame('-A-', $alli->getTicker());

        $this->em->clear();
        $aDb = $this->ar->find(345);
        $this->assertNotNull($aDb);
    }
}
