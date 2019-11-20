<?php

declare(strict_types=1);

namespace Tests\Functional\Controller\User;

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
     * @var Corporation
     */
    private $corporation1;

    /**
     * @var Corporation
     */
    private $corporation2;

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
        $this->repositoryFactory = new RepositoryFactory($this->helper->getEm());
    }

    public function testPlayers403()
    {
        $response = $this->runApp('GET', '/api/user/watchlist/1/players');
        $this->assertEquals(403, $response->getStatusCode());

        $this->setupDb();
        $this->loginUser(6); # not role watchlist or watchlist-admin

        $response = $this->runApp('GET', '/api/user/watchlist/1/players');
        $this->assertEquals(403, $response->getStatusCode());

        $this->setupDb();
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

    public function testExemptionList403()
    {
        $response = $this->runApp('GET', '/api/user/watchlist/1/exemption/list');
        $this->assertEquals(403, $response->getStatusCode());

        $this->setupDb();
        $this->loginUser(6); # not role watchlist or watchlist-admin

        $response = $this->runApp('GET', '/api/user/watchlist/1/exemption/list');
        $this->assertEquals(403, $response->getStatusCode());

        $this->setupDb();
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
        $this->loginUser(7); # not role watchlist-admin

        $response = $this->runApp('PUT', '/api/user/watchlist/1/exemption/add/100');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testExemptionAdd404()
    {
        $this->setupDb();
        $this->loginUser(8); # role watchlist-admin

        $response = $this->runApp('PUT', '/api/user/watchlist/1/exemption/add/100');
        $this->assertEquals(404, $response->getStatusCode());

        $response = $this->runApp('PUT', '/api/user/watchlist/2/exemption/add/'.$this->player2->getId());
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testExemptionAdd204()
    {
        $this->setupDb();
        $this->loginUser(8); # role watchlist-admin

        $response1 = $this->runApp('PUT', '/api/user/watchlist/1/exemption/add/'.$this->player2->getId());
        $response2 = $this->runApp('PUT', '/api/user/watchlist/1/exemption/add/'.$this->player2->getId());
        $this->assertEquals(204, $response1->getStatusCode());
        $this->assertEquals(204, $response2->getStatusCode());

        $this->helper->getEm()->clear();
        $result = $this->repositoryFactory->getWatchlistRepository()->find(1);
        $this->assertSame(2, count($result->getExemptions()));
    }

    public function testExemptionRemove403()
    {
        $response = $this->runApp('PUT', '/api/user/watchlist/1/exemption/remove/100');
        $this->assertEquals(403, $response->getStatusCode());

        $this->setupDb();
        $this->loginUser(7); # not role watchlist-admin

        $response = $this->runApp('PUT', '/api/user/watchlist/1/exemption/remove/100');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testExemptionRemove404()
    {
        $this->setupDb();
        $this->loginUser(8); # role watchlist-admin

        $response = $this->runApp('PUT', '/api/user/watchlist/1/exemption/remove/100');
        $this->assertEquals(404, $response->getStatusCode());

        $response = $this->runApp('PUT', '/api/user/watchlist/2/exemption/remove/'.$this->player1->getId());
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testExemptionRemove204()
    {
        $this->setupDb();
        $this->loginUser(8); # role watchlist-admin

        $response = $this->runApp('PUT', '/api/user/watchlist/1/exemption/remove/'.$this->player1->getId());
        $this->assertEquals(204, $response->getStatusCode());

        $this->helper->getEm()->clear();
        $result = $this->repositoryFactory->getWatchlistRepository()->find(1);
        $this->assertSame(0, count($result->getExemptions()));
    }

    public function testCorporationList403()
    {
        $response = $this->runApp('GET', '/api/user/watchlist/1/corporation/list');
        $this->assertEquals(403, $response->getStatusCode());

        $this->setupDb();
        $this->loginUser(6); # not role watchlist-admin

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
        $this->loginUser(7); # not role watchlist-admin

        $response = $this->runApp('PUT', '/api/user/watchlist/1/corporation/add/100');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testCorporationAdd404()
    {
        $this->setupDb();
        $this->loginUser(8); # role watchlist-admin

        $response = $this->runApp('PUT', '/api/user/watchlist/1/corporation/add/100');
        $this->assertEquals(404, $response->getStatusCode());

        $response = $this->runApp('PUT', '/api/user/watchlist/2/corporation/add/'.$this->corporation2->getId());
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testCorporationAdd204()
    {
        $this->setupDb();
        $this->loginUser(8); # role watchlist-admin

        $response1 = $this->runApp('PUT', '/api/user/watchlist/1/corporation/add/'.$this->corporation2->getId());
        $response2 = $this->runApp('PUT', '/api/user/watchlist/1/corporation/add/'.$this->corporation2->getId());
        $this->assertEquals(204, $response1->getStatusCode());
        $this->assertEquals(204, $response2->getStatusCode());

        $this->helper->getEm()->clear();
        $result = $this->repositoryFactory->getWatchlistRepository()->find(1);
        $this->assertSame(2, count($result->getCorporations()));
    }

    public function testCorporationRemove403()
    {
        $response = $this->runApp('PUT', '/api/user/watchlist/1/corporation/remove/100');
        $this->assertEquals(403, $response->getStatusCode());

        $this->setupDb();
        $this->loginUser(7); # not role watchlist-admin

        $response = $this->runApp('PUT', '/api/user/watchlist/1/corporation/remove/100');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testCorporationRemove404()
    {
        $this->setupDb();
        $this->loginUser(8); # role watchlist-admin

        $response = $this->runApp('PUT', '/api/user/watchlist/1/corporation/remove/100');
        $this->assertEquals(404, $response->getStatusCode());

        $response = $this->runApp('PUT', '/api/user/watchlist/2/corporation/remove/'.$this->corporation1->getId());
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testCorporationRemove204()
    {
        $this->setupDb();
        $this->loginUser(8); # role watchlist-admin

        $response = $this->runApp('PUT', '/api/user/watchlist/1/corporation/remove/'.$this->corporation1->getId());
        $this->assertEquals(204, $response->getStatusCode());

        $this->helper->getEm()->clear();
        $result = $this->repositoryFactory->getWatchlistRepository()->find(1);
        $this->assertSame(0, count($result->getCorporations()));
    }

    public function testAllianceList403()
    {
        $response = $this->runApp('GET', '/api/user/watchlist/1/alliance/list');
        $this->assertEquals(403, $response->getStatusCode());

        $this->setupDb();
        $this->loginUser(6); # not role watchlist-admin

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
        $this->loginUser(7); # not role watchlist-admin

        $response = $this->runApp('PUT', '/api/user/watchlist/1/alliance/add/100');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testAllianceAdd404()
    {
        $this->setupDb();
        $this->loginUser(8); # role watchlist-admin

        $response = $this->runApp('PUT', '/api/user/watchlist/1/alliance/add/100');
        $this->assertEquals(404, $response->getStatusCode());

        $response = $this->runApp('PUT', '/api/user/watchlist/2/alliance/add/'.$this->alliance2->getId());
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testAllianceAdd204()
    {
        $this->setupDb();
        $this->loginUser(8); # role watchlist-admin

        $response1 = $this->runApp('PUT', '/api/user/watchlist/1/alliance/add/'.$this->alliance2->getId());
        $response2 = $this->runApp('PUT', '/api/user/watchlist/1/alliance/add/'.$this->alliance2->getId());
        $this->assertEquals(204, $response1->getStatusCode());
        $this->assertEquals(204, $response2->getStatusCode());

        $this->helper->getEm()->clear();
        $result = $this->repositoryFactory->getWatchlistRepository()->find(1);
        $this->assertSame(2, count($result->getAlliances()));
    }

    public function testAllianceRemove403()
    {
        $response = $this->runApp('PUT', '/api/user/watchlist/1/alliance/remove/100');
        $this->assertEquals(403, $response->getStatusCode());

        $this->setupDb();
        $this->loginUser(7); # not role watchlist-admin

        $response = $this->runApp('PUT', '/api/user/watchlist/1/alliance/remove/100');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testAllianceRemove404()
    {
        $this->setupDb();
        $this->loginUser(8); # role watchlist-admin

        $response = $this->runApp('PUT', '/api/user/watchlist/1/alliance/remove/100');
        $this->assertEquals(404, $response->getStatusCode());

        $response = $this->runApp('PUT', '/api/user/watchlist/2/alliance/remove/'.$this->alliance1->getId());
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testAllianceRemove204()
    {
        $this->setupDb();
        $this->loginUser(8); # role watchlist-admin

        $response = $this->runApp('PUT', '/api/user/watchlist/1/alliance/remove/'.$this->alliance1->getId());
        $this->assertEquals(204, $response->getStatusCode());

        $this->helper->getEm()->clear();
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

        # TODO test group permission
    }

    public function testGroupList200()
    {
        $this->setupDb();
        $this->loginUser(8); # role watchlist-admin

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
        $this->loginUser(8); # role watchlist-admin

        $response = $this->runApp('PUT', '/api/user/watchlist/1/group/add/100');
        $this->assertEquals(404, $response->getStatusCode());

        $response = $this->runApp('PUT', '/api/user/watchlist/2/group/add/'.$this->group2->getId());
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testGroupAdd204()
    {
        $this->setupDb();
        $this->loginUser(8); # role watchlist-admin

        $response1 = $this->runApp('PUT', '/api/user/watchlist/1/group/add/'.$this->group2->getId());
        $response2 = $this->runApp('PUT', '/api/user/watchlist/1/group/add/'.$this->group2->getId());
        $this->assertEquals(204, $response1->getStatusCode());
        $this->assertEquals(204, $response2->getStatusCode());

        $this->helper->getEm()->clear();
        $result = $this->repositoryFactory->getWatchlistRepository()->find(1);
        $this->assertSame(2, count($result->getGroups()));
    }

    public function testGroupRemove403()
    {
        $response = $this->runApp('PUT', '/api/user/watchlist/1/group/remove/100');
        $this->assertEquals(403, $response->getStatusCode());

        $this->setupDb();
        $this->loginUser(7); # not role watchlist-admin

        $response = $this->runApp('PUT', '/api/user/watchlist/1/group/remove/100');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testGroupRemove404()
    {
        $this->setupDb();
        $this->loginUser(8); # role watchlist-admin

        $response = $this->runApp('PUT', '/api/user/watchlist/1/group/remove/100');
        $this->assertEquals(404, $response->getStatusCode());

        $response = $this->runApp('PUT', '/api/user/watchlist/2/group/remove/'.$this->group1->getId());
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testGroupRemove204()
    {
        $this->setupDb();
        $this->loginUser(8); # role watchlist-admin

        $response = $this->runApp('PUT', '/api/user/watchlist/1/group/remove/'.$this->group1->getId());
        $this->assertEquals(204, $response->getStatusCode());

        $this->helper->getEm()->clear();
        $result = $this->repositoryFactory->getWatchlistRepository()->find(1);
        $this->assertSame(0, count($result->getGroups()));
    }

    private function setupDb()
    {
        $this->helper->emptyDb();

        $watchlist = (new Watchlist())->setId(1)->setName('ws1');

        $this->player1 = $this->helper->addCharacterMain('User', 6, [Role::USER])->getPlayer();
        $this->player2 = $this->helper->addCharacterMain('Read', 7, [Role::WATCHLIST])->getPlayer();
        $this->helper->addCharacterMain('Admin', 8, [Role::WATCHLIST_ADMIN]);
        $player3 = $this->helper->addCharacterMain('Admin', 9, [Role::WATCHLIST])->getPlayer();

        $this->corporation1 = (new Corporation())->setId(98000101);
        $this->corporation2 = (new Corporation())->setId(98000102);

        // setup for "watchlist" testPlayers200
        $this->helper->addCharacterToPlayer('c2b', 20, $this->player2);
        $this->player2->getCharacters()[0]->setCorporation($this->corporation1);
        $this->player2->getCharacters()[1]->setCorporation($this->corporation2);

        $this->alliance1 = (new Alliance())->setId(201);
        $this->alliance2 = (new Alliance())->setId(202);

        $this->group1 = (new Group())->setName('g1');
        $this->group2 = (new Group())->setName('g2');

        $watchlist->addExemption($this->player1);
        $watchlist->addCorporation($this->corporation1);
        $watchlist->addAlliance($this->alliance1);
        $watchlist->addGroup($this->group1);
        $player3->addGroup($this->group1);

        $this->helper->getEm()->persist($watchlist);
        $this->helper->getEm()->persist($this->corporation1);
        $this->helper->getEm()->persist($this->corporation2);
        $this->helper->getEm()->persist($this->alliance1);
        $this->helper->getEm()->persist($this->alliance2);
        $this->helper->getEm()->persist($this->group1);
        $this->helper->getEm()->persist($this->group2);

        $this->helper->getEm()->flush();
    }
}
