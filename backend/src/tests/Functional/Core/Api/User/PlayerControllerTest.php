<?php declare(strict_types=1);

namespace Tests\Functional\Core\Api\User;

use Brave\Core\Entity\Alliance;
use Brave\Core\Entity\Corporation;
use Brave\Core\Entity\Group;
use Brave\Core\Entity\PlayerRepository;
use Brave\Core\Roles;
use Doctrine\ORM\EntityManagerInterface;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Tests\Functional\WebTestCase;
use Tests\Helper;
use Tests\WriteErrorListener;

class PlayerControllerTest extends WebTestCase
{
    private $h;

    private $em;

    private $userId;

    private $managerId;

    private $player;

    private $group;

    private $gPrivId;

    private $pr;

    private $log;

    public function setUp()
    {
        $_SESSION = null;

        $this->h = new Helper();
        $this->em = $this->h->getEm();
        $this->pr = new PlayerRepository($this->em);

        $this->log = new Logger('test');
        $this->log->pushHandler(new TestHandler());
    }

    public function testShow403()
    {
        $response = $this->runApp('GET', '/api/user/player/show');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testShow200()
    {
        $this->h->emptyDb();
        $groups = $this->h->addGroups(['group1', 'another-group']);
        $char = $this->h->addCharacterMain(
            'TUser', 123456, [Roles::USER, Roles::USER_ADMIN], ['group1', 'another-group']);
        $alli = (new Alliance())->setId(123)->setName('alli1')->setTicker('ATT');
        $corp = (new Corporation())->setId(456)->setName('corp1')->setTicker('MT')->setAlliance($alli);
        $char->setCorporation($corp);
        $this->h->getEm()->persist($alli);
        $this->h->getEm()->persist($corp);
        $this->h->getEm()->flush();
        $this->loginUser(123456);

        $response = $this->runApp('GET', '/api/user/player/show');
        $this->assertEquals(200, $response->getStatusCode());

        $this->assertSame([
            'id' => $char->getPlayer()->getId(),
            'name' => 'TUser',
            'roles' => [Roles::USER, Roles::USER_ADMIN],
            'characters' => [
                [
                    'id' => 123456,
                    'name' => 'TUser',
                    'main' => true,
                    'lastUpdate' => null,
                    'validToken' => false,
                    'corporation' => [
                        'id' => 456, 'name' => 'corp1', 'ticker' => 'MT', 'alliance' => [
                            'id' => 123, 'name' => 'alli1', 'ticker' => 'ATT'
                        ]
                    ]
                ],
            ],
            'applications' => [],
            'groups' => [
                ['id' => $groups[1]->getId(), 'name' => 'another-group', 'visibility' => Group::VISIBILITY_PRIVATE],
                ['id' => $groups[0]->getId(), 'name' => 'group1', 'visibility' => Group::VISIBILITY_PRIVATE]
            ],
            'managerGroups' => [],
            'managerApps' => [],
        ], $this->parseJsonBody($response));
    }

    public function testAddApplication403()
    {
        $response = $this->runApp('PUT', '/api/user/player/add-application/11');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testAddApplication404()
    {
        $this->setupDb();
        $this->loginUser(12);

        $response = $this->runApp('PUT', '/api/user/player/add-application/' . ($this->group->getId() + 5));
        $this->assertEquals(404, $response->getStatusCode());

        $response = $this->runApp('PUT', '/api/user/player/add-application/' . $this->gPrivId);
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testAddApplication204()
    {
        $this->setupDb();
        $this->loginUser(12);

        $response1 = $this->runApp('PUT', '/api/user/player/add-application/'. $this->group->getId());
        $response2 = $this->runApp('PUT', '/api/user/player/add-application/'. $this->group->getId());
        $this->assertEquals(204, $response1->getStatusCode());
        $this->assertEquals(204, $response2->getStatusCode());

        $this->em->clear();
        $p = $this->pr->find($this->player->getId());
        $this->assertSame(1, count($p->getApplications()));
    }

    public function testRemoveApplication403()
    {
        $response = $this->runApp('PUT', '/api/user/player/remove-application/11');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testRemoveApplication404()
    {
        $this->setupDb();
        $this->loginUser(12);

        $response = $this->runApp('PUT', '/api/user/player/remove-application/' . ($this->group->getId() + 5));
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testRemoveApplication204()
    {
        $this->setupDb();
        $this->loginUser(12);

        $this->player->addApplication($this->group);
        $this->em->flush();

        $response = $this->runApp('PUT', '/api/user/player/remove-application/' . $this->group->getId());
        $this->assertEquals(204, $response->getStatusCode());

        $this->em->clear();
        $p = $this->pr->find($this->player->getId());
        $this->assertSame(0, count($p->getApplications()));
    }

    public function testLeaveGroup403()
    {
        $response = $this->runApp('PUT', '/api/user/player/leave-group/11');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testLeaveGroup404()
    {
        $this->setupDb();
        $this->loginUser(12);

        $response = $this->runApp('PUT', '/api/user/player/leave-group/' . ($this->group->getId() + 5));
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testLeaveGroup204()
    {
        $this->setupDb();
        $this->loginUser(12);

        $this->player->addGroup($this->group);
        $this->em->flush();

        $response = $this->runApp('PUT', '/api/user/player/leave-group/' . $this->group->getId());
        $this->assertEquals(204, $response->getStatusCode());

        $this->em->clear();
        $p = $this->pr->find($this->player->getId());
        $this->assertSame(0, count($p->getGroups()));
    }

    public function testSetMain403()
    {
        $response = $this->runApp('PUT', '/api/user/player/set-main/123456');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testSetMain404()
    {
        $this->setupDb();
        $this->loginUser(12);

        $response = $this->runApp('PUT', '/api/user/player/set-main/123456');
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testSetMain200()
    {
        $this->setupDb();
        $this->loginUser(12);

        $charsBefore = $this->pr->find($this->player->getId())->getCharacters();
        $this->assertSame(12, $charsBefore[0]->getId());
        $this->assertSame(13, $charsBefore[1]->getId());
        $this->assertTrue($charsBefore[0]->getMain());
        $this->assertFalse($charsBefore[1]->getMain());
        $this->assertSame('Alt', $charsBefore[1]->getName());
        $this->assertSame('Admin', $this->player->getName());

        $response = $this->runApp('PUT', '/api/user/player/set-main/13');
        $this->assertEquals(200, $response->getStatusCode());

        $this->assertSame(
            ['id' => 13,
                'name' => 'Alt',
                'main' => true,
                'lastUpdate' => null,
                'validToken' => false,
                'corporation' => null
            ],
            $this->parseJsonBody($response)
        );

        $this->em->clear();

        $playerAfter = $this->pr->find($this->player->getId());
        $charsAfter = $playerAfter->getCharacters();
        $this->assertSame(12, $charsAfter[0]->getId());
        $this->assertSame(13, $charsAfter[1]->getId());
        $this->assertFalse($charsAfter[0]->getMain());
        $this->assertTrue($charsAfter[1]->getMain());
        $this->assertSame('Alt', $playerAfter->getName());
    }

    public function testAll403()
    {
        $response = $this->runApp('GET', '/api/user/player/all');
        $this->assertEquals(403, $response->getStatusCode());

        $this->setupDb();
        $this->loginUser(10); // not user-admin, group-admin or group-manager

        $response = $this->runApp('GET', '/api/user/player/all');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testAll200()
    {
        $this->setupDb();
        $this->loginUser(12);

        $response = $this->runApp('GET', '/api/user/player/all');
        $this->assertEquals(200, $response->getStatusCode());

        $this->assertSame([
            ['id' => $this->player->getId(), 'name' => 'Admin'],
            ['id' => $this->managerId, 'name' => 'Manager'],
            ['id' => $this->userId, 'name' => 'User'],
        ], $this->parseJsonBody($response));
    }

    public function testAppManagers403()
    {
        $response = $this->runApp('GET', '/api/user/player/app-managers');
        $this->assertEquals(403, $response->getStatusCode());

        $this->setupDb();
        $this->loginUser(11); // not user-admin or group-admin

        $response = $this->runApp('GET', '/api/user/player/app-managers');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testAppManagers200NoGroup()
    {
        $h = new Helper();
        $h->emptyDb();
        $h->addCharacterMain('Admin', 12, [Roles::APP_ADMIN]);
        $this->loginUser(12);

        $response = $this->runApp('GET', '/api/user/player/app-managers', null, null, [
            LoggerInterface::class => $this->log
        ]);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertSame([], $this->parseJsonBody($response));
        $this->assertSame(
            'PlayerController->getManagers(): role "app-manager" not found.',
            $this->log->getHandlers()[0]->getRecords()[0]['message']
        );
    }

    public function testAppManagers200()
    {
        $this->setupDb();
        $this->loginUser(12);

        $response = $this->runApp('GET', '/api/user/player/app-managers');
        $this->assertEquals(200, $response->getStatusCode());

        $this->assertSame(
            [['id' => $this->managerId, 'name' => 'Manager']],
            $this->parseJsonBody($response)
        );
    }

    public function testGroupManagers403()
    {
        $response = $this->runApp('GET', '/api/user/player/group-managers');
        $this->assertEquals(403, $response->getStatusCode());

        $this->setupDb();
        $this->loginUser(11); // not user-admin or group-admin

        $response = $this->runApp('GET', '/api/user/player/group-managers');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testGroupManagers200NoGroup()
    {
        $h = new Helper();
        $h->emptyDb();
        $h->addCharacterMain('Admin', 12, [Roles::GROUP_ADMIN]);
        $this->loginUser(12);

        $response = $this->runApp('GET', '/api/user/player/group-managers', null, null, [
            LoggerInterface::class => $this->log
        ]);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertSame([], $this->parseJsonBody($response));
        $this->assertSame(
            'PlayerController->getManagers(): role "group-manager" not found.',
            $this->log->getHandlers()[0]->getRecords()[0]['message']
        );
    }

    public function testGroupManagers200()
    {
        $this->setupDb();
        $this->loginUser(12);

        $response = $this->runApp('GET', '/api/user/player/group-managers');
        $this->assertEquals(200, $response->getStatusCode());

        $this->assertSame(
            [['id' => $this->managerId, 'name' => 'Manager']],
            $this->parseJsonBody($response)
        );
    }

    public function testWithRole403()
    {
        $response = $this->runApp('GET', '/api/user/player/with-role/role-name');
        $this->assertEquals(403, $response->getStatusCode());

        $this->setupDb();
        $this->loginUser(11); // not user-admin

        $response = $this->runApp('GET', '/api/user/player/with-role/role-name');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testWithRole400()
    {
        $this->setupDb();
        $this->loginUser(12);

        $response = $this->runApp('GET', '/api/user/player/with-role/invalid');
        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testWithRole200()
    {
        $this->setupDb();
        $this->loginUser(12);

        $response1 = $this->runApp('GET', '/api/user/player/with-role/'.Roles::APP_ADMIN);
        $response2 = $this->runApp('GET', '/api/user/player/with-role/'.Roles::APP_MANAGER);
        $response3 = $this->runApp('GET', '/api/user/player/with-role/'.Roles::GROUP_ADMIN);
        $response4 = $this->runApp('GET', '/api/user/player/with-role/'.Roles::GROUP_MANAGER);
        $response5 = $this->runApp('GET', '/api/user/player/with-role/'.Roles::USER_ADMIN);

        $this->assertEquals(200, $response1->getStatusCode());
        $this->assertEquals(200, $response2->getStatusCode());
        $this->assertEquals(200, $response3->getStatusCode());
        $this->assertEquals(200, $response4->getStatusCode());
        $this->assertEquals(200, $response5->getStatusCode());

        $this->assertSame([
            ['id' => $this->player->getId(), 'name' => 'Admin']
        ], $this->parseJsonBody($response1));
        $this->assertSame([
            ['id' => $this->managerId, 'name' => 'Manager']
        ], $this->parseJsonBody($response2));
        $this->assertSame([
            ['id' => $this->player->getId(), 'name' => 'Admin']
        ], $this->parseJsonBody($response3));
        $this->assertSame([
            ['id' => $this->managerId, 'name' => 'Manager']
        ], $this->parseJsonBody($response4));
        $this->assertSame([
            ['id' => $this->player->getId(), 'name' => 'Admin']
        ], $this->parseJsonBody($response5));
    }

    public function testAddRole403()
    {
        $response = $this->runApp('PUT', '/api/user/player/101/add-role/r');
        $this->assertEquals(403, $response->getStatusCode());

        $this->setupDb();
        $this->loginUser(11); // not user-admin or group-admin

        $response = $this->runApp('PUT', '/api/user/player/101/add-role/r');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testAddRole404()
    {
        $this->setupDb();
        $this->loginUser(12);

        $response1 = $this->runApp('PUT', '/api/user/player/101/add-role/r');
        $response2 = $this->runApp('PUT', '/api/user/player/101/add-role/'.Roles::APP_MANAGER);
        $response3 = $this->runApp('PUT', '/api/user/player/'.$this->player->getId().'/add-role/role');

        // app is a valid role, but not for users
        $response4 = $this->runApp('PUT', '/api/user/player/'.$this->player->getId().'/add-role/'.Roles::APP);

        $this->assertEquals(404, $response1->getStatusCode());
        $this->assertEquals(404, $response2->getStatusCode());
        $this->assertEquals(404, $response3->getStatusCode());
        $this->assertEquals(404, $response4->getStatusCode());
    }

    public function testAddRole204()
    {
        $this->setupDb();
        $this->loginUser(12);

        $r1 = $this->runApp('PUT', '/api/user/player/'.($this->player->getId()).'/add-role/'.Roles::APP_MANAGER);
        $r2 = $this->runApp('PUT', '/api/user/player/'.($this->player->getId()).'/add-role/'.Roles::APP_MANAGER);
        $this->assertEquals(204, $r1->getStatusCode());
        $this->assertEquals(204, $r2->getStatusCode());

        $this->em->clear();

        $player = (new PlayerRepository($this->em))->find($this->player->getId());
        $this->assertSame(
            [Roles::APP_ADMIN, Roles::APP_MANAGER, Roles::GROUP_ADMIN, Roles::USER, Roles::USER_ADMIN],
            $player->getRoleNames()
        );
    }

    public function testRemoveRole403()
    {
        $response = $this->runApp('PUT', '/api/user/player/101/remove-role/r');
        $this->assertEquals(403, $response->getStatusCode());

        $this->setupDb();
        $this->loginUser(11); // not user-admin or group-admin

        $response = $this->runApp('PUT', '/api/user/player/101/remove-role/r');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testRemoveRole404()
    {
        $this->setupDb();
        $this->loginUser(12);

        $response1 = $this->runApp('PUT', '/api/user/player/101/remove-role/a');
        $response2 = $this->runApp('PUT', '/api/user/player/101/remove-role/'.Roles::APP_MANAGER);
        $response3 = $this->runApp('PUT', '/api/user/player/'.$this->player->getId().'/remove-role/a');

        // user is a valid role, but may not be removed
        $response4 = $this->runApp('PUT', '/api/user/player/'.$this->player->getId().'/remove-role/'.Roles::USER);

        $this->assertEquals(404, $response1->getStatusCode());
        $this->assertEquals(404, $response2->getStatusCode());
        $this->assertEquals(404, $response3->getStatusCode());
        $this->assertEquals(404, $response4->getStatusCode());
    }

    public function testRemoveRole500()
    {
        $this->setupDb();
        $this->loginUser(12);

        $em = $this->h->getEm(true);
        $em->getEventManager()->addEventListener(\Doctrine\ORM\Events::onFlush, new WriteErrorListener());

        $res = $this->runApp('PUT',
            '/api/user/player/'.$this->player->getId().'/remove-role/'.Roles::APP_ADMIN, null, null, [
            EntityManagerInterface::class => $em,
            LoggerInterface::class => $this->log
        ]);
        $this->assertEquals(500, $res->getStatusCode());
    }

    public function testRemoveRole204()
    {
        $this->setupDb();
        $this->loginUser(12);

        $r1 = $this->runApp('PUT', '/api/user/player/'.$this->player->getId().'/remove-role/'.Roles::APP_ADMIN);
        $r2 = $this->runApp('PUT', '/api/user/player/'.$this->player->getId().'/remove-role/'.Roles::APP_ADMIN);
        $this->assertEquals(204, $r1->getStatusCode());
        $this->assertEquals(204, $r2->getStatusCode());

        $this->em->clear();

        $player = (new PlayerRepository($this->em))->find($this->player->getId());
        $this->assertSame(
            [Roles::GROUP_ADMIN, Roles::USER, Roles::USER_ADMIN],
            $player->getRoleNames()
        );
    }

    public function testShowById403()
    {
        $response = $this->runApp('GET', '/api/user/player/1/show');
        $this->assertEquals(403, $response->getStatusCode());

        $this->setupDb();
        $this->loginUser(10); // not user-admin or group-manager

        $response = $this->runApp('GET', '/api/user/player/1/show');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testShowById404()
    {
        $this->setupDb();
        $this->loginUser(12);

        $response = $this->runApp('GET', '/api/user/player/'.($this->player->getId() + 5).'/show');
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testShowById200()
    {
        $this->setupDb();
        $this->loginUser(12);

        $response = $this->runApp('GET', '/api/user/player/'.$this->player->getId().'/show');
        $this->assertEquals(200, $response->getStatusCode());

        $this->assertSame([
            'id' => $this->player->getId(),
            'name' => 'Admin',
            'roles' => [Roles::APP_ADMIN, Roles::GROUP_ADMIN, Roles::USER, Roles::USER_ADMIN],
            'characters' => [
                [
                    'id' => 12,
                    'name' => 'Admin',
                    'main' => true,
                    'lastUpdate' => null,
                    'validToken' => false,
                    'corporation' => [
                        'id' => 234, 'name' => 'ccc', 'ticker' => 'c-c', 'alliance' => [
                            'id' => 123, 'name' => 'aaa', 'ticker' => 'a-a'
                        ]
                    ]
                ],
                [
                    'id' => 13,
                    'name' => 'Alt',
                    'main' => false,
                    'lastUpdate' => null,
                    'validToken' => false,
                    'corporation' => null
                ],
            ],
            'applications' => [],
            'groups' => [],
            'managerGroups' => [],
            'managerApps' => [],
        ], $this->parseJsonBody($response));
    }

    public function testCharacters403()
    {
        $this->setupDb();
        $this->loginUser(12); // not a group-manager

        $response = $this->runApp('GET', '/api/user/player/'.$this->player->getId().'/characters');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testCharacters404()
    {
        $this->setupDb();
        $this->loginUser(11);

        $response = $this->runApp('GET', '/api/user/player/'.($this->player->getId() + 5).'/characters');
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testCharacters200()
    {
        $this->setupDb();
        $this->loginUser(11);

        $response = $this->runApp('GET', '/api/user/player/'.$this->player->getId().'/characters');
        $this->assertEquals(200, $response->getStatusCode());

        $this->assertSame([
            'id' => $this->player->getId(),
            'name' => 'Admin',
            'characters' => [
                [
                    'id' => 12,
                    'name' => 'Admin',
                    'main' => true,
                    'lastUpdate' => null,
                    'validToken' => false,
                    'corporation' => [
                        'id' => 234, 'name' => 'ccc', 'ticker' => 'c-c', 'alliance' => [
                            'id' => 123, 'name' => 'aaa', 'ticker' => 'a-a'
                        ]
                    ]
                ],
                [
                    'id' => 13,
                    'name' => 'Alt',
                    'main' => false,
                    'lastUpdate' => null,
                    'validToken' => false,
                    'corporation' => null
                ],
            ],
        ], $this->parseJsonBody($response));
    }

    private function setupDb()
    {
        $this->h->emptyDb();

        $this->h->addRoles([
            Roles::USER,
            Roles::APP,
            Roles::APP_ADMIN,
            Roles::APP_MANAGER,
            Roles::GROUP_ADMIN,
            Roles::GROUP_MANAGER,
            Roles::USER_ADMIN
        ]);

        $gs = $this->h->addGroups(['test-pub', 'test-priv']);
        $gs[0]->setVisibility(Group::VISIBILITY_PUBLIC);
        $this->group = $gs[0];
        $this->gPrivId = $gs[1]->getId();

        $this->userId = $this->h->addCharacterMain('User', 10, [Roles::USER])->getPlayer()->getId();

        $this->managerId = $this->h->addCharacterMain(
            'Manager', 11, [Roles::USER, Roles::APP_MANAGER, Roles::GROUP_MANAGER])->getPlayer()->getId();

        $alli = (new Alliance())->setId(123)->setName('aaa')->setTicker('a-a');
        $corp = (new Corporation())->setId(234)->setName('ccc')->setTicker('c-c')->setAlliance($alli);

        $char = $this->h->addCharacterMain('Admin', 12,
            [Roles::USER, Roles::APP_ADMIN, Roles::USER_ADMIN, Roles::GROUP_ADMIN]);
        $char->setCorporation($corp);
        $this->player = $char->getPlayer();

        $this->h->getEm()->persist($corp);
        $this->h->getEm()->persist($alli);
        $this->h->getEm()->flush();

        $this->h->addCharacterToPlayer('Alt', 13, $this->player);
    }
}
