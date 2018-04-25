<?php declare(strict_types=1);

namespace Tests\Functional\Core\Api\User;

use Brave\Core\Entity\Alliance;
use Brave\Core\Entity\Corporation;
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
            ['id' => 96061222, 'name' => 'User', 'main' => true, 'corporation' => null],
            $this->parseJsonBody($response)
        );
    }

    public function testPlayer403()
    {
        $response = $this->runApp('GET', '/api/user/character/player');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testPlayer200()
    {
        $this->helper->emptyDb();
        $groups = $this->helper->addGroups(['group1', 'another-group']);
        $char = $this->helper->addCharacterMain(
            'TUser', 123456, [Roles::USER, Roles::USER_ADMIN], ['group1', 'another-group']);
        $alli = (new Alliance())->setId(123)->setName('alli1')->setTicker('ATT');
        $corp = (new Corporation())->setId(456)->setName('corp1')->setTicker('MT')->setAlliance($alli);
        $char->setCorporation($corp);
        $this->helper->getEm()->persist($alli);
        $this->helper->getEm()->persist($corp);
        $this->helper->getEm()->flush();
        $this->loginUser(123456);

        $response = $this->runApp('GET', '/api/user/character/player');
        $this->assertEquals(200, $response->getStatusCode());

        $this->assertSame([
            'id' => $char->getPlayer()->getId(),
            'name' => 'TUser',
            'roles' => [Roles::USER, Roles::USER_ADMIN],
            'characters' => [
                ['id' => 123456, 'name' => 'TUser', 'main' => true, 'corporation' => [
                    'id' => 456, 'name' => 'corp1', 'ticker' => 'MT', 'alliance' => [
                        'id' => 123, 'name' => 'alli1', 'ticker' => 'ATT'
                    ]
                ]],
            ],
            'applications' => [],
            'groups' => [
                ['id' => $groups[1]->getId(), 'name' => 'another-group', 'public' => false],
                ['id' => $groups[0]->getId(), 'name' => 'group1', 'public' => false]
            ],
            'managerGroups' => [],
            'managerApps' => [],
        ], $this->parseJsonBody($response));
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
        $this->assertSame(
            ['id' => 96061222, 'name' => 'Char 96061222', 'main' => true, 'corporation' => [
                'id' => 234, 'name' => 'The Corp.', 'ticker' => '-TTT-', 'alliance' => null
            ]],
            $this->parseJsonBody($response)
        );
    }

    private function setupDb()
    {
        $this->helper->emptyDb();
        $this->helper->addCharacterMain('User', 96061222, [Roles::USER], ['group1']);
    }
}
