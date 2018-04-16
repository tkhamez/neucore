<?php
namespace Tests\Functional\Core\Api\User;

use Tests\Functional\WebTestCase;
use Tests\Helper;
use Brave\Core\Roles;
use Brave\Core\Entity\GroupRepository;
use Brave\Core\Entity\Group;
use Brave\Core\Entity\PlayerRepository;
use Brave\Core\Entity\Player;
use Brave\Core\Entity\AppRepository;
use Brave\Core\Entity\App;

class ApplicationTest extends WebTestCase
{
    private $helper;

    private $em;

    private $ar;

    private $gr;

    private $gid;

    private $aid;

    private $pid;

    private $pid2;

    private $pid3;

    public function setUp()
    {
        $_SESSION = null;

        $this->helper = new Helper();
        $this->em = $this->helper->getEm();
        $this->ar = new AppRepository($this->em);
        $this->gr = new GroupRepository($this->em);
    }

    public function testAll403()
    {
        $response = $this->runApp('GET', '/api/user/application/all');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testAll200()
    {
        $this->setupDb();
        $this->loginUser(8);

        $response = $this->runApp('GET', '/api/user/application/all');
        $this->assertEquals(200, $response->getStatusCode());

        $this->assertSame(
            [['id' => $this->aid, 'name' => 'app one']],
            $this->parseJsonBody($response)
        );
    }

    public function testCreate403()
    {
        $response = $this->runApp('POST', '/api/user/application/create');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testCreate400()
    {
        $this->setupDb();
        $this->loginUser(8);

        $response1 = $this->runApp('POST', '/api/user/application/create');
        $this->assertEquals(400, $response1->getStatusCode());

        $response2 = $this->runApp('POST', '/api/user/application/create', ['name' => '']);
        $this->assertEquals(400, $response2->getStatusCode());
    }

    public function testCreate200()
    {
        $this->setupDb();
        $this->loginUser(8);

        $response = $this->runApp('POST', '/api/user/application/create', ['name' => "new\napp"]);
        $this->assertEquals(200, $response->getStatusCode());

        $na = $this->ar->findOneBy(['name' => 'new app']);
        $this->assertSame(
            ['id' => $na->getId(), 'name' => 'new app'],
            $this->parseJsonBody($response)
        );

        $this->assertSame(60, strlen($na->getSecret())); // the hash (blowfish) is 60 chars atm, may change.
    }

    public function testRename403()
    {
        $response = $this->runApp('PUT', '/api/user/application/55/rename');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testRename404()
    {
        $this->setupDb();
        $this->loginUser(8);

        $response = $this->runApp('PUT', '/api/user/application/'.($this->aid + 1).'/rename', ['name' => "n\n a n"]);
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testRename400()
    {
        $this->setupDb();
        $this->loginUser(8);

        $response = $this->runApp('PUT', '/api/user/application/'.$this->aid.'/rename', ['name' => '']);
        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testRename200()
    {
        $this->setupDb();
        $this->loginUser(8);

        $response1 = $this->runApp('PUT', '/api/user/application/'.$this->aid.'/rename', ['name' => "n\n a n"]);
        $response2 = $this->runApp('PUT', '/api/user/application/'.$this->aid.'/rename', ['name' => 'new name']);
        $this->assertEquals(200, $response1->getStatusCode());
        $this->assertEquals(200, $response2->getStatusCode());

        $this->assertSame(
            ['id' => $this->aid, 'name' => 'n  a n'],
            $this->parseJsonBody($response1)
        );

        $this->assertSame(
            ['id' => $this->aid, 'name' => 'new name'],
            $this->parseJsonBody($response2)
        );

        $renamed = $this->ar->findOneBy(['name' => 'new name']);
        $this->assertInstanceOf(App::class, $renamed);
    }

    public function testDelete403()
    {
        $response = $this->runApp('DELETE', '/api/user/application/55/delete');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testDelete404()
    {
        $this->setupDb();
        $this->loginUser(8);

        $response = $this->runApp('DELETE', '/api/user/application/'.($this->aid + 1).'/delete');
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testDelete204()
    {
        $this->setupDb();
        $this->loginUser(8);

        $response = $this->runApp('DELETE', '/api/user/application/'.$this->aid.'/delete');
        $this->assertEquals(204, $response->getStatusCode());

        $this->em->clear();

        $deleted = $this->ar->find($this->aid);
        $this->assertNull($deleted);
    }

    public function testManagers403()
    {
        $response = $this->runApp('GET', '/api/user/application/1/managers');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testManagers404()
    {
        $this->setupDb();
        $this->loginUser(8);

        $response = $this->runApp('GET', '/api/user/application/'.($this->aid + 1).'/managers');
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testManagers200()
    {
        $this->setupDb();
        $this->loginUser(8);

        $response = $this->runApp('GET', '/api/user/application/'.$this->aid.'/managers');
        $this->assertEquals(200, $response->getStatusCode());

        $this->assertSame(
            [['id' => $this->pid3, 'name' => 'Manager']],
            $this->parseJsonBody($response)
        );
    }

    public function testAddManager403()
    {
        $response = $this->runApp('PUT', '/api/user/application/59/add-manager/1');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testAddManager404()
    {
        $this->setupDb();
        $this->loginUser(8);

        $response1 = $this->runApp('PUT', '/api/user/application/'.$this->aid.'/add-manager/'.($this->pid3 + 1));
        $response2 = $this->runApp('PUT', '/api/user/application/'.($this->aid + 1).'/add-manager/'.$this->pid3);

        $this->assertEquals(404, $response1->getStatusCode());
        $this->assertEquals(404, $response2->getStatusCode());
    }

    public function testAddManager204()
    {
        $this->setupDb();
        $this->loginUser(8);

        $player = new Player();
        $player->setName('Manager2');
        $this->em->persist($player);
        $this->em->flush();

        $response1 = $this->runApp('PUT', '/api/user/application/'.$this->aid.'/add-manager/'.$this->pid3);
        $response2 = $this->runApp('PUT', '/api/user/application/'.$this->aid.'/add-manager/'.$player->getId());
        $this->assertEquals(204, $response1->getStatusCode());
        $this->assertEquals(204, $response2->getStatusCode());

        $this->em->clear();

        $actual = [];
        $app = $this->ar->find($this->aid);
        foreach ($app->getManagers() as $mg) {
            $actual[] = $mg->getId();
        }
        $this->assertSame([$this->pid3, $player->getId()], $actual);
    }

    public function testRemoveManager403()
    {
        $response = $this->runApp('PUT', '/api/user/application/59/remove-manager/1');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testRemoveManager404()
    {
        $this->setupDb();
        $this->loginUser(8);

        $response = $this->runApp('PUT', '/api/user/application/'.($this->aid + 1).'/remove-manager/'.$this->pid3);
        $response = $this->runApp('PUT', '/api/user/application/'.$this->aid.'/remove-manager/'.($this->pid3 + 1));
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testRemoveManager204()
    {
        $this->setupDb();
        $this->loginUser(8);

        $response = $this->runApp('PUT', '/api/user/application/'.$this->aid.'/remove-manager/'.$this->pid3);
        $this->assertEquals(204, $response->getStatusCode());

        $player = (new PlayerRepository($this->em))->find($this->pid3);
        $actual = [];
        foreach ($player->getManagerGroups() as $mg) {
            $actual[] = $mg->getId();
        }
        $this->assertSame([], $actual);
    }

    public function testGroups403()
    {
        $response = $this->runApp('GET', '/api/user/application/1/groups');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testGroups404()
    {
        $this->setupDb();
        $this->loginUser(8);

        $response = $this->runApp('GET', '/api/user/application/'.($this->aid + 1).'/groups');
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testGroups200()
    {
        $this->setupDb();
        $this->loginUser(8);

        $response = $this->runApp('GET', '/api/user/application/'.$this->aid.'/groups');
        $this->assertEquals(200, $response->getStatusCode());

        $this->assertSame(
            [['id' => $this->gid, 'name' => 'group-one']],
            $this->parseJsonBody($response)
        );
    }

    public function testAddGroup403()
    {
        $response = $this->runApp('PUT', '/api/user/application/59/add-group/1');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testAddGroup404()
    {
        $this->setupDb();
        $this->loginUser(8);

        $response1 = $this->runApp('PUT', '/api/user/application/'.$this->aid.'/add-group/'.($this->gid + 1));
        $response2 = $this->runApp('PUT', '/api/user/application/'.($this->aid + 1).'/add-group/'.$this->gid);

        $this->assertEquals(404, $response1->getStatusCode());
        $this->assertEquals(404, $response2->getStatusCode());
    }

    public function testAddGroup204()
    {
        $this->setupDb();
        $this->loginUser(8);

        $group = new Group();
        $group->setName('Group1');
        $this->em->persist($group);
        $this->em->flush();

        $response1 = $this->runApp('PUT', '/api/user/application/'.$this->aid.'/add-group/'.$this->gid);
        $response2 = $this->runApp('PUT', '/api/user/application/'.$this->aid.'/add-group/'.$group->getId());
        $this->assertEquals(204, $response1->getStatusCode());
        $this->assertEquals(204, $response2->getStatusCode());

        $this->em->clear();

        $actual = [];
        $app = $this->ar->find($this->aid);
        foreach ($app->getGroups() as $gp) {
            $actual[] = $gp->getId();
        }
        $this->assertSame([$this->gid, $group->getId()], $actual);
    }

    public function testRemoveGroup403()
    {
        $response = $this->runApp('PUT', '/api/user/application/59/remove-group/1');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testRemoveGroup404()
    {
        $this->setupDb();
        $this->loginUser(8);

        $response = $this->runApp('PUT', '/api/user/application/'.($this->aid + 1).'/remove-group/'.$this->gid);
        $response = $this->runApp('PUT', '/api/user/application/'.$this->aid.'/remove-group/'.($this->gid + 1));
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testRemoveGroup204()
    {
        $this->setupDb();
        $this->loginUser(8);

        $response = $this->runApp('PUT', '/api/user/application/'.$this->aid.'/remove-group/'.$this->gid);
        $this->assertEquals(204, $response->getStatusCode());

        $group = $this->gr->find($this->gid);
        $actual = [];
        foreach ($group->getApps() as $a) {
            $actual[] = $a->getId();
        }
        $this->assertSame([], $actual);
    }

    public function testChangeSecret403()
    {
        $response = $this->runApp('PUT', '/api/user/application/59/change-secret');
        $this->assertEquals(403, $response->getStatusCode());

        $this->setupDb();

        $this->loginUser(8); // no manager
        $response = $this->runApp('PUT', '/api/user/application/'.($this->aid + 1).'/change-secret');
        $this->assertEquals(403, $response->getStatusCode());

        $this->loginUser(9); // manager, but not of this app
        $response = $this->runApp('PUT', '/api/user/application/'.$this->aid.'/change-secret');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testChangeSecret404()
    {
        $this->setupDb();
        $this->loginUser(10);

        $response = $this->runApp('PUT', '/api/user/application/'.($this->aid + 1).'/change-secret');
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testChangeSecret200()
    {
        $this->setupDb();
        $this->loginUser(10);

        $response = $this->runApp('PUT', '/api/user/application/'.$this->aid.'/change-secret');
        $this->assertEquals(200, $response->getStatusCode());

        $this->assertSame(64, strlen($this->parseJsonBody($response)));
    }

    private function setupDb()
    {
        $this->helper->emptyDb();

        $g = $this->helper->addGroups(['group-one']);
        $this->gid = $g[0]->getId();

        $a = new App();
        $a->setName('app one');
        $a->setSecret(password_hash('abc123', PASSWORD_DEFAULT));
        $this->em->persist($a);

        $char = $this->helper->addCharacterMain('Admin', 8, [Roles::USER, Roles::APP_ADMIN]);
        $char2 = $this->helper->addCharacterMain('Manager', 9, [Roles::USER, Roles::APP_MANAGER]);
        $char3 = $this->helper->addCharacterMain('Manager', 10, [Roles::USER, Roles::APP_MANAGER]);
        $this->pid = $char->getPlayer()->getId();
        $this->pid2 = $char2->getPlayer()->getId();
        $this->pid3 = $char3->getPlayer()->getId();

        $a->addManager($char3->getPlayer());
        $a->addGroup($g[0]);

        $this->em->flush();

        $this->aid = $a->getId();
    }
}
