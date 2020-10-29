<?php

declare(strict_types=1);

namespace Tests\Functional\Controller\User;

use Doctrine\Persistence\ObjectManager;
use Neucore\Entity\Alliance;
use Neucore\Entity\Corporation;
use Neucore\Entity\Group;
use Neucore\Entity\Player;
use Neucore\Entity\Role;
use Neucore\Entity\Watchlist;
use Neucore\Factory\RepositoryFactory;
use Tests\Functional\WebTestCase;
use Tests\Helper;

class WatchlistControllerTest extends WebTestCase
{
    /**
     * @var Helper
     */
    private $helper;

    /**
     * @var ObjectManager 
     */
    private $em;

    /**
     * @var RepositoryFactory
     */
    private $repositoryFactory;

    /**
     * @var Player
     */
    private $player1;

    /**
     * @var Player
     */
    private $player2;

    /**
     * @var Player
     */
    private $player3;

    /**
     * @var Corporation
     */
    private $corporation1;

    /**
     * @var Corporation
     */
    private $corporation2;

    /**
     * @var Corporation
     */
    private $corporation3;

    /**
     * @var Alliance
     */
    private $alliance1;

    /**
     * @var Alliance
     */
    private $alliance2;

    /**
     * @var Group
     */
    private $group1;

    /**
     * @var Group
     */
    private $group2;

    /**
     * @var Group
     */
    private $group3;
    /**
     * @var int
     */
    private $wl1;

    /**
     * @var int
     */
    private $wl2;

    protected function setUp(): void
    {
        $_SESSION = null;
        $this->helper = new Helper();
        $this->em = $this->helper->getObjectManager();
        $this->repositoryFactory = new RepositoryFactory($this->em);
    }

    public function testCreate403()
    {
        $this->setupDb();
        $this->loginUser(8); # role watchlist-manager

        $response = $this->runApp('POST', '/api/user/watchlist/create', ['name' => "new wl"], [
            'Content-Type' => 'application/x-www-form-urlencoded'
        ]);

        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testCreate400()
    {
        $this->setupDb();
        $this->loginUser(10); # role watchlist-admin

        $response = $this->runApp('POST', '/api/user/watchlist/create', ['name' => "\n "], [
            'Content-Type' => 'application/x-www-form-urlencoded'
        ]);

        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testCreate201()
    {
        $this->setupDb();
        $this->loginUser(10); # role watchlist-admin

        $response = $this->runApp('POST', '/api/user/watchlist/create', ['name' => "list\n\n one"], [
            'Content-Type' => 'application/x-www-form-urlencoded'
        ]);

        $this->assertEquals(201, $response->getStatusCode());
        $result = $this->parseJsonBody($response);
        $this->assertGreaterThanOrEqual(1, $result['id']);
        $this->assertSame('list one', $result['name']);

        $this->assertNotNull($this->repositoryFactory->getWatchlistRepository()->find($result['id']));
    }

    public function testRename404()
    {
        $this->setupDb();
        $this->loginUser(10); # role watchlist-admin

        $id = $this->wl2 + 10;
        $response = $this->runApp('PUT', "/api/user/watchlist/$id/rename", ['name' => "list\n\n two"], [
            'Content-Type' => 'application/x-www-form-urlencoded'
        ]);

        $this->assertEquals(404, $response->getStatusCode());
        $this->assertEquals('Watchlist not found.', $response->getReasonPhrase());
    }

    public function testRename403()
    {
        $this->setupDb();
        $this->loginUser(8); # role watchlist-manager

        $response = $this->runApp('PUT', "/api/user/watchlist/{$this->wl2}/rename", ['name' => "new name"], [
            'Content-Type' => 'application/x-www-form-urlencoded'
        ]);

        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testRename400()
    {
        $this->setupDb();
        $this->loginUser(10); # role watchlist-admin

        $response = $this->runApp('PUT', "/api/user/watchlist/{$this->wl2}/rename", ['name' => "\n "], [
            'Content-Type' => 'application/x-www-form-urlencoded'
        ]);

        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testRename200()
    {
        $this->setupDb();
        $this->loginUser(10); # role watchlist-admin

        $response = $this->runApp('PUT', "/api/user/watchlist/{$this->wl2}/rename", ['name' => "list\n\n two"], [
            'Content-Type' => 'application/x-www-form-urlencoded'
        ]);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertSame(
            ['id' => $this->wl2, 'name' => 'list two', 'lockWatchlistSettings' => false],
            $this->parseJsonBody($response)
        );

        $this->assertSame('list two', $this->repositoryFactory->getWatchlistRepository()->find($this->wl2)->getName());
    }

    public function testDelete404()
    {
        $this->setupDb();
        $this->loginUser(10); # role watchlist-admin

        $id = $this->wl2 + 10;
        $response = $this->runApp('DELETE', "/api/user/watchlist/$id/delete");
        $this->assertEquals(404, $response->getStatusCode());
        $this->assertEquals('Watchlist not found.', $response->getReasonPhrase());
    }

    public function testDelete403()
    {
        $this->setupDb();
        $this->loginUser(8); # role watchlist-manager

        $response = $this->runApp('DELETE', "/api/user/watchlist/{$this->wl2}/delete");
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testDelete204()
    {
        $this->setupDb();
        $this->loginUser(10); # role watchlist-admin

        $response = $this->runApp('DELETE', "/api/user/watchlist/{$this->wl2}/delete");
        $this->assertEquals(204, $response->getStatusCode());

        $this->assertNull($this->repositoryFactory->getWatchlistRepository()->find($this->wl2));
    }

    public function testLockWatchlistSettings404()
    {
        $this->setupDb();
        $this->loginUser(10); # role watchlist-admin

        $id = $this->wl1 + 10;
        $response = $this->runApp('PUT', "/api/user/watchlist/{$id}/lock-watchlist-settings/1");
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testLockWatchlistSettings403()
    {
        $this->setupDb();
        $this->loginUser(8); # role watchlist-manager

        $response = $this->runApp('PUT', "/api/user/watchlist/{$this->wl1}/lock-watchlist-settings/1");
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testLockWatchlistSettings200()
    {
        $this->setupDb();
        $this->loginUser(10); # role watchlist-admin

        $response = $this->runApp('PUT', "/api/user/watchlist/{$this->wl1}/lock-watchlist-settings/1");

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertSame(
            ['id' => $this->wl1, 'name' => 'ws1', 'lockWatchlistSettings' => true],
            $this->parseJsonBody($response)
        );

        $this->assertTrue($this->repositoryFactory->getWatchlistRepository()->find($this->wl1)->getLockWatchlistSettings());
    }

    public function testListAll403()
    {
        $response1 = $this->runApp('GET', '/api/user/watchlist/listAll');
        $this->assertEquals(403, $response1->getStatusCode());

        $this->setupDb();
        $this->loginUser(9); # role watchlist with group

        $response2 = $this->runApp('GET', '/api/user/watchlist/listAll');
        $this->assertEquals(403, $response2->getStatusCode());
    }

    public function testListAll200()
    {
        $this->setupDb();
        $this->loginUser(10); # role watchlist-admin

        $response = $this->runApp('GET', '/api/user/watchlist/listAll');
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertSame(
            [
                ['id' => $this->wl1, 'name' => 'ws1', 'lockWatchlistSettings' => false],
                ['id' => $this->wl2, 'name' => 'ws2', 'lockWatchlistSettings' => false]
            ],
            $this->parseJsonBody($response)
        );
    }

    public function testListAvailable403()
    {
        $response1 = $this->runApp('GET', '/api/user/watchlist/list-available');
        $this->assertEquals(403, $response1->getStatusCode());

        $this->setupDb();
        $this->loginUser(6); # not role watchlist

        $response2 = $this->runApp('GET', '/api/user/watchlist/list-available');
        $this->assertEquals(403, $response2->getStatusCode());
    }

    public function testListAvailable200()
    {
        $this->setupDb();
        $this->loginUser(9); # role watchlist with group

        $response = $this->runApp('GET', '/api/user/watchlist/list-available');
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertSame(
            [['id' => $this->wl1, 'name' => 'ws1', 'lockWatchlistSettings' => false]],
            $this->parseJsonBody($response)
        );
    }

    public function testListAvailableManage403()
    {
        $response1 = $this->runApp('GET', '/api/user/watchlist/list-available-manage');
        $this->assertEquals(403, $response1->getStatusCode());

        $this->setupDb();
        $this->loginUser(9); # not role watchlist-manager

        $response2 = $this->runApp('GET', '/api/user/watchlist/list-available-manage');
        $this->assertEquals(403, $response2->getStatusCode());
    }

    public function testListAvailableManage200()
    {
        $this->setupDb();
        $this->loginUser(8); # role watchlist-manager with group

        $response = $this->runApp('GET', '/api/user/watchlist/list-available-manage');
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertSame(
            [['id' => $this->wl1, 'name' => 'ws1', 'lockWatchlistSettings' => false]],
            $this->parseJsonBody($response)
        );
    }

    public function testPlayers403()
    {
        $this->setupDb();

        $response = $this->runApp('GET', "/api/user/watchlist/{$this->wl1}/players");
        $this->assertEquals(403, $response->getStatusCode());

        $this->loginUser(6); # not role watchlist
        $response = $this->runApp('GET', "/api/user/watchlist/{$this->wl1}/players");
        $this->assertEquals(403, $response->getStatusCode());

        $this->loginUser(7); # role watchlist, not group member
        $response = $this->runApp('GET', "/api/user/watchlist/{$this->wl1}/players");
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testPlayers200()
    {
        $this->setupDb();
        $this->loginUser(9); # role watchlist with group

        $response = $this->runApp('GET', "/api/user/watchlist/{$this->wl1}/players");
        $this->assertEquals(200, $response->getStatusCode());

        $this->assertSame([[
            'id' => $this->player2->getId(),
            'name' => 'Read'
        ]], $this->parseJsonBody($response));
    }

    public function testPlayersKicklist403()
    {
        $this->setupDb();

        $response = $this->runApp('GET', "/api/user/watchlist/{$this->wl1}/players-kicklist");
        $this->assertEquals(403, $response->getStatusCode());

        $this->loginUser(6); # not role watchlist
        $response = $this->runApp('GET', "/api/user/watchlist/{$this->wl1}/players-kicklist");
        $this->assertEquals(403, $response->getStatusCode());

        $this->loginUser(7); # role watchlist, not group member
        $response = $this->runApp('GET', "/api/user/watchlist/{$this->wl1}/players-kicklist");
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testPlayersKicklist200()
    {
        $this->setupDb();
        $this->loginUser(9); # role watchlist with group

        $response = $this->runApp('GET', "/api/user/watchlist/{$this->wl1}/players-kicklist");
        $this->assertEquals(200, $response->getStatusCode());

        $this->assertSame([[
            'id' => $this->player3->getId(),
            'name' => 'Watchlist'
        ]], $this->parseJsonBody($response));
    }

    public function testExemptionList403()
    {
        $this->setupDb();

        $response = $this->runApp('GET', "/api/user/watchlist/{$this->wl1}/exemption/list");
        $this->assertEquals(403, $response->getStatusCode());

        $this->loginUser(6); # not role watchlist
        $response = $this->runApp('GET', "/api/user/watchlist/{$this->wl1}/exemption/list");
        $this->assertEquals(403, $response->getStatusCode());

        $this->loginUser(7); # role watchlist, not group member
        $response = $this->runApp('GET', "/api/user/watchlist/{$this->wl1}/exemption/list");
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testExemptionList200()
    {
        $this->setupDb();
        $this->loginUser(9); # role watchlist with group

        $response = $this->runApp('GET', "/api/user/watchlist/{$this->wl1}/exemption/list");
        $this->assertEquals(200, $response->getStatusCode());

        $this->assertSame([[
            'id' => $this->player1->getId(),
            'name' => 'User'
        ]], $this->parseJsonBody($response));
    }

    public function testExemptionAdd403()
    {
        $this->setupDb();

        $response = $this->runApp('PUT', "/api/user/watchlist/{$this->wl1}/exemption/add/100");
        $this->assertEquals(403, $response->getStatusCode());

        $this->loginUser(7); # not role watchlist-manager
        $response = $this->runApp('PUT', "/api/user/watchlist/{$this->wl1}/exemption/add/100");
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testExemptionAdd403Manager()
    {
        $this->setupDb();
        $this->loginUser(8); # role watchlist-manager

        $id = $this->wl1 + 10;
        $response = $this->runApp('PUT', "/api/user/watchlist/$id/exemption/add/100");
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testExemptionAdd404()
    {
        $this->setupDb();
        $this->loginUser(8); # role watchlist-manager

        $response = $this->runApp('PUT', "/api/user/watchlist/{$this->wl1}/exemption/add/100");
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testExemptionAdd204()
    {
        $this->setupDb();
        $this->loginUser(8); # role watchlist-manager

        $response1 = $this->runApp('PUT', "/api/user/watchlist/{$this->wl1}/exemption/add/".$this->player2->getId());
        $response2 = $this->runApp('PUT', "/api/user/watchlist/{$this->wl1}/exemption/add/".$this->player2->getId());
        $this->assertEquals(204, $response1->getStatusCode());
        $this->assertEquals(204, $response2->getStatusCode());

        $this->em->clear();
        $result = $this->repositoryFactory->getWatchlistRepository()->find($this->wl1);
        $this->assertSame(2, count($result->getExemptions()));
    }

    public function testExemptionRemove403()
    {
        $this->setupDb();

        $response = $this->runApp('PUT', "/api/user/watchlist/{$this->wl1}/exemption/remove/100");
        $this->assertEquals(403, $response->getStatusCode());

        $this->loginUser(7); # not role watchlist-manager
        $response = $this->runApp('PUT', "/api/user/watchlist/{$this->wl1}/exemption/remove/100");
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testExemptionRemove403Manager()
    {
        $this->setupDb();
        $this->loginUser(8); # role watchlist-manager

        $id = $this->wl1 + 10;
        $response = $this->runApp('PUT', "/api/user/watchlist/$id/exemption/remove/100");
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testExemptionRemove404()
    {
        $this->setupDb();
        $this->loginUser(8); # role watchlist-manager

        $response = $this->runApp('PUT', "/api/user/watchlist/{$this->wl1}/exemption/remove/100");
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testExemptionRemove204()
    {
        $this->setupDb();
        $this->loginUser(8); # role watchlist-manager

        $response = $this->runApp('PUT', "/api/user/watchlist/{$this->wl1}/exemption/remove/".$this->player1->getId());
        $this->assertEquals(204, $response->getStatusCode());

        $this->em->clear();
        $result = $this->repositoryFactory->getWatchlistRepository()->find($this->wl1);
        $this->assertSame(0, count($result->getExemptions()));
    }

    public function testCorporationList403()
    {
        $this->setupDb();

        $response = $this->runApp('GET', "/api/user/watchlist/{$this->wl1}/corporation/list");
        $this->assertEquals(403, $response->getStatusCode());

        $this->loginUser(7); # role watchlist, not group member
        $response = $this->runApp('GET', "/api/user/watchlist/{$this->wl1}/corporation/list");
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testCorporationList200()
    {
        $this->setupDb();
        $this->loginUser(9); # role watchlist with group

        $response = $this->runApp('GET', "/api/user/watchlist/{$this->wl1}/corporation/list");
        $this->assertEquals(200, $response->getStatusCode());

        $this->assertSame([[
            'id' => $this->corporation1->getId(),
            'name' => null,
            'ticker' => null,
            'alliance' => null,
        ]], $this->parseJsonBody($response));
    }

    public function testCorporationList200Admin()
    {
        $this->setupDb();
        $this->loginUser(10); # role watchlist-admin

        $response = $this->runApp('GET', "/api/user/watchlist/{$this->wl1}/corporation/list");
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testCorporationAdd403()
    {
        $this->setupDb();

        $response = $this->runApp('PUT', "/api/user/watchlist/{$this->wl1}/corporation/add/100");
        $this->assertEquals(403, $response->getStatusCode());

        $this->loginUser(7); # not role watchlist-manager or watchlist-admin
        $response = $this->runApp('PUT', "/api/user/watchlist/{$this->wl1}/corporation/add/100");
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testCorporationAdd403Manager()
    {
        $this->setupDb();
        $this->loginUser(8); # role watchlist-manager

        $id = $this->wl1 + 10;
        $response = $this->runApp('PUT', "/api/user/watchlist/$id/corporation/add/100");
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testCorporationAdd403ManagerLocked()
    {
        $this->setupDb();
        $this->loginUser(8); # role watchlist-manager

        $this->repositoryFactory->getWatchlistRepository()->find($this->wl1)->setLockWatchlistSettings(true);
        $this->em->flush();

        $response1 = $this->runApp(
            'PUT', "/api/user/watchlist/{$this->wl1}/corporation/add/".$this->corporation2->getId()
        );
        $this->assertEquals(403, $response1->getStatusCode());
    }

    public function testCorporationAdd404()
    {
        $this->setupDb();
        $this->loginUser(8); # role watchlist-manager

        $response = $this->runApp('PUT', "/api/user/watchlist/{$this->wl1}/corporation/add/100");
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testCorporationAdd204()
    {
        $this->setupDb();
        $this->loginUser(8); # role watchlist-manager

        $response1 = $this->runApp(
            'PUT', "/api/user/watchlist/{$this->wl1}/corporation/add/".$this->corporation2->getId()
        );
        $response2 = $this->runApp(
            'PUT', "/api/user/watchlist/{$this->wl1}/corporation/add/".$this->corporation2->getId()
        );
        $this->assertEquals(204, $response1->getStatusCode());
        $this->assertEquals(204, $response2->getStatusCode());

        $this->em->clear();
        $result = $this->repositoryFactory->getWatchlistRepository()->find($this->wl1);
        $this->assertSame(2, count($result->getCorporations()));
    }

    public function testCorporationAdd204Admin()
    {
        $this->setupDb();
        $this->loginUser(10); # role watchlist-admin

        $response = $this->runApp(
            'PUT', "/api/user/watchlist/{$this->wl1}/corporation/add/".$this->corporation2->getId()
        );
        $this->assertEquals(204, $response->getStatusCode());
    }

    public function testCorporationRemove403()
    {
        $this->setupDb();

        $response = $this->runApp('PUT', "/api/user/watchlist/{$this->wl1}/corporation/remove/100");
        $this->assertEquals(403, $response->getStatusCode());

        $this->loginUser(7); # not role watchlist-manager
        $response = $this->runApp('PUT', "/api/user/watchlist/{$this->wl1}/corporation/remove/100");
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testCorporationRemove403Manager()
    {
        $this->setupDb();
        $this->loginUser(8); # role watchlist-manager

        $id = $this->wl1 + 10;
        $response = $this->runApp('PUT', "/api/user/watchlist/$id/corporation/remove/100");
        $this->assertEquals(403, $response->getStatusCode());
    }


    public function testCorporationRemove403ManagerLocked()
    {
        $this->setupDb();
        $this->loginUser(8); # role watchlist-manager

        $this->repositoryFactory->getWatchlistRepository()->find($this->wl1)->setLockWatchlistSettings(true);
        $this->em->flush();

        $response = $this->runApp(
            'PUT', "/api/user/watchlist/{$this->wl1}/corporation/remove/".$this->corporation1->getId()
        );
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testCorporationRemove404()
    {
        $this->setupDb();
        $this->loginUser(8); # role watchlist-manager

        $response = $this->runApp('PUT', "/api/user/watchlist/{$this->wl1}/corporation/remove/100");
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testCorporationRemove204()
    {
        $this->setupDb();
        $this->loginUser(8); # role watchlist-manager

        $response = $this->runApp(
            'PUT', "/api/user/watchlist/{$this->wl1}/corporation/remove/".$this->corporation1->getId()
        );
        $this->assertEquals(204, $response->getStatusCode());

        $this->em->clear();
        $result = $this->repositoryFactory->getWatchlistRepository()->find($this->wl1);
        $this->assertSame(0, count($result->getCorporations()));
    }

    public function testCorporationRemove204Admin()
    {
        $this->setupDb();
        $this->loginUser(10); # role watchlist-admin

        $response = $this->runApp(
            'PUT', "/api/user/watchlist/{$this->wl1}/corporation/remove/".$this->corporation1->getId()
        );
        $this->assertEquals(204, $response->getStatusCode());
    }

    public function testAllianceList403()
    {
        $this->setupDb();

        $response = $this->runApp('GET', "/api/user/watchlist/{$this->wl1}/alliance/list");
        $this->assertEquals(403, $response->getStatusCode());

        $this->loginUser(7); # role watchlist, not group member
        $response = $this->runApp('GET', "/api/user/watchlist/{$this->wl1}/alliance/list");
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testAllianceList200()
    {
        $this->setupDb();
        $this->loginUser(9); # role watchlist with group

        $response = $this->runApp('GET', "/api/user/watchlist/{$this->wl1}/alliance/list");
        $this->assertEquals(200, $response->getStatusCode());

        $this->assertSame([[
            'id' => $this->alliance1->getId(),
            'name' => null,
            'ticker' => null,
        ]], $this->parseJsonBody($response));
    }

    public function testAllianceList200Admin()
    {
        $this->setupDb();
        $this->loginUser(10); # role watchlist-admin

        $response = $this->runApp('GET', "/api/user/watchlist/{$this->wl1}/alliance/list");
        $this->assertEquals(200, $response->getStatusCode());

    }

    public function testAllianceAdd403()
    {
        $this->setupDb();

        $response = $this->runApp('PUT', "/api/user/watchlist/{$this->wl1}/alliance/add/100");
        $this->assertEquals(403, $response->getStatusCode());

        $this->loginUser(7); # not role watchlist-manager
        $response = $this->runApp('PUT', "/api/user/watchlist/{$this->wl1}/alliance/add/100");
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testAllianceAdd403Manager()
    {
        $this->setupDb();
        $this->loginUser(8); # role watchlist-manager

        $id = $this->wl1 + 10;
        $response = $this->runApp('PUT', "/api/user/watchlist/$id/alliance/add/100");
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testAllianceAdd403ManagerLocked()
    {
        $this->setupDb();
        $this->loginUser(8); # role watchlist-manager

        $this->repositoryFactory->getWatchlistRepository()->find($this->wl1)->setLockWatchlistSettings(true);
        $this->em->flush();

        $response = $this->runApp('PUT', "/api/user/watchlist/{$this->wl1}/alliance/add/".$this->alliance2->getId());
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testAllianceAdd404()
    {
        $this->setupDb();
        $this->loginUser(8); # role watchlist-manager

        $response = $this->runApp('PUT', "/api/user/watchlist/{$this->wl1}/alliance/add/100");
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testAllianceAdd204()
    {
        $this->setupDb();
        $this->loginUser(8); # role watchlist-manager

        $response1 = $this->runApp('PUT', "/api/user/watchlist/{$this->wl1}/alliance/add/".$this->alliance2->getId());
        $response2 = $this->runApp('PUT', "/api/user/watchlist/{$this->wl1}/alliance/add/".$this->alliance2->getId());
        $this->assertEquals(204, $response1->getStatusCode());
        $this->assertEquals(204, $response2->getStatusCode());

        $this->em->clear();
        $result = $this->repositoryFactory->getWatchlistRepository()->find($this->wl1);
        $this->assertSame(2, count($result->getAlliances()));
    }

    public function testAllianceAdd204Admin()
    {
        $this->setupDb();
        $this->loginUser(10); # role watchlist-admin

        $response = $this->runApp('PUT', "/api/user/watchlist/{$this->wl1}/alliance/add/".$this->alliance2->getId());
        $this->assertEquals(204, $response->getStatusCode());
    }

    public function testAllianceRemove403()
    {
        $this->setupDb();

        $response = $this->runApp('PUT', "/api/user/watchlist/{$this->wl1}/alliance/remove/100");
        $this->assertEquals(403, $response->getStatusCode());

        $this->loginUser(7); # not role watchlist-manager
        $response = $this->runApp('PUT', "/api/user/watchlist/{$this->wl1}/alliance/remove/100");
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testAllianceRemove403Manager()
    {
        $this->setupDb();
        $this->loginUser(8); # role watchlist-manager

        $id = $this->wl1 + 10;
        $response = $this->runApp('PUT', "/api/user/watchlist/$id/alliance/remove/100");
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testAllianceRemove403ManagerLocked()
    {
        $this->setupDb();
        $this->loginUser(8); # role watchlist-manager

        $this->repositoryFactory->getWatchlistRepository()->find($this->wl1)->setLockWatchlistSettings(true);
        $this->em->flush();

        $response = $this->runApp(
            'PUT', "/api/user/watchlist/{$this->wl1}/alliance/remove/".$this->alliance1->getId()
        );
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testAllianceRemove404()
    {
        $this->setupDb();
        $this->loginUser(8); # role watchlist-manager

        $response = $this->runApp('PUT', "/api/user/watchlist/{$this->wl1}/alliance/remove/100");
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testAllianceRemove204()
    {
        $this->setupDb();
        $this->loginUser(8); # role watchlist-manager

        $response = $this->runApp(
            'PUT', "/api/user/watchlist/{$this->wl1}/alliance/remove/".$this->alliance1->getId()
        );
        $this->assertEquals(204, $response->getStatusCode());

        $this->em->clear();
        $result = $this->repositoryFactory->getWatchlistRepository()->find($this->wl1);
        $this->assertSame(0, count($result->getAlliances()));
    }

    public function testAllianceRemove204Admin()
    {
        $this->setupDb();
        $this->loginUser(10); # role watchlist-admin

        $response = $this->runApp(
            'PUT', "/api/user/watchlist/{$this->wl1}/alliance/remove/".$this->alliance1->getId()
        );
        $this->assertEquals(204, $response->getStatusCode());
    }

    public function testGroupList403()
    {
        $this->setupDb();

        $response = $this->runApp('GET', "/api/user/watchlist/{$this->wl1}/group/list");
        $this->assertEquals(403, $response->getStatusCode());

        $this->loginUser(6); # not role watchlist-admin
        $response = $this->runApp('GET', "/api/user/watchlist/{$this->wl1}/group/list");
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testGroupList200()
    {
        $this->setupDb();
        $this->loginUser(10); # role watchlist-admin

        $response = $this->runApp('GET', "/api/user/watchlist/{$this->wl1}/group/list");
        $this->assertEquals(200, $response->getStatusCode());

        $this->assertSame([[
            'id' => $this->group1->getId(),
            'name' => 'g1',
            'visibility' => Group::VISIBILITY_PRIVATE,
        ]], $this->parseJsonBody($response));
    }

    public function testGroupAdd403()
    {
        $this->setupDb();

        $response = $this->runApp('PUT', "/api/user/watchlist/{$this->wl1}/group/add/100");
        $this->assertEquals(403, $response->getStatusCode());

        $this->loginUser(7); # not role watchlist-admin
        $response = $this->runApp('PUT', "/api/user/watchlist/{$this->wl1}/group/add/100");
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testGroupAdd404()
    {
        $this->setupDb();
        $this->loginUser(10); # role watchlist-admin

        $response = $this->runApp('PUT', "/api/user/watchlist/{$this->wl1}/group/add/100");
        $this->assertEquals(404, $response->getStatusCode());

        $id = $this->wl1 + 10;
        $response = $this->runApp('PUT', "/api/user/watchlist/$id/group/add/".$this->group2->getId());
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testGroupAdd204()
    {
        $this->setupDb();
        $this->loginUser(10); # role watchlist-admin

        $response1 = $this->runApp('PUT', "/api/user/watchlist/{$this->wl1}/group/add/".$this->group2->getId());
        $response2 = $this->runApp('PUT', "/api/user/watchlist/{$this->wl1}/group/add/".$this->group2->getId());
        $this->assertEquals(204, $response1->getStatusCode());
        $this->assertEquals(204, $response2->getStatusCode());

        $this->em->clear();
        $result = $this->repositoryFactory->getWatchlistRepository()->find($this->wl1);
        $this->assertSame(2, count($result->getGroups()));
    }

    public function testGroupRemove403()
    {
        $this->setupDb();

        $response = $this->runApp('PUT', "/api/user/watchlist/{$this->wl1}/group/remove/100");
        $this->assertEquals(403, $response->getStatusCode());

        $this->loginUser(8); # not role watchlist-admin
        $response = $this->runApp('PUT', "/api/user/watchlist/{$this->wl1}/group/remove/100");
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testGroupRemove404()
    {
        $this->setupDb();
        $this->loginUser(10); # role watchlist-admin

        $response = $this->runApp('PUT', "/api/user/watchlist/{$this->wl1}/group/remove/100");
        $this->assertEquals(404, $response->getStatusCode());

        $id = $this->wl1 + 10;
        $response = $this->runApp('PUT', "/api/user/watchlist/$id/group/remove/".$this->group1->getId());
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testGroupRemove204()
    {
        $this->setupDb();
        $this->loginUser(10); # role watchlist-admin

        $response = $this->runApp('PUT', "/api/user/watchlist/{$this->wl1}/group/remove/".$this->group1->getId());
        $this->assertEquals(204, $response->getStatusCode());

        $this->em->clear();
        $result = $this->repositoryFactory->getWatchlistRepository()->find($this->wl1);
        $this->assertSame(0, count($result->getGroups()));
    }

    public function testManagerGroupList403()
    {
        $this->setupDb();

        $response = $this->runApp('GET', "/api/user/watchlist/{$this->wl1}/manager-group/list");
        $this->assertEquals(403, $response->getStatusCode());

        $this->loginUser(6); # not role watchlist-admin
        $response = $this->runApp('GET', "/api/user/watchlist/{$this->wl1}/manager-group/list");
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testManagerGroupList200()
    {
        $this->setupDb();
        $this->loginUser(10); # role watchlist-admin

        $response = $this->runApp('GET', "/api/user/watchlist/{$this->wl1}/manager-group/list");
        $this->assertEquals(200, $response->getStatusCode());

        $this->assertSame([[
            'id' => $this->group3->getId(),
            'name' => 'g3',
            'visibility' => Group::VISIBILITY_PRIVATE,
        ]], $this->parseJsonBody($response));
    }

    public function testManagerGroupAdd403()
    {
        $this->setupDb();

        $response = $this->runApp('PUT', "/api/user/watchlist/{$this->wl1}/manager-group/add/100");
        $this->assertEquals(403, $response->getStatusCode());

        $this->loginUser(7); # not role watchlist-admin
        $response = $this->runApp('PUT', "/api/user/watchlist/{$this->wl1}/manager-group/add/100");
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testManagerGroupAdd404()
    {
        $this->setupDb();
        $this->loginUser(10); # role watchlist-admin

        $response = $this->runApp('PUT', "/api/user/watchlist/{$this->wl1}/manager-group/add/100");
        $this->assertEquals(404, $response->getStatusCode());

        $id = $this->wl1 + 10;
        $response = $this->runApp('PUT', "/api/user/watchlist/$id/manager-group/add/".$this->group2->getId());
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testManagerGroupAdd204()
    {
        $this->setupDb();
        $this->loginUser(10); # role watchlist-admin

        $response1 = $this->runApp('PUT', "/api/user/watchlist/{$this->wl1}/manager-group/add/".$this->group2->getId());
        $response2 = $this->runApp('PUT', "/api/user/watchlist/{$this->wl1}/manager-group/add/".$this->group2->getId());
        $this->assertEquals(204, $response1->getStatusCode());
        $this->assertEquals(204, $response2->getStatusCode());

        $this->em->clear();
        $result = $this->repositoryFactory->getWatchlistRepository()->find($this->wl1);
        $this->assertSame(2, count($result->getManagerGroups()));
    }

    public function testManagerGroupRemove403()
    {
        $this->setupDb();

        $response = $this->runApp('PUT', "/api/user/watchlist/{$this->wl1}/manager-group/remove/100");
        $this->assertEquals(403, $response->getStatusCode());

        $this->loginUser(8); # not role watchlist-admin
        $response = $this->runApp('PUT', "/api/user/watchlist/{$this->wl1}/manager-group/remove/100");
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testManagerGroupRemove404()
    {
        $this->setupDb();
        $this->loginUser(10); # role watchlist-admin

        $response = $this->runApp('PUT', "/api/user/watchlist/{$this->wl1}/manager-group/remove/100");
        $this->assertEquals(404, $response->getStatusCode());

        $id = $this->wl1 + 10;
        $response = $this->runApp('PUT', "/api/user/watchlist/$id/manager-group/remove/".$this->group1->getId());
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testManagerGroupRemove204()
    {
        $this->setupDb();
        $this->loginUser(10); # role watchlist-admin

        $response = $this->runApp(
            'PUT', "/api/user/watchlist/{$this->wl1}/manager-group/remove/".$this->group3->getId()
        );
        $this->assertEquals(204, $response->getStatusCode());

        $this->em->clear();
        $result = $this->repositoryFactory->getWatchlistRepository()->find($this->wl1);
        $this->assertSame(0, count($result->getManagerGroups()));
    }

    public function testKicklistCorporationList403()
    {
        $this->setupDb();

        $response = $this->runApp('GET', "/api/user/watchlist/{$this->wl1}/kicklist-corporation/list");
        $this->assertEquals(403, $response->getStatusCode());

        $this->loginUser(7); # role watchlist, not group member
        $response = $this->runApp('GET', "/api/user/watchlist/{$this->wl1}/kicklist-corporation/list");
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testKicklistCorporationList200()
    {
        $this->setupDb();
        $this->loginUser(9); # role watchlist with group

        $response = $this->runApp('GET', "/api/user/watchlist/{$this->wl1}/kicklist-corporation/list");
        $this->assertEquals(200, $response->getStatusCode());

        $this->assertSame([[
            'id' => $this->corporation3->getId(),
            'name' => null,
            'ticker' => null,
            'alliance' => null,
        ]], $this->parseJsonBody($response));
    }

    public function testKicklistCorporationAdd403()
    {
        $this->setupDb();

        $response = $this->runApp('PUT', "/api/user/watchlist/{$this->wl1}/kicklist-corporation/add/100");
        $this->assertEquals(403, $response->getStatusCode());

        $this->loginUser(7); # not role watchlist-manager
        $response = $this->runApp('PUT', "/api/user/watchlist/{$this->wl1}/kicklist-corporation/add/100");
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testKicklistCorporationAdd403Manager()
    {
        $this->setupDb();
        $this->loginUser(8); # role watchlist-manager

        $id = $this->wl1 + 10;
        $response = $this->runApp('PUT', "/api/user/watchlist/$id/kicklist-corporation/add/100");
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testKicklistCorporationAdd404()
    {
        $this->setupDb();
        $this->loginUser(8); # role watchlist-manager

        $response = $this->runApp('PUT', "/api/user/watchlist/{$this->wl1}/kicklist-corporation/add/100");
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testKicklistCorporationAdd204()
    {
        $this->setupDb();
        $this->loginUser(8); # role watchlist-manager

        $response1 = $this->runApp(
            'PUT',
            "/api/user/watchlist/{$this->wl1}/kicklist-corporation/add/".$this->corporation2->getId()
        );
        $response2 = $this->runApp(
            'PUT',
            "/api/user/watchlist/{$this->wl1}/kicklist-corporation/add/".$this->corporation2->getId()
        );
        $this->assertEquals(204, $response1->getStatusCode());
        $this->assertEquals(204, $response2->getStatusCode());

        $this->em->clear();
        $result = $this->repositoryFactory->getWatchlistRepository()->find($this->wl1);
        $this->assertSame(2, count($result->getKicklistCorporations()));
    }

    public function testKicklistCorporationRemove403()
    {
        $this->setupDb();

        $response = $this->runApp('PUT', "/api/user/watchlist/{$this->wl1}/kicklist-corporation/remove/100");
        $this->assertEquals(403, $response->getStatusCode());

        $this->loginUser(7); # not role watchlist-manager
        $response = $this->runApp('PUT', "/api/user/watchlist/{$this->wl1}/kicklist-corporation/remove/100");
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testKicklistCorporationRemove403Manager()
    {
        $this->setupDb();
        $this->loginUser(8); # role watchlist-manager

        $id = $this->wl1 + 10;
        $response = $this->runApp('PUT', "/api/user/watchlist/$id/kicklist-corporation/remove/100");
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testKicklistCorporationRemove404()
    {
        $this->setupDb();
        $this->loginUser(8); # role watchlist-manager

        $response = $this->runApp('PUT', "/api/user/watchlist/{$this->wl1}/kicklist-corporation/remove/100");
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testKicklistCorporationRemove204()
    {
        $this->setupDb();
        $this->loginUser(8); # role watchlist-manager

        $response = $this->runApp(
            'PUT',
            "/api/user/watchlist/{$this->wl1}/kicklist-corporation/remove/".$this->corporation3->getId()
        );
        $this->assertEquals(204, $response->getStatusCode());

        $this->em->clear();
        $result = $this->repositoryFactory->getWatchlistRepository()->find($this->wl1);
        $this->assertSame(0, count($result->getKicklistCorporations()));
    }

    public function testKicklistAllianceList403()
    {
        $this->setupDb();

        $response = $this->runApp('GET', "/api/user/watchlist/{$this->wl1}/kicklist-alliance/list");
        $this->assertEquals(403, $response->getStatusCode());

        $this->loginUser(7); # role watchlist, not group member
        $response = $this->runApp('GET', "/api/user/watchlist/{$this->wl1}/kicklist-alliance/list");
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testKicklistAllianceList200()
    {
        $this->setupDb();
        $this->loginUser(9); # role watchlist with group

        $response = $this->runApp('GET', "/api/user/watchlist/{$this->wl1}/kicklist-alliance/list");
        $this->assertEquals(200, $response->getStatusCode());

        $this->assertSame([[
            'id' => $this->alliance2->getId(),
            'name' => null,
            'ticker' => null,
        ]], $this->parseJsonBody($response));
    }

    public function testKicklistAllianceAdd403()
    {
        $this->setupDb();

        $response = $this->runApp('PUT', "/api/user/watchlist/{$this->wl1}/kicklist-alliance/add/100");
        $this->assertEquals(403, $response->getStatusCode());

        $this->loginUser(7); # not role watchlist-manager
        $response = $this->runApp('PUT', "/api/user/watchlist/{$this->wl1}/kicklist-alliance/add/100");
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testKicklistAllianceAdd403Manager()
    {
        $this->setupDb();
        $this->loginUser(8); # role watchlist-manager

        $id = $this->wl1 + 10;
        $response = $this->runApp('PUT', "/api/user/watchlist/$id/kicklist-alliance/add/100");
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testKicklistAllianceAdd404()
    {
        $this->setupDb();
        $this->loginUser(8); # role watchlist-manager

        $response = $this->runApp('PUT', "/api/user/watchlist/{$this->wl1}/kicklist-alliance/add/100");
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testKicklistAllianceAdd204()
    {
        $this->setupDb();
        $this->loginUser(8); # role watchlist-manager

        $response1 = $this->runApp(
            'PUT', "/api/user/watchlist/{$this->wl1}/kicklist-alliance/add/".$this->alliance1->getId()
        );
        $response2 = $this->runApp(
            'PUT', "/api/user/watchlist/{$this->wl1}/kicklist-alliance/add/".$this->alliance1->getId()
        );
        $this->assertEquals(204, $response1->getStatusCode());
        $this->assertEquals(204, $response2->getStatusCode());

        $this->em->clear();
        $result = $this->repositoryFactory->getWatchlistRepository()->find($this->wl1);
        $this->assertSame(2, count($result->getKicklistAlliances()));
    }

    public function testKicklistAllianceRemove403()
    {
        $this->setupDb();

        $response = $this->runApp('PUT', "/api/user/watchlist/{$this->wl1}/kicklist-alliance/remove/100");
        $this->assertEquals(403, $response->getStatusCode());

        $this->loginUser(7); # not role watchlist-manager
        $response = $this->runApp('PUT', "/api/user/watchlist/{$this->wl1}/kicklist-alliance/remove/100");
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testKicklistAllianceRemove403Manager()
    {
        $this->setupDb();
        $this->loginUser(8); # role watchlist-manager

        $id = $this->wl1 + 10;
        $response = $this->runApp('PUT', "/api/user/watchlist/$id/kicklist-alliance/remove/100");
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testKicklistAllianceRemove404()
    {
        $this->setupDb();
        $this->loginUser(8); # role watchlist-manager

        $response = $this->runApp('PUT', "/api/user/watchlist/{$this->wl1}/kicklist-alliance/remove/100");
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testKicklistAllianceRemove204()
    {
        $this->setupDb();
        $this->loginUser(8); # role watchlist-manager

        $response = $this->runApp(
            'PUT', "/api/user/watchlist/{$this->wl1}/kicklist-alliance/remove/".$this->alliance2->getId()
        );
        $this->assertEquals(204, $response->getStatusCode());

        $this->em->clear();
        $result = $this->repositoryFactory->getWatchlistRepository()->find($this->wl1);
        $this->assertSame(0, count($result->getKicklistAlliances()));
    }

    public function testAllowlistCorporationList403()
    {
        $this->setupDb();

        $response = $this->runApp('GET', "/api/user/watchlist/{$this->wl1}/allowlist-corporation/list");
        $this->assertEquals(403, $response->getStatusCode());

        $this->loginUser(7); # role watchlist, not group member
        $response = $this->runApp('GET', "/api/user/watchlist/{$this->wl1}/allowlist-corporation/list");
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testAllowlistCorporationList200()
    {
        $this->setupDb();
        $this->loginUser(9); # role watchlist with group

        $response = $this->runApp('GET', "/api/user/watchlist/{$this->wl1}/allowlist-corporation/list");
        $this->assertEquals(200, $response->getStatusCode());

        $this->assertSame([[
            'id' => $this->corporation1->getId(),
            'name' => null,
            'ticker' => null,
            'alliance' => null,
            'autoAllowlist' => false,
        ]], $this->parseJsonBody($response));
    }

    public function testAllowlistCorporationAdd403()
    {
        $this->setupDb();

        $response = $this->runApp('PUT', "/api/user/watchlist/{$this->wl1}/allowlist-corporation/add/100");
        $this->assertEquals(403, $response->getStatusCode());

        $this->loginUser(7); # not role watchlist-manager
        $response = $this->runApp('PUT', "/api/user/watchlist/{$this->wl1}/allowlist-corporation/add/100");
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testAllowlistCorporationAdd403Manager()
    {
        $this->setupDb();
        $this->loginUser(8); # role watchlist-manager

        $id = $this->wl1 + 10;
        $response = $this->runApp('PUT', "/api/user/watchlist/$id/allowlist-corporation/add/100");
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testAllowlistCorporationAdd404()
    {
        $this->setupDb();
        $this->loginUser(8); # role watchlist-manager

        $response = $this->runApp('PUT', "/api/user/watchlist/{$this->wl1}/allowlist-corporation/add/100");
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testAllowlistCorporationAdd204()
    {
        $this->setupDb();
        $this->loginUser(8); # role watchlist-manager

        $response1 = $this->runApp(
            'PUT',
            "/api/user/watchlist/{$this->wl1}/allowlist-corporation/add/".$this->corporation2->getId()
        );
        $response2 = $this->runApp(
            'PUT',
            "/api/user/watchlist/{$this->wl1}/allowlist-corporation/add/".$this->corporation2->getId()
        );
        $this->assertEquals(204, $response1->getStatusCode());
        $this->assertEquals(204, $response2->getStatusCode());

        $this->em->clear();
        $result = $this->repositoryFactory->getWatchlistRepository()->find($this->wl1);
        $this->assertSame(2, count($result->getAllowlistCorporations()));
    }

    public function testAllowlistCorporationRemove403()
    {
        $this->setupDb();

        $response = $this->runApp('PUT', "/api/user/watchlist/{$this->wl1}/allowlist-corporation/remove/100");
        $this->assertEquals(403, $response->getStatusCode());

        $this->loginUser(7); # not role watchlist-manager
        $response = $this->runApp('PUT', "/api/user/watchlist/{$this->wl1}/allowlist-corporation/remove/100");
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testAllowlistCorporationRemove403Manager()
    {
        $this->setupDb();
        $this->loginUser(8); # role watchlist-manager

        $id = $this->wl1 + 10;
        $response = $this->runApp('PUT', "/api/user/watchlist/$id/allowlist-corporation/remove/100");
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testAllowlistCorporationRemove404()
    {
        $this->setupDb();
        $this->loginUser(8); # role watchlist-manager

        $response = $this->runApp('PUT', "/api/user/watchlist/{$this->wl1}/allowlist-corporation/remove/100");
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testAllowlistCorporationRemove204()
    {
        $this->setupDb();
        $this->loginUser(8); # role watchlist-manager

        $response = $this->runApp(
            'PUT',
            "/api/user/watchlist/{$this->wl1}/allowlist-corporation/remove/".$this->corporation1->getId()
        );
        $this->assertEquals(204, $response->getStatusCode());

        $this->em->clear();
        $result = $this->repositoryFactory->getWatchlistRepository()->find($this->wl1);
        $this->assertSame(0, count($result->getAllowlistCorporations()));
    }

    public function testAllowlistAllianceList403()
    {
        $this->setupDb();

        $response = $this->runApp('GET', "/api/user/watchlist/{$this->wl1}/allowlist-alliance/list");
        $this->assertEquals(403, $response->getStatusCode());

        $this->loginUser(7); # role watchlist, not group member
        $response = $this->runApp('GET', "/api/user/watchlist/{$this->wl1}/allowlist-alliance/list");
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testAllowlistAllianceList200()
    {
        $this->setupDb();
        $this->loginUser(9); # role watchlist with group

        $response = $this->runApp('GET', "/api/user/watchlist/{$this->wl1}/allowlist-alliance/list");
        $this->assertEquals(200, $response->getStatusCode());

        $this->assertSame([[
            'id' => $this->alliance1->getId(),
            'name' => null,
            'ticker' => null,
        ]], $this->parseJsonBody($response));
    }

    public function testAllowlistAllianceAdd403()
    {
        $this->setupDb();

        $response = $this->runApp('PUT', "/api/user/watchlist/{$this->wl1}/allowlist-alliance/add/100");
        $this->assertEquals(403, $response->getStatusCode());

        $this->loginUser(7); # not role watchlist-manager
        $response = $this->runApp('PUT', "/api/user/watchlist/{$this->wl1}/allowlist-alliance/add/100");
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testAllowlistAllianceAdd403Manager()
    {
        $this->setupDb();
        $this->loginUser(8); # role watchlist-manager

        $id = $this->wl1 + 10;
        $response = $this->runApp('PUT', "/api/user/watchlist/$id/allowlist-alliance/add/100");
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testAllowlistAllianceAdd404()
    {
        $this->setupDb();
        $this->loginUser(8); # role watchlist-manager

        $response = $this->runApp('PUT', "/api/user/watchlist/{$this->wl1}/allowlist-alliance/add/100");
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testAllowlistAllianceAdd204()
    {
        $this->setupDb();
        $this->loginUser(8); # role watchlist-manager

        $response1 = $this->runApp(
            'PUT', "/api/user/watchlist/{$this->wl1}/allowlist-alliance/add/".$this->alliance2->getId()
        );
        $response2 = $this->runApp(
            'PUT', "/api/user/watchlist/{$this->wl1}/allowlist-alliance/add/".$this->alliance2->getId()
        );
        $this->assertEquals(204, $response1->getStatusCode());
        $this->assertEquals(204, $response2->getStatusCode());

        $this->em->clear();
        $result = $this->repositoryFactory->getWatchlistRepository()->find($this->wl1);
        $this->assertSame(2, count($result->getAllowlistAlliances()));
    }

    public function testAllowlistAllianceRemove403()
    {
        $this->setupDb();

        $response = $this->runApp('PUT', "/api/user/watchlist/{$this->wl1}/allowlist-alliance/remove/100");
        $this->assertEquals(403, $response->getStatusCode());

        $this->loginUser(7); # not role watchlist-manager
        $response = $this->runApp('PUT', "/api/user/watchlist/{$this->wl1}/allowlist-alliance/remove/100");
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testAllowlistAllianceRemove403Manager()
    {
        $this->setupDb();
        $this->loginUser(8); # role watchlist-manager

        $id = $this->wl1 + 10;
        $response = $this->runApp('PUT', "/api/user/watchlist/$id/allowlist-alliance/remove/100");
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testAllowlistAllianceRemove404()
    {
        $this->setupDb();
        $this->loginUser(8); # role watchlist-manager

        $response = $this->runApp('PUT', "/api/user/watchlist/{$this->wl1}/allowlist-alliance/remove/100");
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testAllowlistAllianceRemove204()
    {
        $this->setupDb();
        $this->loginUser(8); # role watchlist-manager

        $response = $this->runApp(
            'PUT',
            "/api/user/watchlist/{$this->wl1}/allowlist-alliance/remove/".$this->alliance1->getId()
        );
        $this->assertEquals(204, $response->getStatusCode());

        $this->em->clear();
        $result = $this->repositoryFactory->getWatchlistRepository()->find($this->wl1);
        $this->assertSame(0, count($result->getAllowlistAlliances()));
    }

    private function setupDb(): void
    {
        $this->helper->emptyDb();

        $watchlist = (new Watchlist())->setName('ws1');
        $watchlist2 = (new Watchlist())->setName('ws2');

        $this->player1 = $this->helper->addCharacterMain('User', 6, [Role::USER])->getPlayer();
        $this->player2 = $this->helper->addCharacterMain('Read', 7, [Role::WATCHLIST])->getPlayer();
        $playerM = $this->helper->addCharacterMain('Manager', 8, [Role::WATCHLIST_MANAGER])->getPlayer();
        $this->player3 = $this->helper->addCharacterMain('Watchlist', 9, [Role::WATCHLIST])->getPlayer();
        $this->helper->addCharacterMain('Admin', 10, [Role::WATCHLIST_ADMIN]);

        $this->corporation1 = (new Corporation())->setId(98000101);
        $this->corporation2 = (new Corporation())->setId(98000102);
        $this->corporation3 = (new Corporation())->setId(98000103);

        // setup for "watchlist" testPlayers200
        $this->helper->addCharacterToPlayer('c2b', 20, $this->player2);
        $this->helper->addCharacterToPlayer('c3b', 30, $this->player3);
        $this->player2->getCharacters()[0]->setCorporation($this->corporation1); // warning list
        $this->player2->getCharacters()[1]->setCorporation($this->corporation2); // not on allowlist
        $this->player3->getCharacters()[0]->setCorporation($this->corporation1);
        $this->player3->getCharacters()[1]->setCorporation($this->corporation2);
        $this->player3->getCharacters()[1]->setCorporation($this->corporation3); // kicklist

        $this->alliance1 = (new Alliance())->setId(201);
        $this->alliance2 = (new Alliance())->setId(202);

        $this->group1 = (new Group())->setName('g1');
        $this->group2 = (new Group())->setName('g2');
        $this->group3 = (new Group())->setName('g3');

        $watchlist->addExemption($this->player1);
        $watchlist->addCorporation($this->corporation1);
        $watchlist->addAlliance($this->alliance1);
        $watchlist->addGroup($this->group1);
        $this->player3->addGroup($this->group1);
        $playerM->addGroup($this->group1);
        $watchlist->addManagerGroup($this->group3);
        $playerM->addGroup($this->group3);
        $watchlist->addKicklistCorporation($this->corporation3);
        $watchlist->addKicklistAlliance($this->alliance2);
        $watchlist->addAllowlistCorporation($this->corporation1);
        $watchlist->addAllowlistAlliance($this->alliance1);

        $this->em->persist($watchlist);
        $this->em->persist($watchlist2);
        $this->em->persist($this->corporation1);
        $this->em->persist($this->corporation2);
        $this->em->persist($this->corporation3);
        $this->em->persist($this->alliance1);
        $this->em->persist($this->alliance2);
        $this->em->persist($this->group1);
        $this->em->persist($this->group2);
        $this->em->persist($this->group3);

        $this->em->flush();

        $this->wl1 = $watchlist->getId();
        $this->wl2 = $watchlist2->getId();
    }
}
