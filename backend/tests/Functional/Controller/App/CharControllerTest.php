<?php

declare(strict_types=1);

namespace Tests\Functional\Controller\App;

use Neucore\Controller\App\CharController;
use Neucore\Entity\Corporation;
use Neucore\Entity\Player;
use Neucore\Entity\RemovedCharacter;
use Neucore\Entity\Role;
use Tests\Functional\WebTestCase;
use Tests\Helper;

class CharControllerTest extends WebTestCase
{
    private Helper $helper;

    private int $app0Id;

    private int $appId;

    protected function setUp(): void
    {
        $this->helper = new Helper();
    }

    public function testMainV1403()
    {
        $response1 = $this->runApp('GET', '/api/app/v1/main/123');
        $this->assertEquals(403, $response1->getStatusCode());

        $this->setUpDb();
        $headers = ['Authorization' => 'Bearer '.base64_encode($this->app0Id.':s0')];
        $response2 = $this->runApp('GET', '/api/app/v1/main/123', null, $headers);
        $this->assertEquals(403, $response2->getStatusCode());
    }

    public function testMainV1404()
    {
        $this->setUpDb();

        $headers = ['Authorization' => 'Bearer '.base64_encode($this->appId.':s1')];
        $response = $this->runApp('GET', '/api/app/v1/main/123', null, $headers);

        $this->assertEquals(404, $response->getStatusCode());
        $this->assertEquals('Not Found', $response->getReasonPhrase());
    }

    public function testMainV2403()
    {
        $response1 = $this->runApp('GET', '/api/app/v2/main/123');
        $this->assertEquals(403, $response1->getStatusCode());

        $this->setUpDb();
        $headers = ['Authorization' => 'Bearer '.base64_encode($this->app0Id.':s0')];
        $response2 = $this->runApp('GET', '/api/app/v2/main/123', null, $headers);
        $this->assertEquals(403, $response2->getStatusCode());
    }

    public function testMainV2404()
    {
        $this->setUpDb();

        $headers = ['Authorization' => 'Bearer '.base64_encode($this->appId.':s1')];
        $response = $this->runApp('GET', '/api/app/v2/main/123', null, $headers);

        $this->assertEquals(404, $response->getStatusCode());
        $this->assertEquals('Character not found.', $response->getReasonPhrase());
    }

    public function testMainV1204()
    {
        $this->setUpDb();
        $char = $this->helper->addCharacterMain('C1', 123, [Role::USER]);
        $char->setMain(false);
        $this->helper->getObjectManager()->flush();

        $headers = ['Authorization' => 'Bearer '.base64_encode($this->appId.':s1')];
        $response = $this->runApp('GET', '/api/app/v1/main/123', null, $headers);

        $this->assertEquals(204, $response->getStatusCode());
    }

    public function testMainV1200()
    {
        $this->setUpDb();
        $char = $this->helper->addCharacterMain('C1', 123, [Role::USER]);
        $this->helper->addCharacterToPlayer('C2', 456, $char->getPlayer());

        $headers = ['Authorization' => 'Bearer '.base64_encode($this->appId.':s1')];
        $response1 = $this->runApp('GET', '/api/app/v1/main/123', null, $headers);
        $response2 = $this->runApp('GET', '/api/app/v1/main/456', null, $headers);

        $this->assertEquals(200, $response1->getStatusCode());
        $this->assertEquals(200, $response2->getStatusCode());

        $body1 = $this->parseJsonBody($response1);
        $body2 = $this->parseJsonBody($response2);

        $this->assertSame($body1, $body2);
        $this->assertSame(
            [
                'id' => 123,
                'name' => 'C1',
                'main' => true,
                'created' => null,
                'lastUpdate' => null,
                'validToken' => null,
                'validTokenTime' => null,
                'tokenLastChecked' => null,
                'corporation' => null
            ],
            $body1
        );
    }

    public function testPlayerV1403()
    {
        $this->setUpDb();

        $response1 = $this->runApp('GET', '/api/app/v1/player/123');
        $this->assertEquals(403, $response1->getStatusCode());

        $headers = ['Authorization' => 'Bearer '.base64_encode($this->app0Id.':s0')]; // does not have role app-chars
        $response2 = $this->runApp('GET', '/api/app/v1/player/123', null, $headers);
        $this->assertEquals(403, $response2->getStatusCode());
    }

    public function testPlayerV1404()
    {
        $this->setUpDb();

        $headers = ['Authorization' => 'Bearer '.base64_encode($this->appId.':s1')];
        $response = $this->runApp('GET', '/api/app/v1/player/123', null, $headers);

        $this->assertEquals(404, $response->getStatusCode());
        $this->assertEquals('Character not found.', $response->getReasonPhrase());
    }

    public function testPlayerV1200()
    {
        $this->setUpDb();
        $playerId = $this->helper->addCharacterMain('C1', 123, [Role::USER])->getPlayer()->getId();

        $headers = ['Authorization' => 'Bearer '.base64_encode($this->appId.':s1')];
        $response = $this->runApp('GET', '/api/app/v1/player/123', null, $headers);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertSame(
            ['id' => $playerId,'name' => 'C1'],
            $this->parseJsonBody($response)
        );
    }

    public function testCharactersV1403()
    {
        $response = $this->runApp('GET', '/api/app/v1/characters/123');
        $this->assertEquals(403, $response->getStatusCode());

        $this->setUpDb();
        $headers = ['Authorization' => 'Bearer '.base64_encode($this->app0Id.':s0')];
        $response = $this->runApp('GET', '/api/app/v1/characters/123', null, $headers);
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testCharactersV1404()
    {
        $this->setUpDb();

        $headers = ['Authorization' => 'Bearer '.base64_encode($this->appId.':s1')];
        $response = $this->runApp('GET', '/api/app/v1/characters/123', null, $headers);

        $this->assertEquals(404, $response->getStatusCode());
        $this->assertEquals('Character not found.', $response->getReasonPhrase());
    }

    public function testCharactersV1200()
    {
        $this->setUpDb();
        $char = $this->helper->addCharacterMain('C1', 123, [Role::USER]);
        $this->helper->addCharacterToPlayer('C2', 456, $char->getPlayer());

        $headers = ['Authorization' => 'Bearer '.base64_encode($this->appId.':s1')];
        $response1 = $this->runApp('GET', '/api/app/v1/characters/123', null, $headers);
        $response2 = $this->runApp('GET', '/api/app/v1/characters/456', null, $headers);

        $this->assertEquals(200, $response1->getStatusCode());
        $this->assertEquals(200, $response2->getStatusCode());

        $body1 = $this->parseJsonBody($response1);
        $body2 = $this->parseJsonBody($response2);

        $this->assertSame($body1, $body2);
        $this->assertSame(
            [[
                'id' => 123,
                'name' => 'C1',
                'main' => true,
                'created' => null,
                'lastUpdate' => null,
                'validToken' => null,
                'validTokenTime' => null,
                'tokenLastChecked' => null,
                'corporation' => null
            ],[
                'id' => 456,
                'name' => 'C2',
                'main' => false,
                'created' => null,
                'lastUpdate' => null,
                'validToken' => null,
                'validTokenTime' => null,
                'tokenLastChecked' => null,
                'corporation' => null
            ]],
            $body1
        );
    }

    public function testCharactersBulk403()
    {
        $this->setUpDb();

        $headers = ['Authorization' => 'Bearer '.base64_encode($this->app0Id.':s0')];
        $response = $this->runApp('POST', '/api/app/v1/characters', [], $headers);

        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testCharactersBulk400()
    {
        $this->setUpDb();

        $response = $this->runApp('POST', '/api/app/v1/characters', '123', [
            'Authorization' => 'Bearer '.base64_encode($this->appId.':s1'),
            'Content-Type' => 'application/json',
        ]);

        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testCharactersBulk200()
    {
        $this->setUpDb();
        $this->helper->addCharacterMain('C1', 987, [Role::USER]);
        $char = $this->helper->addCharacterMain('C1', 123, [Role::USER]);
        $this->helper->addCharacterToPlayer('C2', 456, $char->getPlayer());

        $response = $this->runApp('POST', '/api/app/v1/characters', [123, 456, 987], [
            'Authorization' => 'Bearer '.base64_encode($this->appId.':s1'),
            'Content-Type' => 'application/json',
        ]);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertSame([[987], [123, 456]], $this->parseJsonBody($response));
    }

    public function testCharacterListV1403()
    {
        $this->setUpDb();

        $headers = [
            'Authorization' => 'Bearer '.base64_encode($this->app0Id.':s0'),
            'Content-Type' => 'application/json',
        ];
        $response = $this->runApp('POST', '/api/app/v1/character-list', [123, 789, 012], $headers);

        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testCharacterListV1400()
    {
        $this->setUpDb();
        $char1 = $this->helper->addCharacterMain('C1', 123);
        $this->helper->addCharacterToPlayer('C2', 456, $char1->getPlayer());
        $this->helper->addCharacterMain('C3', 789);

        $headers = [
            'Authorization' => 'Bearer '.base64_encode($this->appId.':s1'),
            'Content-Type' => 'application/json',
        ];
        $response = $this->runApp('POST', '/api/app/v1/character-list', "", $headers);

        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testCharacterListV1200()
    {
        $this->setUpDb();
        $char1 = $this->helper->addCharacterMain('C1', 123);
        $this->helper->addCharacterToPlayer('C2', 456, $char1->getPlayer());
        $this->helper->addCharacterMain('C3', 789);

        $headers = [
            'Authorization' => 'Bearer '.base64_encode($this->appId.':s1'),
            'Content-Type' => 'application/json',
        ];
        $response = $this->runApp('POST', '/api/app/v1/character-list', [123, 789, 012], $headers);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertSame([
            [
                'id' => 123,
                'name' => 'C1',
                'main' => true,
                'created' => null,
                'lastUpdate' => null,
                'validToken' => null,
                'validTokenTime' => null,
                'tokenLastChecked' => null,
            ],[
                'id' => 789,
                'name' => 'C3',
                'main' => true,
                'created' => null,
                'lastUpdate' => null,
                'validToken' => null,
                'validTokenTime' => null,
                'tokenLastChecked' => null,
            ]
        ], $this->parseJsonBody($response));
    }

    public function testPlayerCharactersV1403()
    {
        $response = $this->runApp('GET', '/api/app/v1/player-chars/5000');
        $this->assertEquals(403, $response->getStatusCode());

        $this->setUpDb();
        $headers = ['Authorization' => 'Bearer '.base64_encode($this->app0Id.':s0')];
        $response = $this->runApp('GET', '/api/app/v1/player-chars/5000', null, $headers);
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testPlayerCharactersV1404()
    {
        $this->setUpDb();

        $headers = ['Authorization' => 'Bearer '.base64_encode($this->appId.':s1')];
        $response = $this->runApp('GET', '/api/app/v1/player-chars/5000', null, $headers);

        $this->assertEquals(404, $response->getStatusCode());
        $this->assertEquals('Player not found.', $response->getReasonPhrase());
    }

    public function testPlayerCharactersV1200()
    {
        $this->setUpDb();
        $player = $this->helper->addCharacterMain('C1', 123, [Role::USER])->getPlayer();
        $this->helper->addCharacterToPlayer('C2', 456, $player);

        $headers = ['Authorization' => 'Bearer '.base64_encode($this->appId.':s1')];
        $response = $this->runApp('GET', '/api/app/v1/player-chars/'.$player->getId(), null, $headers);

        $this->assertEquals(200, $response->getStatusCode());
        $body = $this->parseJsonBody($response);
        $this->assertSame(
            [[
                'id' => 123,
                'name' => 'C1',
                'main' => true,
                'created' => null,
                'lastUpdate' => null,
                'validToken' => null,
                'validTokenTime' => null,
                'tokenLastChecked' => null,
                'corporation' => null
            ],[
                'id' => 456,
                'name' => 'C2',
                'main' => false,
                'created' => null,
                'lastUpdate' => null,
                'validToken' => null,
                'validTokenTime' => null,
                'tokenLastChecked' => null,
                'corporation' => null
            ]],
            $body
        );
    }

    public function testPlayerWithCharactersV1_403()
    {
        $response = $this->runApp('GET', '/api/app/v1/player-with-characters/123');
        $this->assertEquals(403, $response->getStatusCode());

        $this->setUpDb();
        $headers = ['Authorization' => 'Bearer '.base64_encode($this->app0Id.':s0')];
        $response = $this->runApp('GET', '/api/app/v1/player-with-characters/123', null, $headers);
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testPlayerWithCharactersV1_404()
    {
        $this->setUpDb();

        $headers = ['Authorization' => 'Bearer '.base64_encode($this->appId.':s1')];
        $response = $this->runApp('GET', '/api/app/v1/player-with-characters/123', null, $headers);

        $this->assertEquals(404, $response->getStatusCode());
        $this->assertEquals(CharController::ERROR_CHARACTER_NOT_FOUND, $response->getReasonPhrase());
    }

    public function testPlayerWithCharactersV1_200()
    {
        $this->setUpDb();
        $player = $this->helper->addCharacterMain('C1', 123, [Role::USER])->getPlayer();
        $this->helper->addCharacterToPlayer('C2', 456, $player);

        $headers = ['Authorization' => 'Bearer '.base64_encode($this->appId.':s1')];
        $response = $this->runApp('GET', '/api/app/v1/player-with-characters/123', null, $headers);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertSame([
            'id' => $player->getId(),
            'name' => 'C1',
            'characters' => [[
                'id' => 123,
                'name' => 'C1',
                'main' => true,
                'created' => null,
                'lastUpdate' => null,
                'validToken' => null,
                'validTokenTime' => null,
                'tokenLastChecked' => null,
                'corporation' => null
            ],[
                'id' => 456,
                'name' => 'C2',
                'main' => false,
                'created' => null,
                'lastUpdate' => null,
                'validToken' => null,
                'validTokenTime' => null,
                'tokenLastChecked' => null,
                'corporation' => null
            ]]
        ], $this->parseJsonBody($response));
    }

    public function testRemovedCharactersV1403()
    {
        $response = $this->runApp('GET', '/api/app/v1/removed-characters/123');
        $this->assertEquals(403, $response->getStatusCode());

        $this->setUpDb();
        $headers = ['Authorization' => 'Bearer '.base64_encode($this->app0Id.':s0')];
        $response = $this->runApp('GET', '/api/app/v1/removed-characters/123', null, $headers);
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testRemovedCharactersV1404()
    {
        $this->setUpDb();

        $headers = ['Authorization' => 'Bearer '.base64_encode($this->appId.':s1')];
        $response = $this->runApp('GET', '/api/app/v1/removed-characters/123', null, $headers);

        $this->assertEquals(404, $response->getStatusCode());
        $this->assertEquals('Character not found.', $response->getReasonPhrase());
    }

    public function testRemovedCharactersV1200()
    {
        $this->setUpDb();
        $player1 = $this->helper->addCharacterMain('p1', 123, [Role::USER])->getPlayer();
        $player2 = (new Player())->setName('p2');
        $removedChar1 = (new RemovedCharacter())->setCharacterId(100)->setCharacterName('c1')
            ->setRemovedDate(new \DateTime('2019-04-20 20:41:46'))
            ->setReason(RemovedCharacter::REASON_DELETED_MANUALLY)
            ->setDeletedBy($player1);
        $removedChar2 = (new RemovedCharacter())->setCharacterId(101)->setCharacterName('c2')
            ->setRemovedDate(new \DateTime('2019-04-20 20:41:47'))
            ->setReason(RemovedCharacter::REASON_MOVED)->setNewPlayer($player2);
        $removedChar1->setPlayer($player1);
        $removedChar2->setPlayer($player1);
        $this->helper->getObjectManager()->persist($player2);
        $this->helper->getObjectManager()->persist($removedChar1);
        $this->helper->getObjectManager()->persist($removedChar2);
        $this->helper->getObjectManager()->flush();
        $this->helper->getObjectManager()->clear();

        $headers = ['Authorization' => 'Bearer '.base64_encode($this->appId.':s1')];
        $response = $this->runApp('GET', '/api/app/v1/removed-characters/123', null, $headers);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertSame(
            [[
                'player' => ['id' => $player1->getId(), 'name' => 'p1'],
                'characterId' => 100,
                'characterName' => 'c1',
                'removedDate' => '2019-04-20T20:41:46Z',
                'reason' => RemovedCharacter::REASON_DELETED_MANUALLY,
                'deletedBy' => ['id' => $player1->getId(), 'name' => 'p1'],
                'newPlayerId' => null,
                'newPlayerName' => null
            ],[
                'player' => ['id' => $player1->getId(), 'name' => 'p1'],
                'characterId' => 101,
                'characterName' => 'c2',
                'removedDate' => '2019-04-20T20:41:47Z',
                'reason' => RemovedCharacter::REASON_MOVED,
                'deletedBy' => null,
                'newPlayerId' => $player2->getId(),
                'newPlayerName' => 'p2'
            ]],
            $this->parseJsonBody($response)
        );
    }

    public function testIncomingCharactersV1403()
    {
        $response = $this->runApp('GET', '/api/app/v1/incoming-characters/123');
        $this->assertEquals(403, $response->getStatusCode());

        $this->setUpDb();
        $headers = ['Authorization' => 'Bearer '.base64_encode($this->app0Id.':s0')];
        $response = $this->runApp('GET', '/api/app/v1/incoming-characters/123', null, $headers);
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testIncomingCharactersV1404()
    {
        $this->setUpDb();

        $headers = ['Authorization' => 'Bearer '.base64_encode($this->appId.':s1')];
        $response = $this->runApp('GET', '/api/app/v1/incoming-characters/123', null, $headers);

        $this->assertEquals(404, $response->getStatusCode());
        $this->assertEquals('Character not found.', $response->getReasonPhrase());
    }

    public function testIncomingCharactersV1200()
    {
        $this->setUpDb();
        $player1 = $this->helper->addCharacterMain('p1', 123, [Role::USER])->getPlayer();
        $player2 = $this->helper->addCharacterMain('p2', 456, [Role::USER])->getPlayer();
        $movedChar = (new RemovedCharacter())->setCharacterId(101)->setCharacterName('c1')
            ->setRemovedDate(new \DateTime('2019-04-20 20:41:47'))
            ->setReason(RemovedCharacter::REASON_MOVED)
            ->setNewPlayer($player2)->setPlayer($player1);
        $this->helper->getObjectManager()->persist($player2);
        $this->helper->getObjectManager()->persist($movedChar);
        $this->helper->getObjectManager()->flush();
        $this->helper->getObjectManager()->clear();

        $headers = ['Authorization' => 'Bearer '.base64_encode($this->appId.':s1')];
        $response = $this->runApp('GET', '/api/app/v1/incoming-characters/456', null, $headers);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertSame([[
            'player' => ['id' => $player1->getId(), 'name' => 'p1'],
            'characterId' => 101,
            'characterName' => 'c1',
            'removedDate' => '2019-04-20T20:41:47Z',
            'reason' => RemovedCharacter::REASON_MOVED,
            'deletedBy' => null,
            'newPlayerId' => $player2->getId(),
            'newPlayerName' => 'p2'
        ]], $this->parseJsonBody($response));
    }

    public function testCorporationPlayersV1403()
    {
        $response1 = $this->runApp('GET', '/api/app/v1/corp-players/1000');
        $this->assertSame(403, $response1->getStatusCode());

        $this->setUpDb();
        $headers = ['Authorization' => 'Bearer '.base64_encode($this->app0Id.':s0')];
        $response2 = $this->runApp('GET', '/api/app/v1/corp-players/1000', null, $headers);
        $this->assertSame(403, $response2->getStatusCode());
    }

    public function testCorporationPlayersV1200InvalidCorp()
    {
        $this->setUpDb();

        $headers = ['Authorization' => 'Bearer '.base64_encode($this->appId.':s1')];
        $response = $this->runApp('GET', '/api/app/v1/corp-players/1000', null, $headers);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame([], $this->parseJsonBody($response));
    }

    public function testCorporationPlayersV1200()
    {
        $this->setUpDb();
        $char = $this->helper->addCharacterMain('C1', 123, [Role::USER]);
        $corp = (new Corporation())->setId(1000)->setName('Corp one');
        $this->helper->getObjectManager()->persist($corp);
        $char->setCorporation($corp);
        $this->helper->addCharacterToPlayer('C2', 456, $char->getPlayer());

        $headers = ['Authorization' => 'Bearer '.base64_encode($this->appId.':s1')];
        $response = $this->runApp('GET', '/api/app/v1/corp-players/1000', null, $headers);

        $this->assertSame(200, $response->getStatusCode());

        $body = $this->parseJsonBody($response);

        $this->assertSame(
            [['id' => $char->getPlayer()->getId(), 'name' => 'C1']],
            $body
        );
    }

    public function testCorporationCharactersV1403()
    {
        $this->setUpDb();

        $headers = [
            'Authorization' => 'Bearer '.base64_encode($this->app0Id.':s0'),
            'Content-Type' => 'application/json',
        ];
        $response = $this->runApp('GET', '/api/app/v1/corp-characters/1000', null, $headers);

        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testCorporationCharactersV1200()
    {
        $this->setUpDb();
        $corp1 = (new Corporation())->setId(1000)->setName('Corp one');
        $corp2 = (new Corporation())->setId(2000)->setName('Corp two');
        $this->helper->getObjectManager()->persist($corp1);
        $this->helper->getObjectManager()->persist($corp2);
        $char1 = $this->helper->addCharacterMain('C1', 123)->setCorporation($corp1);
        $this->helper->addCharacterToPlayer('C2', 456, $char1->getPlayer())->setCorporation($corp2);
        $this->helper->addCharacterMain('C3', 789)->setCorporation($corp1);
        $this->helper->getObjectManager()->flush();
        $this->helper->getObjectManager()->clear();

        $headers = ['Authorization' => 'Bearer '.base64_encode($this->appId.':s1')];
        $response1 = $this->runApp('GET', '/api/app/v1/corp-characters/1000', null, $headers);
        $response2 = $this->runApp('GET', '/api/app/v1/corp-characters/3000', null, $headers);

        $this->assertSame(200, $response1->getStatusCode());
        $this->assertSame(200, $response2->getStatusCode());
        $this->assertSame([
            [
                'id' => 123,
                'name' => 'C1',
                'main' => true,
                'created' => null,
                'lastUpdate' => null,
                'validToken' => null,
                'validTokenTime' => null,
                'tokenLastChecked' => null,
            ],[
                'id' => 789,
                'name' => 'C3',
                'main' => true,
                'created' => null,
                'lastUpdate' => null,
                'validToken' => null,
                'validTokenTime' => null,
                'tokenLastChecked' => null,
            ]
        ], $this->parseJsonBody($response1));
        $this->assertSame([], $this->parseJsonBody($response2));
    }

    private function setUpDb(): void
    {
        $this->helper->emptyDb();
        $this->app0Id = $this->helper->addApp('A0', 's0', [Role::APP])->getId();
        $this->appId = $this->helper->addApp('A1', 's1', [Role::APP, Role::APP_CHARS])->getId();
    }
}
