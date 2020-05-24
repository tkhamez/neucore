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

    protected function setUp(): void
    {
        $_SESSION = null;
        $this->helper = new Helper();
        $this->em = $this->helper->getObjectManager();
        $this->repositoryFactory = new RepositoryFactory($this->em);
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
            [['id' => 1, 'name' => 'ws1'], ['id' => 2, 'name' => 'ws2']],
            $this->parseJsonBody($response)
        );
    }

    public function testListAvailable403()
    {
        $response1 = $this->runApp('GET', '/api/user/watchlist/listAvailable');
        $this->assertEquals(403, $response1->getStatusCode());

        $this->setupDb();
        $this->loginUser(6); # not role watchlist

        $response2 = $this->runApp('GET', '/api/user/watchlist/listAvailable');
        $this->assertEquals(403, $response2->getStatusCode());
    }

    public function testListAvailable200()
    {
        $this->setupDb();
        $this->loginUser(9); # role watchlist with group

        $response = $this->runApp('GET', '/api/user/watchlist/listAvailable');
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertSame(
            [['id' => 1, 'name' => 'ws1']],
            $this->parseJsonBody($response)
        );
    }

    public function testPlayers403()
    {
        $response = $this->runApp('GET', '/api/user/watchlist/1/players');
        $this->assertEquals(403, $response->getStatusCode());

        $this->setupDb();

        $this->loginUser(6); # not role watchlist
        $response = $this->runApp('GET', '/api/user/watchlist/1/players');
        $this->assertEquals(403, $response->getStatusCode());

        $this->loginUser(7); # role watchlist, not group member
        $response = $this->runApp('GET', '/api/user/watchlist/1/players');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testPlayers200()
    {
        $this->setupDb();
        $this->loginUser(9); # role watchlist with group

        $response = $this->runApp('GET', '/api/user/watchlist/1/players');
        $this->assertEquals(200, $response->getStatusCode());

        $this->assertSame([[
            'id' => $this->player2->getId(),
            'name' => 'Read'
        ]], $this->parseJsonBody($response));
    }

    public function testPlayersBlacklist403()
    {
        $response = $this->runApp('GET', '/api/user/watchlist/1/players-blacklist');
        $this->assertEquals(403, $response->getStatusCode());

        $this->setupDb();

        $this->loginUser(6); # not role watchlist
        $response = $this->runApp('GET', '/api/user/watchlist/1/players-blacklist');
        $this->assertEquals(403, $response->getStatusCode());

        $this->loginUser(7); # role watchlist, not group member
        $response = $this->runApp('GET', '/api/user/watchlist/1/players-blacklist');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testPlayersBlacklist200()
    {
        $this->setupDb();
        $this->loginUser(9); # role watchlist with group

        $response = $this->runApp('GET', '/api/user/watchlist/1/players-blacklist');
        $this->assertEquals(200, $response->getStatusCode());

        $this->assertSame([[
            'id' => $this->player3->getId(),
            'name' => 'Watchlist'
        ]], $this->parseJsonBody($response));
    }

    public function testExemptionList403()
    {
        $response = $this->runApp('GET', '/api/user/watchlist/1/exemption/list');
        $this->assertEquals(403, $response->getStatusCode());

        $this->setupDb();

        $this->loginUser(6); # not role watchlist
        $response = $this->runApp('GET', '/api/user/watchlist/1/exemption/list');
        $this->assertEquals(403, $response->getStatusCode());

        $this->loginUser(7); # role watchlist, not group member
        $response = $this->runApp('GET', '/api/user/watchlist/1/exemption/list');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testExemptionList200()
    {
        $this->setupDb();
        $this->loginUser(9); # role watchlist with group

        $response = $this->runApp('GET', '/api/user/watchlist/1/exemption/list');
        $this->assertEquals(200, $response->getStatusCode());

        $this->assertSame([[
            'id' => $this->player1->getId(),
            'name' => 'User'
        ]], $this->parseJsonBody($response));
    }

    public function testExemptionAdd403()
    {
        $response = $this->runApp('PUT', '/api/user/watchlist/1/exemption/add/100');
        $this->assertEquals(403, $response->getStatusCode());

        $this->setupDb();
        $this->loginUser(7); # not role watchlist-manager
        $response = $this->runApp('PUT', '/api/user/watchlist/1/exemption/add/100');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testExemptionAdd403Manager()
    {
        $this->setupDb();
        $this->loginUser(8); # role watchlist-manager

        $response = $this->runApp('PUT', '/api/user/watchlist/10/exemption/add/100');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testExemptionAdd404()
    {
        $this->setupDb();
        $this->loginUser(8); # role watchlist-manager

        $response = $this->runApp('PUT', '/api/user/watchlist/1/exemption/add/100');
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testExemptionAdd204()
    {
        $this->setupDb();
        $this->loginUser(8); # role watchlist-manager

        $response1 = $this->runApp('PUT', '/api/user/watchlist/1/exemption/add/'.$this->player2->getId());
        $response2 = $this->runApp('PUT', '/api/user/watchlist/1/exemption/add/'.$this->player2->getId());
        $this->assertEquals(204, $response1->getStatusCode());
        $this->assertEquals(204, $response2->getStatusCode());

        $this->em->clear();
        $result = $this->repositoryFactory->getWatchlistRepository()->find(1);
        $this->assertSame(2, count($result->getExemptions()));
    }

    public function testExemptionRemove403()
    {
        $response = $this->runApp('PUT', '/api/user/watchlist/1/exemption/remove/100');
        $this->assertEquals(403, $response->getStatusCode());

        $this->setupDb();
        $this->loginUser(7); # not role watchlist-manager
        $response = $this->runApp('PUT', '/api/user/watchlist/1/exemption/remove/100');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testExemptionRemove403Manager()
    {
        $this->setupDb();
        $this->loginUser(8); # role watchlist-manager

        $response = $this->runApp('PUT', '/api/user/watchlist/10/exemption/remove/100');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testExemptionRemove404()
    {
        $this->setupDb();
        $this->loginUser(8); # role watchlist-manager

        $response = $this->runApp('PUT', '/api/user/watchlist/1/exemption/remove/100');
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testExemptionRemove204()
    {
        $this->setupDb();
        $this->loginUser(8); # role watchlist-manager

        $response = $this->runApp('PUT', '/api/user/watchlist/1/exemption/remove/'.$this->player1->getId());
        $this->assertEquals(204, $response->getStatusCode());

        $this->em->clear();
        $result = $this->repositoryFactory->getWatchlistRepository()->find(1);
        $this->assertSame(0, count($result->getExemptions()));
    }

    public function testCorporationList403()
    {
        $response = $this->runApp('GET', '/api/user/watchlist/1/corporation/list');
        $this->assertEquals(403, $response->getStatusCode());

        $this->setupDb();
        $this->loginUser(7); # role watchlist, not group member
        $response = $this->runApp('GET', '/api/user/watchlist/1/corporation/list');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testCorporationList200()
    {
        $this->setupDb();
        $this->loginUser(9); # role watchlist with group

        $response = $this->runApp('GET', '/api/user/watchlist/1/corporation/list');
        $this->assertEquals(200, $response->getStatusCode());

        $this->assertSame([[
            'id' => $this->corporation1->getId(),
            'name' => null,
            'ticker' => null,
            'alliance' => null,
        ]], $this->parseJsonBody($response));
    }

    public function testCorporationAdd403()
    {
        $response = $this->runApp('PUT', '/api/user/watchlist/1/corporation/add/100');
        $this->assertEquals(403, $response->getStatusCode());

        $this->setupDb();
        $this->loginUser(7); # not role watchlist-manager
        $response = $this->runApp('PUT', '/api/user/watchlist/1/corporation/add/100');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testCorporationAdd403Manager()
    {
        $this->setupDb();
        $this->loginUser(8); # role watchlist-manager

        $response = $this->runApp('PUT', '/api/user/watchlist/10/corporation/add/100');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testCorporationAdd404()
    {
        $this->setupDb();
        $this->loginUser(8); # role watchlist-manager

        $response = $this->runApp('PUT', '/api/user/watchlist/1/corporation/add/100');
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testCorporationAdd204()
    {
        $this->setupDb();
        $this->loginUser(8); # role watchlist-manager

        $response1 = $this->runApp('PUT', '/api/user/watchlist/1/corporation/add/'.$this->corporation2->getId());
        $response2 = $this->runApp('PUT', '/api/user/watchlist/1/corporation/add/'.$this->corporation2->getId());
        $this->assertEquals(204, $response1->getStatusCode());
        $this->assertEquals(204, $response2->getStatusCode());

        $this->em->clear();
        $result = $this->repositoryFactory->getWatchlistRepository()->find(1);
        $this->assertSame(2, count($result->getCorporations()));
    }

    public function testCorporationRemove403()
    {
        $response = $this->runApp('PUT', '/api/user/watchlist/1/corporation/remove/100');
        $this->assertEquals(403, $response->getStatusCode());

        $this->setupDb();
        $this->loginUser(7); # not role watchlist-manager
        $response = $this->runApp('PUT', '/api/user/watchlist/1/corporation/remove/100');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testCorporationRemove403Manager()
    {
        $this->setupDb();
        $this->loginUser(8); # role watchlist-manager

        $response = $this->runApp('PUT', '/api/user/watchlist/10/corporation/remove/100');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testCorporationRemove404()
    {
        $this->setupDb();
        $this->loginUser(8); # role watchlist-manager

        $response = $this->runApp('PUT', '/api/user/watchlist/1/corporation/remove/100');
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testCorporationRemove204()
    {
        $this->setupDb();
        $this->loginUser(8); # role watchlist-manager

        $response = $this->runApp('PUT', '/api/user/watchlist/1/corporation/remove/'.$this->corporation1->getId());
        $this->assertEquals(204, $response->getStatusCode());

        $this->em->clear();
        $result = $this->repositoryFactory->getWatchlistRepository()->find(1);
        $this->assertSame(0, count($result->getCorporations()));
    }

    public function testAllianceList403()
    {
        $response = $this->runApp('GET', '/api/user/watchlist/1/alliance/list');
        $this->assertEquals(403, $response->getStatusCode());

        $this->setupDb();
        $this->loginUser(7); # role watchlist, not group member
        $response = $this->runApp('GET', '/api/user/watchlist/1/alliance/list');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testAllianceList200()
    {
        $this->setupDb();
        $this->loginUser(9); # role watchlist with group

        $response = $this->runApp('GET', '/api/user/watchlist/1/alliance/list');
        $this->assertEquals(200, $response->getStatusCode());

        $this->assertSame([[
            'id' => $this->alliance1->getId(),
            'name' => null,
            'ticker' => null,
        ]], $this->parseJsonBody($response));
    }

    public function testAllianceAdd403()
    {
        $response = $this->runApp('PUT', '/api/user/watchlist/1/alliance/add/100');
        $this->assertEquals(403, $response->getStatusCode());

        $this->setupDb();
        $this->loginUser(7); # not role watchlist-manager
        $response = $this->runApp('PUT', '/api/user/watchlist/1/alliance/add/100');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testAllianceAdd403Manager()
    {
        $this->setupDb();
        $this->loginUser(8); # role watchlist-manager

        $response = $this->runApp('PUT', '/api/user/watchlist/10/alliance/add/100');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testAllianceAdd404()
    {
        $this->setupDb();
        $this->loginUser(8); # role watchlist-manager

        $response = $this->runApp('PUT', '/api/user/watchlist/1/alliance/add/100');
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testAllianceAdd204()
    {
        $this->setupDb();
        $this->loginUser(8); # role watchlist-manager

        $response1 = $this->runApp('PUT', '/api/user/watchlist/1/alliance/add/'.$this->alliance2->getId());
        $response2 = $this->runApp('PUT', '/api/user/watchlist/1/alliance/add/'.$this->alliance2->getId());
        $this->assertEquals(204, $response1->getStatusCode());
        $this->assertEquals(204, $response2->getStatusCode());

        $this->em->clear();
        $result = $this->repositoryFactory->getWatchlistRepository()->find(1);
        $this->assertSame(2, count($result->getAlliances()));
    }

    public function testAllianceRemove403()
    {
        $response = $this->runApp('PUT', '/api/user/watchlist/1/alliance/remove/100');
        $this->assertEquals(403, $response->getStatusCode());

        $this->setupDb();
        $this->loginUser(7); # not role watchlist-manager
        $response = $this->runApp('PUT', '/api/user/watchlist/1/alliance/remove/100');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testAllianceRemove403Manager()
    {
        $this->setupDb();
        $this->loginUser(8); # role watchlist-manager

        $response = $this->runApp('PUT', '/api/user/watchlist/10/alliance/remove/100');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testAllianceRemove404()
    {
        $this->setupDb();
        $this->loginUser(8); # role watchlist-manager

        $response = $this->runApp('PUT', '/api/user/watchlist/1/alliance/remove/100');
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testAllianceRemove204()
    {
        $this->setupDb();
        $this->loginUser(8); # role watchlist-manager

        $response = $this->runApp('PUT', '/api/user/watchlist/1/alliance/remove/'.$this->alliance1->getId());
        $this->assertEquals(204, $response->getStatusCode());

        $this->em->clear();
        $result = $this->repositoryFactory->getWatchlistRepository()->find(1);
        $this->assertSame(0, count($result->getAlliances()));
    }

    public function testGroupList403()
    {
        $response = $this->runApp('GET', '/api/user/watchlist/1/group/list');
        $this->assertEquals(403, $response->getStatusCode());

        $this->setupDb();
        $this->loginUser(6); # not role watchlist-admin
        $response = $this->runApp('GET', '/api/user/watchlist/1/group/list');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testGroupList200()
    {
        $this->setupDb();
        $this->loginUser(10); # role watchlist-admin

        $response = $this->runApp('GET', '/api/user/watchlist/1/group/list');
        $this->assertEquals(200, $response->getStatusCode());

        $this->assertSame([[
            'id' => $this->group1->getId(),
            'name' => 'g1',
            'visibility' => Group::VISIBILITY_PRIVATE,
        ]], $this->parseJsonBody($response));
    }

    public function testGroupAdd403()
    {
        $response = $this->runApp('PUT', '/api/user/watchlist/1/group/add/100');
        $this->assertEquals(403, $response->getStatusCode());

        $this->setupDb();
        $this->loginUser(7); # not role watchlist-admin
        $response = $this->runApp('PUT', '/api/user/watchlist/1/group/add/100');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testGroupAdd404()
    {
        $this->setupDb();
        $this->loginUser(10); # role watchlist-admin

        $response = $this->runApp('PUT', '/api/user/watchlist/1/group/add/100');
        $this->assertEquals(404, $response->getStatusCode());

        $response = $this->runApp('PUT', '/api/user/watchlist/10/group/add/'.$this->group2->getId());
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testGroupAdd204()
    {
        $this->setupDb();
        $this->loginUser(10); # role watchlist-admin

        $response1 = $this->runApp('PUT', '/api/user/watchlist/1/group/add/'.$this->group2->getId());
        $response2 = $this->runApp('PUT', '/api/user/watchlist/1/group/add/'.$this->group2->getId());
        $this->assertEquals(204, $response1->getStatusCode());
        $this->assertEquals(204, $response2->getStatusCode());

        $this->em->clear();
        $result = $this->repositoryFactory->getWatchlistRepository()->find(1);
        $this->assertSame(2, count($result->getGroups()));
    }

    public function testGroupRemove403()
    {
        $response = $this->runApp('PUT', '/api/user/watchlist/1/group/remove/100');
        $this->assertEquals(403, $response->getStatusCode());

        $this->setupDb();
        $this->loginUser(8); # not role watchlist-admin
        $response = $this->runApp('PUT', '/api/user/watchlist/1/group/remove/100');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testGroupRemove404()
    {
        $this->setupDb();
        $this->loginUser(10); # role watchlist-admin

        $response = $this->runApp('PUT', '/api/user/watchlist/1/group/remove/100');
        $this->assertEquals(404, $response->getStatusCode());

        $response = $this->runApp('PUT', '/api/user/watchlist/10/group/remove/'.$this->group1->getId());
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testGroupRemove204()
    {
        $this->setupDb();
        $this->loginUser(10); # role watchlist-admin

        $response = $this->runApp('PUT', '/api/user/watchlist/1/group/remove/'.$this->group1->getId());
        $this->assertEquals(204, $response->getStatusCode());

        $this->em->clear();
        $result = $this->repositoryFactory->getWatchlistRepository()->find(1);
        $this->assertSame(0, count($result->getGroups()));
    }

    public function testBlacklistCorporationList403()
    {
        $response = $this->runApp('GET', '/api/user/watchlist/1/blacklist-corporation/list');
        $this->assertEquals(403, $response->getStatusCode());

        $this->setupDb();
        $this->loginUser(7); # role watchlist, not group member
        $response = $this->runApp('GET', '/api/user/watchlist/1/blacklist-corporation/list');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testBlacklistCorporationList200()
    {
        $this->setupDb();
        $this->loginUser(9); # role watchlist with group

        $response = $this->runApp('GET', '/api/user/watchlist/1/blacklist-corporation/list');
        $this->assertEquals(200, $response->getStatusCode());

        $this->assertSame([[
            'id' => $this->corporation3->getId(),
            'name' => null,
            'ticker' => null,
            'alliance' => null,
        ]], $this->parseJsonBody($response));
    }

    public function testBlacklistCorporationAdd403()
    {
        $response = $this->runApp('PUT', '/api/user/watchlist/1/blacklist-corporation/add/100');
        $this->assertEquals(403, $response->getStatusCode());

        $this->setupDb();
        $this->loginUser(7); # not role watchlist-manager
        $response = $this->runApp('PUT', '/api/user/watchlist/1/blacklist-corporation/add/100');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testBlacklistCorporationAdd403Manager()
    {
        $this->setupDb();
        $this->loginUser(8); # role watchlist-manager

        $response = $this->runApp('PUT', '/api/user/watchlist/10/blacklist-corporation/add/100');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testBlacklistCorporationAdd404()
    {
        $this->setupDb();
        $this->loginUser(8); # role watchlist-manager

        $response = $this->runApp('PUT', '/api/user/watchlist/1/blacklist-corporation/add/100');
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testBlacklistCorporationAdd204()
    {
        $this->setupDb();
        $this->loginUser(8); # role watchlist-manager

        $response1 = $this->runApp(
            'PUT',
            '/api/user/watchlist/1/blacklist-corporation/add/'.$this->corporation2->getId()
        );
        $response2 = $this->runApp(
            'PUT',
            '/api/user/watchlist/1/blacklist-corporation/add/'.$this->corporation2->getId()
        );
        $this->assertEquals(204, $response1->getStatusCode());
        $this->assertEquals(204, $response2->getStatusCode());

        $this->em->clear();
        $result = $this->repositoryFactory->getWatchlistRepository()->find(1);
        $this->assertSame(2, count($result->getBlacklistCorporations()));
    }

    public function testBlacklistCorporationRemove403()
    {
        $response = $this->runApp('PUT', '/api/user/watchlist/1/blacklist-corporation/remove/100');
        $this->assertEquals(403, $response->getStatusCode());

        $this->setupDb();
        $this->loginUser(7); # not role watchlist-manager
        $response = $this->runApp('PUT', '/api/user/watchlist/1/blacklist-corporation/remove/100');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testBlacklistCorporationRemove403Manager()
    {
        $this->setupDb();
        $this->loginUser(8); # role watchlist-manager

        $response = $this->runApp('PUT', '/api/user/watchlist/10/blacklist-corporation/remove/100');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testBlacklistCorporationRemove404()
    {
        $this->setupDb();
        $this->loginUser(8); # role watchlist-manager

        $response = $this->runApp('PUT', '/api/user/watchlist/1/blacklist-corporation/remove/100');
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testBlacklistCorporationRemove204()
    {
        $this->setupDb();
        $this->loginUser(8); # role watchlist-manager

        $response = $this->runApp(
            'PUT',
            '/api/user/watchlist/1/blacklist-corporation/remove/'.$this->corporation3->getId()
        );
        $this->assertEquals(204, $response->getStatusCode());

        $this->em->clear();
        $result = $this->repositoryFactory->getWatchlistRepository()->find(1);
        $this->assertSame(0, count($result->getBlacklistCorporations()));
    }

    public function testBlacklistAllianceList403()
    {
        $response = $this->runApp('GET', '/api/user/watchlist/1/blacklist-alliance/list');
        $this->assertEquals(403, $response->getStatusCode());

        $this->setupDb();
        $this->loginUser(7); # role watchlist, not group member
        $response = $this->runApp('GET', '/api/user/watchlist/1/blacklist-alliance/list');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testBlacklistAllianceList200()
    {
        $this->setupDb();
        $this->loginUser(9); # role watchlist with group

        $response = $this->runApp('GET', '/api/user/watchlist/1/blacklist-alliance/list');
        $this->assertEquals(200, $response->getStatusCode());

        $this->assertSame([[
            'id' => $this->alliance2->getId(),
            'name' => null,
            'ticker' => null,
        ]], $this->parseJsonBody($response));
    }

    public function testBlacklistAllianceAdd403()
    {
        $response = $this->runApp('PUT', '/api/user/watchlist/1/blacklist-alliance/add/100');
        $this->assertEquals(403, $response->getStatusCode());

        $this->setupDb();
        $this->loginUser(7); # not role watchlist-manager
        $response = $this->runApp('PUT', '/api/user/watchlist/1/blacklist-alliance/add/100');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testBlacklistAllianceAdd403Manager()
    {
        $this->setupDb();
        $this->loginUser(8); # role watchlist-manager

        $response = $this->runApp('PUT', '/api/user/watchlist/10/blacklist-alliance/add/100');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testBlacklistAllianceAdd404()
    {
        $this->setupDb();
        $this->loginUser(8); # role watchlist-manager

        $response = $this->runApp('PUT', '/api/user/watchlist/1/blacklist-alliance/add/100');
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testBlacklistAllianceAdd204()
    {
        $this->setupDb();
        $this->loginUser(8); # role watchlist-manager

        $response1 = $this->runApp('PUT', '/api/user/watchlist/1/blacklist-alliance/add/'.$this->alliance1->getId());
        $response2 = $this->runApp('PUT', '/api/user/watchlist/1/blacklist-alliance/add/'.$this->alliance1->getId());
        $this->assertEquals(204, $response1->getStatusCode());
        $this->assertEquals(204, $response2->getStatusCode());

        $this->em->clear();
        $result = $this->repositoryFactory->getWatchlistRepository()->find(1);
        $this->assertSame(2, count($result->getBlacklistAlliances()));
    }

    public function testBlacklistAllianceRemove403()
    {
        $response = $this->runApp('PUT', '/api/user/watchlist/1/blacklist-alliance/remove/100');
        $this->assertEquals(403, $response->getStatusCode());

        $this->setupDb();
        $this->loginUser(7); # not role watchlist-manager
        $response = $this->runApp('PUT', '/api/user/watchlist/1/blacklist-alliance/remove/100');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testBlacklistAllianceRemove403Manager()
    {
        $this->setupDb();
        $this->loginUser(8); # role watchlist-manager

        $response = $this->runApp('PUT', '/api/user/watchlist/10/blacklist-alliance/remove/100');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testBlacklistAllianceRemove404()
    {
        $this->setupDb();
        $this->loginUser(8); # role watchlist-manager

        $response = $this->runApp('PUT', '/api/user/watchlist/1/blacklist-alliance/remove/100');
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testBlacklistAllianceRemove204()
    {
        $this->setupDb();
        $this->loginUser(8); # role watchlist-manager

        $response = $this->runApp('PUT', '/api/user/watchlist/1/blacklist-alliance/remove/'.$this->alliance2->getId());
        $this->assertEquals(204, $response->getStatusCode());

        $this->em->clear();
        $result = $this->repositoryFactory->getWatchlistRepository()->find(1);
        $this->assertSame(0, count($result->getBlacklistAlliances()));
    }

    public function testWhitelistCorporationList403()
    {
        $response = $this->runApp('GET', '/api/user/watchlist/1/whitelist-corporation/list');
        $this->assertEquals(403, $response->getStatusCode());

        $this->setupDb();
        $this->loginUser(7); # role watchlist, not group member
        $response = $this->runApp('GET', '/api/user/watchlist/1/whitelist-corporation/list');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testWhitelistCorporationList200()
    {
        $this->setupDb();
        $this->loginUser(9); # role watchlist with group

        $response = $this->runApp('GET', '/api/user/watchlist/1/whitelist-corporation/list');
        $this->assertEquals(200, $response->getStatusCode());

        $this->assertSame([[
            'id' => $this->corporation1->getId(),
            'name' => null,
            'ticker' => null,
            'alliance' => null,
            'autoWhitelist' => false,
        ]], $this->parseJsonBody($response));
    }

    public function testWhitelistCorporationAdd403()
    {
        $response = $this->runApp('PUT', '/api/user/watchlist/1/whitelist-corporation/add/100');
        $this->assertEquals(403, $response->getStatusCode());

        $this->setupDb();
        $this->loginUser(7); # not role watchlist-manager
        $response = $this->runApp('PUT', '/api/user/watchlist/1/whitelist-corporation/add/100');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testWhitelistCorporationAdd403Manager()
    {
        $this->setupDb();
        $this->loginUser(8); # role watchlist-manager

        $response = $this->runApp('PUT', '/api/user/watchlist/10/whitelist-corporation/add/100');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testWhitelistCorporationAdd404()
    {
        $this->setupDb();
        $this->loginUser(8); # role watchlist-manager

        $response = $this->runApp('PUT', '/api/user/watchlist/1/whitelist-corporation/add/100');
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testWhitelistCorporationAdd204()
    {
        $this->setupDb();
        $this->loginUser(8); # role watchlist-manager

        $response1 = $this->runApp(
            'PUT',
            '/api/user/watchlist/1/whitelist-corporation/add/'.$this->corporation2->getId()
        );
        $response2 = $this->runApp(
            'PUT',
            '/api/user/watchlist/1/whitelist-corporation/add/'.$this->corporation2->getId()
        );
        $this->assertEquals(204, $response1->getStatusCode());
        $this->assertEquals(204, $response2->getStatusCode());

        $this->em->clear();
        $result = $this->repositoryFactory->getWatchlistRepository()->find(1);
        $this->assertSame(2, count($result->getWhitelistCorporations()));
    }

    public function testWhitelistCorporationRemove403()
    {
        $response = $this->runApp('PUT', '/api/user/watchlist/1/whitelist-corporation/remove/100');
        $this->assertEquals(403, $response->getStatusCode());

        $this->setupDb();
        $this->loginUser(7); # not role watchlist-manager
        $response = $this->runApp('PUT', '/api/user/watchlist/1/whitelist-corporation/remove/100');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testWhitelistCorporationRemove403Manager()
    {
        $this->setupDb();
        $this->loginUser(8); # role watchlist-manager

        $response = $this->runApp('PUT', '/api/user/watchlist/10/whitelist-corporation/remove/100');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testWhitelistCorporationRemove404()
    {
        $this->setupDb();
        $this->loginUser(8); # role watchlist-manager

        $response = $this->runApp('PUT', '/api/user/watchlist/1/whitelist-corporation/remove/100');
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testWhitelistCorporationRemove204()
    {
        $this->setupDb();
        $this->loginUser(8); # role watchlist-manager

        $response = $this->runApp(
            'PUT',
            '/api/user/watchlist/1/whitelist-corporation/remove/'.$this->corporation1->getId()
        );
        $this->assertEquals(204, $response->getStatusCode());

        $this->em->clear();
        $result = $this->repositoryFactory->getWatchlistRepository()->find(1);
        $this->assertSame(0, count($result->getWhitelistCorporations()));
    }

    public function testWhitelistAllianceList403()
    {
        $response = $this->runApp('GET', '/api/user/watchlist/1/whitelist-alliance/list');
        $this->assertEquals(403, $response->getStatusCode());

        $this->setupDb();
        $this->loginUser(7); # role watchlist, not group member
        $response = $this->runApp('GET', '/api/user/watchlist/1/whitelist-alliance/list');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testWhitelistAllianceList200()
    {
        $this->setupDb();
        $this->loginUser(9); # role watchlist with group

        $response = $this->runApp('GET', '/api/user/watchlist/1/whitelist-alliance/list');
        $this->assertEquals(200, $response->getStatusCode());

        $this->assertSame([[
            'id' => $this->alliance1->getId(),
            'name' => null,
            'ticker' => null,
        ]], $this->parseJsonBody($response));
    }

    public function testWhitelistAllianceAdd403()
    {
        $response = $this->runApp('PUT', '/api/user/watchlist/1/whitelist-alliance/add/100');
        $this->assertEquals(403, $response->getStatusCode());

        $this->setupDb();
        $this->loginUser(7); # not role watchlist-manager
        $response = $this->runApp('PUT', '/api/user/watchlist/1/whitelist-alliance/add/100');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testWhitelistAllianceAdd403Manager()
    {
        $this->setupDb();
        $this->loginUser(8); # role watchlist-manager

        $response = $this->runApp('PUT', '/api/user/watchlist/10/whitelist-alliance/add/100');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testWhitelistAllianceAdd404()
    {
        $this->setupDb();
        $this->loginUser(8); # role watchlist-manager

        $response = $this->runApp('PUT', '/api/user/watchlist/1/whitelist-alliance/add/100');
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testWhitelistAllianceAdd204()
    {
        $this->setupDb();
        $this->loginUser(8); # role watchlist-manager

        $response1 = $this->runApp('PUT', '/api/user/watchlist/1/whitelist-alliance/add/'.$this->alliance2->getId());
        $response2 = $this->runApp('PUT', '/api/user/watchlist/1/whitelist-alliance/add/'.$this->alliance2->getId());
        $this->assertEquals(204, $response1->getStatusCode());
        $this->assertEquals(204, $response2->getStatusCode());

        $this->em->clear();
        $result = $this->repositoryFactory->getWatchlistRepository()->find(1);
        $this->assertSame(2, count($result->getWhitelistAlliances()));
    }

    public function testWhitelistAllianceRemove403()
    {
        $response = $this->runApp('PUT', '/api/user/watchlist/1/whitelist-alliance/remove/100');
        $this->assertEquals(403, $response->getStatusCode());

        $this->setupDb();
        $this->loginUser(7); # not role watchlist-manager
        $response = $this->runApp('PUT', '/api/user/watchlist/1/whitelist-alliance/remove/100');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testWhitelistAllianceRemove403Manager()
    {
        $this->setupDb();
        $this->loginUser(8); # role watchlist-manager

        $response = $this->runApp('PUT', '/api/user/watchlist/10/whitelist-alliance/remove/100');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testWhitelistAllianceRemove404()
    {
        $this->setupDb();
        $this->loginUser(8); # role watchlist-manager

        $response = $this->runApp('PUT', '/api/user/watchlist/1/whitelist-alliance/remove/100');
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testWhitelistAllianceRemove204()
    {
        $this->setupDb();
        $this->loginUser(8); # role watchlist-manager

        $response = $this->runApp('PUT', '/api/user/watchlist/1/whitelist-alliance/remove/'.$this->alliance1->getId());
        $this->assertEquals(204, $response->getStatusCode());

        $this->em->clear();
        $result = $this->repositoryFactory->getWatchlistRepository()->find(1);
        $this->assertSame(0, count($result->getWhitelistAlliances()));
    }

    private function setupDb()
    {
        $this->helper->emptyDb();

        $watchlist = (new Watchlist())->setId(1)->setName('ws1');
        $watchlist2 = (new Watchlist())->setId(2)->setName('ws2');

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
        $this->player2->getCharacters()[0]->setCorporation($this->corporation1); // red list
        $this->player2->getCharacters()[1]->setCorporation($this->corporation2); // not on white list
        $this->player3->getCharacters()[0]->setCorporation($this->corporation1);
        $this->player3->getCharacters()[1]->setCorporation($this->corporation2);
        $this->player3->getCharacters()[1]->setCorporation($this->corporation3); // blacklist

        $this->alliance1 = (new Alliance())->setId(201);
        $this->alliance2 = (new Alliance())->setId(202);

        $this->group1 = (new Group())->setName('g1');
        $this->group2 = (new Group())->setName('g2');

        $watchlist->addExemption($this->player1);
        $watchlist->addCorporation($this->corporation1);
        $watchlist->addAlliance($this->alliance1);
        $watchlist->addGroup($this->group1);
        $this->player3->addGroup($this->group1);
        $playerM->addGroup($this->group1);
        $watchlist->addBlacklistCorporation($this->corporation3);
        $watchlist->addBlacklistAlliance($this->alliance2);
        $watchlist->addWhitelistCorporation($this->corporation1);
        $watchlist->addWhitelistAlliance($this->alliance1);

        $this->em->persist($watchlist);
        $this->em->persist($watchlist2);
        $this->em->persist($this->corporation1);
        $this->em->persist($this->corporation2);
        $this->em->persist($this->corporation3);
        $this->em->persist($this->alliance1);
        $this->em->persist($this->alliance2);
        $this->em->persist($this->group1);
        $this->em->persist($this->group2);

        $this->em->flush();
    }
}
