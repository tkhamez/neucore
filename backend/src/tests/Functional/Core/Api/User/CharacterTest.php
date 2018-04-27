<?php declare(strict_types=1);

namespace Tests\Functional\Core\Api\User;

use Brave\Core\Roles;
use Swagger\Client\Eve\Api\CharacterApi;
use Swagger\Client\Eve\Api\CorporationApi;
use Swagger\Client\Eve\Model\GetCharactersCharacterIdOk;
use Swagger\Client\Eve\Model\GetCorporationsCorporationIdOk;
use Tests\Functional\WebTestCase;
use Tests\Helper;

class CharacterTest extends WebTestCase
{
    private $helper;

    public function setUp()
    {
        $_SESSION = null;
        $this->helper = new Helper();
    }

    public function testShow403()
    {
        $response = $this->runApp('GET', '/api/user/character/show');
        $this->assertSame(403, $response->getStatusCode());
    }

    public function testShow200()
    {
        $this->setupDb();
        $this->loginUser(96061222);

        $response = $this->runApp('GET', '/api/user/character/show');
        $this->assertSame(200, $response->getStatusCode());

        $this->assertSame(
            ['id' => 96061222, 'name' => 'User', 'main' => true, 'lastUpdate' => null, 'corporation' => null],
            $this->parseJsonBody($response)
        );
    }

    public function testUpdate403()
    {
        $response = $this->runApp('PUT', '/api/user/character/update/96061222');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testUpdate404()
    {
        $this->setupDb();
        $this->loginUser(96061222);

        $response = $this->runApp('PUT', '/api/user/character/update/9');
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testUpdate503()
    {
        $this->setupDb();
        $this->loginUser(96061222);

        $charApi = $this->createMock(CharacterApi::class);
        $response = $this->runApp('PUT', '/api/user/character/update/96061222', [], [], [
            CharacterApi::class => $charApi
        ]);

        $this->assertEquals(503, $response->getStatusCode());
    }

    public function testUpdate200()
    {
        $this->setupDb();
        $this->loginUser(96061222);

        $charApi = $this->createMock(CharacterApi::class);
        $charApi->method('getCharactersCharacterId')->willReturn(new GetCharactersCharacterIdOk([
            'name' => 'Char 96061222', 'corporation_id' => 234
        ]));
        $corpApi = $this->createMock(CorporationApi::class);
        $corpApi->method('getCorporationsCorporationId')->willReturn(new GetCorporationsCorporationIdOk([
            'name' => 'The Corp.', 'ticker' => '-TTT-', 'alliance_id' => null
        ]));

        $response = $this->runApp('PUT', '/api/user/character/update/96061222', [], [], [
            CharacterApi::class => $charApi,
            CorporationApi::class => $corpApi,
        ]);

        $this->assertEquals(200, $response->getStatusCode());

        $expected = ['id' => 96061222, 'name' => 'Char 96061222', 'main' => true, 'corporation' => [
            'id' => 234, 'name' => 'The Corp.', 'ticker' => '-TTT-', 'alliance' => null
        ]];
        $actual = $this->parseJsonBody($response);

        $this->assertRegExp('/^[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}Z$/', $actual['lastUpdate']);
        unset($actual['lastUpdate']);

        $this->assertSame($expected, $actual);
    }

    private function setupDb()
    {
        $this->helper->emptyDb();
        $this->helper->addCharacterMain('User', 96061222, [Roles::USER], ['group1']);
    }
}
