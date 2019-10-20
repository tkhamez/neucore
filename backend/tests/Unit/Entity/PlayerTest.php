<?php
/** @noinspection DuplicatedCode */

declare(strict_types=1);

namespace Tests\Unit\Entity;

use Neucore\Entity\Alliance;
use Neucore\Entity\App;
use Neucore\Entity\Character;
use Neucore\Entity\Corporation;
use Neucore\Entity\Group;
use Neucore\Entity\GroupApplication;
use Neucore\Entity\Player;
use Neucore\Entity\RemovedCharacter;
use Neucore\Entity\Role;
use PHPUnit\Framework\TestCase;

class PlayerTest extends TestCase
{
    public function testJsonSerialize()
    {
        $a1 = (new App())->setName('app-one');
        $g1 = (new Group())->setName('gName');
        $g2 = (new Group())->setName('group2');
        $play = new Player();
        $play->setName('test user');
        $play->addGroup($g2);
        $play->addRole((new Role(1))->setName('rName'));
        $play->addRole((new Role(2))->setName('role2'));
        $c1 = new Character();
        $c2 = new Character();
        $c1->setId(123);
        $c2->setId(234);
        $c1->setMain(true);
        $c2->setMain(false);
        $c1->setName('eve one');
        $c2->setName('eve two');
        $c1->setCorporation((new Corporation())->setName('corp1')->setTicker('ABC')
            ->setAlliance((new Alliance())->setName('alli1')->setTicker('DEF')));
        $play->addCharacter($c1);
        $play->addCharacter($c2);
        $play->addManagerGroup($g1);
        $play->addManagerApp($a1);

        $this->assertSame([
            'id' => null,
            'name' => 'test user',
            'status' => Player::STATUS_STANDARD,
            'roles' => ['rName', 'role2'],
            'characters' => [[
                'id' => 123,
                'name' => 'eve one',
                'main' => true,
                'lastUpdate' => null,
                'validToken' => null,
                'validTokenTime' => null,
                'corporation' => ['id' => null, 'name' => 'corp1', 'ticker' => 'ABC', 'alliance' => [
                    'id' => null, 'name' => 'alli1', 'ticker' => 'DEF'
                ]]
            ], [
                'id' => 234,
                'name' =>
                'eve two',
                'main' => false,
                'lastUpdate' => null,
                'validToken' => null,
                'validTokenTime' => null,
                'corporation' => null
            ]],
            'groups' => [
                ['id' => null, 'name' => 'group2', 'visibility' => Group::VISIBILITY_PRIVATE]
            ],
            'managerGroups' => [['id' => null, 'name' => 'gName', 'visibility' => Group::VISIBILITY_PRIVATE]],
            'managerApps' => [['id' => null, 'name' => 'app-one', 'groups' => [], 'roles' => [],]],
        ], json_decode((string) json_encode($play), true));

        $this->assertSame(['id' => null, 'name' => 'test user'], $play->jsonSerialize(true));
    }

    public function testSetGetId()
    {
        $this->assertNull((new Player())->getId());
        $this->assertSame(5, (new Player())->setId(5)->getId());
    }

    public function testSetGetName()
    {
        $play= new Player();
        $play->setName('nam');
        $this->assertSame('nam', $play->getName());
    }

    /**
     * @throws \Exception
     */
    public function testSetGetLastUpdate()
    {
        $dt1 = new \DateTime('2018-04-26 18:59:36');

        $player = new Player();
        $player->setLastUpdate($dt1);
        $dt2 = $player->getLastUpdate();

        $this->assertNotSame($dt1, $dt2);
        $this->assertSame('2018-04-26T18:59:36+00:00', $dt2->format(\DateTime::ATOM));
    }

    public function testSetGetStatus()
    {
        $player = new Player();
        $this->assertSame(Player::STATUS_STANDARD, $player->getStatus());

        $player->setStatus(Player::STATUS_MANAGED);
        $this->assertSame(Player::STATUS_MANAGED, $player->getStatus());
    }

    public function testAddGetRemoveRole()
    {
        $player = new Player();
        $r1 = new Role(1);
        $r2 = new Role(2);
        $r1->setName('n1');
        $r2->setName('n2');

        $this->assertSame([], $player->getRoles());

        $player->addRole($r1);
        $player->addRole($r2);
        $this->assertSame([$r1, $r2], $player->getRoles());

        $player->removeRole($r2);
        $this->assertSame([$r1], $player->getRoles());
    }

    public function testGetRoleNames()
    {
        $player = new Player();
        $r1 = (new Role(1))->setName('n1');
        $r2 = (new Role(2))->setName('n2');
        $player->addRole($r1)->addRole($r2);

        $this->assertSame(['n1', 'n2'], $player->getRoleNames());
    }

    public function testHasRole()
    {
        $player = new Player();
        $role = new Role(1);
        $role->setName('role1');
        $player->addRole($role);

        $this->assertTrue($player->hasRole('role1'));
        $this->assertFalse($player->hasRole('role2'));
    }

    public function testAddGetRemoveCharacter()
    {
        $play = new Player();
        $c1 = new Character();
        $c2 = new Character();

        $this->assertSame([], $play->getCharacters());

        $play->addCharacter($c1);
        $play->addCharacter($c2);
        $this->assertSame([$c1, $c2], $play->getCharacters());

        $play->removeCharacter($c2);
        $this->assertSame([$c1], $play->getCharacters());
    }

    /**
     * @throws \ReflectionException
     */
    public function testHasCharacter()
    {
        $char1 = new Character();
        $char2 = new Character();

        $rp = new \ReflectionProperty(Character::class, 'id');
        $rp->setAccessible(true);
        $rp->setValue($char1, 1);
        $rp->setValue($char2, 2);

        $player = new Player();
        $player->addCharacter($char1);

        $this->assertTrue($player->hasCharacter((int) $char1->getId()));
        $this->assertFalse($player->hasCharacter((int) $char2->getId()));
    }

    /**
     * @throws \Exception
     */
    public function testHasCharacterWithInvalidTokenOlderThan()
    {
        $char1 = (new Character())->setValidToken(true)->setValidTokenTime(new \DateTime('now -10 seconds'));
        $char2 = (new Character())->setValidToken(false)->setValidTokenTime(new \DateTime('now -10 seconds'));
        $char3 = (new Character())->setValidToken(false)->setValidTokenTime(new \DateTime('now -36 hours'));
        $char4 = (new Character())->setValidToken(false)->setValidTokenTime(new \DateTime('now +12 hours'));
        $char5 = (new Character())->setValidTokenTime(new \DateTime('now -36 hours')); // validToken is null

        $player1 = (new Player())->addCharacter($char1);
        $player2 = (new Player())->addCharacter($char2);
        $player3 = (new Player())->addCharacter($char1)->addCharacter($char3);
        $player4 = (new Player())->addCharacter($char1)->addCharacter($char4);
        $player5 = (new Player())->addCharacter($char5);
        $player6 = (new Player())->addCharacter(new Character());

        $this->assertFalse($player1->hasCharacterWithInvalidTokenOlderThan(24));
        $this->assertFalse($player2->hasCharacterWithInvalidTokenOlderThan(24)); // false because time is NOW

        $this->assertFalse($player3->hasCharacterWithInvalidTokenOlderThan(48));
        $this->assertTrue($player3->hasCharacterWithInvalidTokenOlderThan(24));
        $this->assertTrue($player3->hasCharacterWithInvalidTokenOlderThan(6));

        $this->assertFalse($player4->hasCharacterWithInvalidTokenOlderThan(6));

        $this->assertTrue($player5->hasCharacterWithInvalidTokenOlderThan(6)); // true because token is NULL

        $this->assertTrue($player2->hasCharacterWithInvalidTokenOlderThan(0)); // it's older or equal 0

        $this->assertTrue($player6->hasCharacterWithInvalidTokenOlderThan(123)); // no token time set
    }

    public function testGetMain()
    {
        $player = new Player();
        $char1 = new Character();
        $char2 = new Character();
        $player->addCharacter($char1);
        $player->addCharacter($char2);

        $this->assertNull($player->getMain());

        $char1->setMain(true);

        $this->assertSame($char1, $player->getMain());
    }

    public function testAddGetRemoveGroupApplication()
    {
        $play = new Player();
        $a1 = new GroupApplication();
        $a2 = new GroupApplication();

        $this->assertSame([], $play->getGroupApplications());

        $play->addGroupApplication($a1);
        $play->addGroupApplication($a2);
        $this->assertSame([$a1, $a2], $play->getGroupApplications());

        $play->removeGroupApplication($a2);
        $this->assertSame([$a1], $play->getGroupApplications());
    }

    public function testAddGetRemoveGroup()
    {
        $play = new Player();
        $g1 = new Group();
        $g2 = new Group();

        $this->assertSame([], $play->getGroups());

        $play->addGroup($g1);
        $play->addGroup($g2);
        $this->assertSame([$g1, $g2], $play->getGroups());

        $play->removeGroup($g2);
        $this->assertSame([$g1], $play->getGroups());
    }

    /**
     * @throws \ReflectionException
     */
    public function testFindGroupById()
    {
        $group1 = new Group();
        $group2 = new Group();

        $rp = new \ReflectionProperty(Group::class, 'id');
        $rp->setAccessible(true);
        $rp->setValue($group1, 1);
        $rp->setValue($group2, 2);

        $player = new Player();
        $player->addGroup($group1);
        $player->addGroup($group2);

        $this->assertSame(2, $player->findGroupById(2)->getId());
        $this->assertNull($player->findGroupById(3));
    }

    /**
     * @throws \ReflectionException
     */
    public function testGetGroupIds()
    {
        $group1 = new Group();
        $group2 = new Group();

        $rp = new \ReflectionProperty(Group::class, 'id');
        $rp->setAccessible(true);
        $rp->setValue($group1, 1);
        $rp->setValue($group2, 2);

        $player = new Player();
        $player->addGroup($group1);
        $player->addGroup($group2);

        $this->assertSame([1, 2], $player->getGroupIds());
    }

    /**
     * @throws \ReflectionException
     */
    public function testHasGroup()
    {
        $group1 = new Group();
        $group2 = new Group();

        $rp = new \ReflectionProperty(Group::class, 'id');
        $rp->setAccessible(true);
        $rp->setValue($group1, 1);
        $rp->setValue($group2, 2);

        $player = new Player();
        $player->addGroup($group1);

        $this->assertTrue($player->hasGroup($group1->getId()));
        $this->assertFalse($player->hasGroup($group2->getId()));
    }

    /**
     * @throws \ReflectionException
     */
    public function testHasAnyGroup()
    {
        $group1 = new Group();
        $group2 = new Group();

        $rp = new \ReflectionProperty(Group::class, 'id');
        $rp->setAccessible(true);
        $rp->setValue($group1, 1);
        $rp->setValue($group2, 2);

        $player = new Player();
        $player->addGroup($group1);

        $this->assertTrue($player->hasAnyGroup([1, 2]));
        $this->assertFalse($player->hasAnyGroup([2, 3]));
    }

    public function testAddGetRemoveManagerGroups()
    {
        $play = new Player();
        $g1 = new Group();
        $g2 = new Group();

        $this->assertSame([], $play->getManagerGroups());

        $play->addManagerGroup($g1);
        $play->addManagerGroup($g2);
        $this->assertSame([$g1, $g2], $play->getManagerGroups());

        $play->removeManagerGroup($g2);
        $this->assertSame([$g1], $play->getManagerGroups());
    }

    public function testHasManagerGroup()
    {
        $player = new Player();
        $group1 = (new Group())->setName('g1');
        $group2 = (new Group())->setName('g2');
        $player->addManagerGroup($group1);

        $this->assertTrue($player->hasManagerGroup($group1));
        $this->assertFalse($player->hasManagerGroup($group2));
    }

    public function testAddGetRemoveManagerApps()
    {
        $play = new Player();
        $a1 = new App();
        $a2 = new App();

        $this->assertSame([], $play->getManagerApps());

        $play->addManagerApp($a1);
        $play->addManagerApp($a2);
        $this->assertSame([$a1, $a2], $play->getManagerApps());

        $play->removeManagerApp($a2);
        $this->assertSame([$a1], $play->getManagerApps());
    }

    public function testAddGetRemoveRemovedCharacters()
    {
        $play = new Player();
        $rc1 = new RemovedCharacter();
        $rc2 = new RemovedCharacter();

        $this->assertSame([], $play->getRemovedCharacters());

        $play->addRemovedCharacter($rc1);
        $play->addRemovedCharacter($rc2);
        $this->assertSame([$rc1, $rc2], $play->getRemovedCharacters());

        $play->removeRemovedCharacter($rc2);
        $this->assertSame([$rc1], $play->getRemovedCharacters());
    }
}
