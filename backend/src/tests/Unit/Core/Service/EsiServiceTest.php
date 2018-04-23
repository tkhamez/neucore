<?php declare(strict_types=1);

namespace Tests\Unit\Core\Service;

use Brave\Core\Service\EsiService;
use Brave\Core\Service\EveTokenService;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Tests\Helper;
use League\OAuth2\Client\Provider\GenericProvider;
use Swagger\Client\Eve\Api\CorporationApi;
use Swagger\Client\Eve\Api\AllianceApi;
use Swagger\Client\Eve\Model\GetAlliancesAllianceIdOk;
use Swagger\Client\Eve\Model\GetCorporationsCorporationIdOk;

class EsiServiceTest extends \PHPUnit\Framework\TestCase
{
    private $log;

    private $alliApi;

    private $corpApi;

    private $esi;

    public function setUp()
    {
        $h = new Helper();
        $em = $h->getEm();

        $this->log = new Logger('Test');
        $this->log->pushHandler(new TestHandler());

        $oauth = $this->createMock(GenericProvider::class);
        $ts = new EveTokenService($oauth, $em, $this->log);

        $this->alliApi = $this->createMock(AllianceApi::class);
        $this->corpApi = $this->createMock(CorporationApi::class);
        $this->esi = new EsiService($this->log, $ts, $this->alliApi, $this->corpApi);
    }

    public function testGetAllianceException404()
    {
        $this->alliApi->method('getAlliancesAllianceId')->will(
            $this->throwException(new \Exception('Not Found.', 404))
        );

        $result = $this->esi->getAlliance(456);

        $this->assertNull($result);
        $this->assertSame(404, $this->esi->getLastErrorCode());
        $this->assertSame('Not Found.', $this->esi->getLastErrorMessage());
        $this->assertSame(0, count($this->log->getHandlers()[0]->getRecords()));
    }

    public function testGetAllianceException500()
    {
        $this->alliApi->method('getAlliancesAllianceId')->will(
            $this->throwException(new \Exception('Oops.', 500))
        );

        $result = $this->esi->getAlliance(456);

        $this->assertNull($result);
        $this->assertSame(500, $this->esi->getLastErrorCode());
        $this->assertSame('Oops.', $this->esi->getLastErrorMessage());
        $this->assertSame('Oops.', $this->log->getHandlers()[0]->getRecords()[0]['message']);
    }

    public function testGetAlliance()
    {
        $this->alliApi->method('getAlliancesAllianceId')->willReturn(new GetAlliancesAllianceIdOk([
            'name' => 'The Alliance.',
            'ticker' => '-HAT-',
        ]));

        $result = $this->esi->getAlliance(456);
        $this->assertNull($this->esi->getLastErrorCode());
        $this->assertNull($this->esi->getLastErrorMessage());
        $this->assertInstanceOf(GetAlliancesAllianceIdOk::class, $result);
        $this->assertSame('The Alliance.', $result->getName());
        $this->assertSame('-HAT-', $result->getTicker());
    }

    public function testGetCorporation404()
    {
        $this->corpApi->method('getCorporationsCorporationId')->will(
            $this->throwException(new \Exception('Not Found.', 404))
        );

        $result = $this->esi->getCorporation(123);

        $this->assertNull($result);
        $this->assertSame(404, $this->esi->getLastErrorCode());
        $this->assertSame('Not Found.', $this->esi->getLastErrorMessage());
        $this->assertSame(0, count($this->log->getHandlers()[0]->getRecords()));
    }

    public function testGetCorporation500()
    {
        $this->corpApi->method('getCorporationsCorporationId')->will(
            $this->throwException(new \Exception('Oops.', 500))
        );

        $result = $this->esi->getCorporation(123);

        $this->assertNull($result);
        $this->assertSame(500, $this->esi->getLastErrorCode());
        $this->assertSame('Oops.', $this->esi->getLastErrorMessage());
        $this->assertSame('Oops.', $this->log->getHandlers()[0]->getRecords()[0]['message']);
    }

    public function testGetCorporation()
    {
        $this->corpApi->method('getCorporationsCorporationId')->willReturn(new GetCorporationsCorporationIdOk([
            'name' => 'The Corp.',
            'ticker' => '-HAT-',
            'alliance_id' => 123
        ]));

        $result = $this->esi->getCorporation(123);
        $this->assertNull($this->esi->getLastErrorCode());
        $this->assertNull($this->esi->getLastErrorMessage());
        $this->assertInstanceOf(GetCorporationsCorporationIdOk::class, $result);
        $this->assertSame('The Corp.', $result->getName());
        $this->assertSame('-HAT-', $result->getTicker());
    }
}
