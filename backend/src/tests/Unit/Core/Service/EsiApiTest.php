<?php declare(strict_types=1);

namespace Tests\Unit\Core\Service;

use Brave\Core\Factory\EsiApiFactory;
use Brave\Core\Service\EsiApi;
use GuzzleHttp\Psr7\Response;
use Swagger\Client\Eve\Model\GetAlliancesAllianceIdOk;
use Swagger\Client\Eve\Model\GetCharactersCharacterIdOk;
use Swagger\Client\Eve\Model\GetCorporationsCorporationIdOk;
use Tests\Logger;
use Tests\Client;

class EsiApiTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var Logger
     */
    private $log;

    /**
     * @var Client
     */
    private $client;

    /**
     * @var EsiApi
     */
    private $esi;

    public function setUp()
    {
        $this->log = new Logger('Test');
        $this->client = new Client();
        $this->esi = new EsiApi($this->log, (new EsiApiFactory())->setClient($this->client));
    }

    public function testGetAllianceException500()
    {
        $this->client->setResponse(new Response(500));

        $result = $this->esi->getAlliance(456);

        $this->assertNull($result);
        $this->assertSame(500, $this->esi->getLastErrorCode());
        $this->assertStringStartsWith('[500] Error ', $this->esi->getLastErrorMessage());
        $this->assertStringStartsWith('[500] Error ', $this->log->getHandler()->getRecords()[0]['message']);
    }

    public function testGetAlliance()
    {
        $this->client->setResponse(new Response(200, [], '{
            "name": "The Alliance.",
            "ticker": "-HAT-"
        }'));

        $result = $this->esi->getAlliance(456);

        $this->assertNull($this->esi->getLastErrorCode());
        $this->assertNull($this->esi->getLastErrorMessage());
        $this->assertInstanceOf(GetAlliancesAllianceIdOk::class, $result);
        $this->assertSame('The Alliance.', $result->getName());
        $this->assertSame('-HAT-', $result->getTicker());
    }

    public function testGetCorporation500()
    {
        $this->client->setResponse(new Response(500));

        $result = $this->esi->getCorporation(123);

        $this->assertNull($result);
        $this->assertSame(500, $this->esi->getLastErrorCode());
        $this->assertStringStartsWith('[500] Error ', $this->esi->getLastErrorMessage());
        $this->assertStringStartsWith('[500] Error ', $this->log->getHandler()->getRecords()[0]['message']);
    }

    public function testGetCorporation()
    {
        $this->client->setResponse(new Response(200, [], '{
            "name": "The Corp.",
            "ticker": "-HAT-",
            "alliance_id": "123"
        }'));

        $result = $this->esi->getCorporation(123);

        $this->assertNull($this->esi->getLastErrorCode());
        $this->assertNull($this->esi->getLastErrorMessage());
        $this->assertInstanceOf(GetCorporationsCorporationIdOk::class, $result);
        $this->assertSame('The Corp.', $result->getName());
        $this->assertSame('-HAT-', $result->getTicker());
    }

    public function testGetCharacter500()
    {
        $this->client->setResponse(new Response(500));

        $result = $this->esi->getCharacter(123);

        $this->assertNull($result);
        $this->assertSame(500, $this->esi->getLastErrorCode());
        $this->assertStringStartsWith('[500] Error ', $this->esi->getLastErrorMessage());
        $this->assertStringStartsWith('[500] Error ', $this->log->getHandler()->getRecords()[0]['message']);
    }

    public function testGetCharacter()
    {
        $this->client->setResponse(new Response(200, [], '{
            "name": "The Char."
        }'));

        $result = $this->esi->getCharacter(123);

        $this->assertNull($this->esi->getLastErrorCode());
        $this->assertNull($this->esi->getLastErrorMessage());
        $this->assertInstanceOf(GetCharactersCharacterIdOk::class, $result);
        $this->assertSame('The Char.', $result->getName());
    }

    public function testSendMail()
    {
        $this->client->setResponse(new Response(200, [], 373515628));

        $result = $this->esi->sendMail(123, 'access-token', 'subject', 'body', [456]);

        $this->assertSame(373515628, $result);
    }
}
