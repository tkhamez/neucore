<?php
namespace Tests\Functional\Core\ApiUser;

use Tests\Functional\WebTestCase;
use Tests\Helper;
use Brave\Core\Roles;
use Brave\Core\Entity\GroupRepository;
use Brave\Core\Entity\Group;
use Brave\Core\Entity\PlayerRepository;

class GroupTest extends WebTestCase
{
    private $helper;

    private $em;

    private $gr;

    private $gid;

    private $pid;

    public function setUp()
    {
        $_SESSION = null;

        $this->helper = new Helper();
        $this->em = $this->helper->getEm();
        $this->gr = new GroupRepository($this->em);
    }

    public function testList403()
    {
        $response = $this->runApp('GET', '/api/user/group/list');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testList200()
    {
        $this->setupDb();
        $this->loginUser(8);

        $response = $this->runApp('GET', '/api/user/group/list');
        $this->assertEquals(200, $response->getStatusCode());

        $this->assertSame(
            [['id' => $this->gid, 'name' => 'group-one']],
            $this->parseJsonBody($response)
        );
    }

    public function testCreate403()
    {
        $response = $this->runApp('POST', '/api/user/group/create');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testCreate400()
    {
        $this->setupDb();
        $this->loginUser(8);

        $response = $this->runApp('POST', '/api/user/group/create');
        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testCreate200()
    {
        $this->setupDb();
        $this->loginUser(8);

        $response = $this->runApp('POST', '/api/user/group/create', ['name' => 'new-g']);
        $this->assertEquals(200, $response->getStatusCode());

        $ng = $this->gr->findOneBy(['name' => 'new-g']);
        $this->assertSame(
            ['id' => $ng->getId(), 'name' => 'new-g'],
            $this->parseJsonBody($response)
        );
    }

    public function testRename403()
    {
        $response = $this->runApp('PUT', '/api/user/group/66/rename');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testRename404()
    {
        $this->setupDb();
        $this->loginUser(8);

        $response = $this->runApp('PUT', '/api/user/group/'.($this->gid + 1).'/rename', ['name' => 'new-g']);
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testRename400()
    {
        $this->setupDb();
        $this->loginUser(8);

        $response = $this->runApp('PUT', '/api/user/group/'.$this->gid.'/rename', ['name' => ' ']);
        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testRename200()
    {
        $this->setupDb();
        $this->loginUser(8);

        $response = $this->runApp('PUT', '/api/user/group/'.$this->gid.'/rename', ['name' => 'new-name']);
        $this->assertEquals(200, $response->getStatusCode());

        $this->assertSame(
            ['id' => $this->gid, 'name' => 'new-name'],
            $this->parseJsonBody($response)
        );

        $renamed = $this->gr->findOneBy(['name' => 'new-name']);
        $this->assertInstanceOf(Group::class, $renamed);
    }

    public function testDelete403()
    {
        $response = $this->runApp('DELETE', '/api/user/group/66/delete');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testDelete404()
    {
        $this->setupDb();
        $this->loginUser(8);

        $response = $this->runApp('DELETE', '/api/user/group/'.($this->gid + 1).'/delete');
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testDelete204()
    {
        $this->setupDb();
        $this->loginUser(8);

        $response = $this->runApp('DELETE', '/api/user/group/'.$this->gid.'/delete');
        $this->assertEquals(204, $response->getStatusCode());

        $this->em->clear();

        $deleted = $this->gr->find($this->gid);
        $this->assertNull($deleted);
    }

    public function testAddManager403()
    {
        $response = $this->runApp('PUT', '/api/user/group/69/add-manager');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testAddManager404()
    {
        $this->setupDb();
        $this->loginUser(8);

        $response1 = $this->runApp('PUT', '/api/user/group/'.$this->gid.'/add-manager', ['player' => $this->pid + 1]);
        $response2 = $this->runApp('PUT', '/api/user/group/'.($this->gid + 1).'/add-manager', ['player' => $this->pid]);

        $this->assertEquals(404, $response1->getStatusCode());
        $this->assertEquals(404, $response2->getStatusCode());
    }

    public function testAddManager204()
    {
        $this->setupDb();
        $this->loginUser(8);

        $response = $this->runApp('PUT', '/api/user/group/'.$this->gid.'/add-manager', ['player' => $this->pid]);
        $this->assertEquals(204, $response->getStatusCode());

        $this->em->clear();

        $player = (new PlayerRepository($this->em))->find($this->pid);
        $actual = [];
        foreach ($player->getManagerGroups() as $mg) {
            $actual[] = $mg->getId();
        }
        $this->assertSame([$this->gid],$actual);
    }

    public function testRemoveManager403()
    {
        $response = $this->runApp('PUT', '/api/user/group/69/remove-manager');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testRemoveManager404()
    {
        $this->setupDb();
        $this->loginUser(8);

        $response = $this->runApp('PUT', '/api/user/group/'.($this->gid + 1).'/remove-manager', ['player' => $this->pid]);
        $response = $this->runApp('PUT', '/api/user/group/'.$this->gid.'/remove-manager', ['player' => ($this->pid + 1)]);
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testRemoveManager204()
    {
        $this->setupDb();
        $this->loginUser(8);

        $group = (new GroupRepository($this->em))->find($this->gid);
        $player = (new PlayerRepository($this->em))->find($this->pid);
        $group->addManager($player);
        $this->em->flush();
        $this->em->clear();

        $response = $this->runApp('PUT', '/api/user/group/'.$this->gid.'/remove-manager', ['player' => $this->pid]);
        $this->assertEquals(204, $response->getStatusCode());

        $player = (new PlayerRepository($this->em))->find($this->pid);
        $actual = [];
        foreach ($player->getManagerGroups() as $mg) {
            $actual[] = $mg->getId();
        }
        $this->assertSame([],$actual);
    }

    private function setupDb()
    {
        $this->helper->emptyDb();

        $g = $this->helper->addGroups(['group-one']);
        $this->gid = $g[0]->getId();

        $char = $this->helper->addCharacterMain('Admin', 8, [Roles::USER, Roles::GROUP_ADMIN], ['group-one']);
        $this->pid = $char->getPlayer()->getId();
    }
}
