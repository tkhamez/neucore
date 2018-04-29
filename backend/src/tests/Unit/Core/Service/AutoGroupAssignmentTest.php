<?php declare(strict_types=1);

namespace Tests\Unit\Core\Service;

use Brave\Core\Entity\CorporationRepository;
use Brave\Core\Entity\PlayerRepository;
use Brave\Core\Service\AutoGroupAssignment;
use Tests\Helper;
use Brave\Core\Entity\GroupRepository;
use Brave\Core\Entity\Player;
use Brave\Core\Entity\Corporation;
use Brave\Core\Entity\Group;
use Brave\Core\Entity\Character;

class AutoGroupAssignmentTest extends \PHPUnit\Framework\TestCase
{
    private $th;

    private $em;

    private $playerRepo;

    private $aga;

    private $playerId;

    private $group1Id;

    private $group2Id;

    private $group3Id;

    public function setUp()
    {
        $this->th = new Helper();
        $this->em = $this->th->getEm();

        $corpRepo = new CorporationRepository($this->em);
        $groupRepo = new GroupRepository($this->em);
        $this->playerRepo = new PlayerRepository($this->em);

        $this->aga = new AutoGroupAssignment($this->em, $corpRepo, $groupRepo, $this->playerRepo);
    }

    public function testAssignNotFound()
    {
        $player = $this->aga->assign(123);
        $this->assertNull($player);
    }

    public function testAssign()
    {
        $this->setUpData();

        $player = $this->aga->assign($this->playerId);
        $this->assertSame($this->playerId, $player->getId());
        $this->em->clear();

        $playerDb = $this->playerRepo->find($this->playerId);
        $groupIds = $playerDb->getGroupIds();
        $this->assertSame([$this->group1Id, $this->group2Id], $groupIds);
        $this->assertGreaterThan('2018-04-28 17:56:54', $playerDb->getLastUpdate()->format('Y-m-d H:i:s'));
    }

    private function setUpData()
    {
        $this->th->emptyDb();

        $group1 = (new Group())->setName('g1');
        $group2 = (new Group())->setName('g2');
        $group3 = (new Group())->setName('g3');
        $corp1 = (new Corporation())->setId(1)->setName('c1')->setTicker('t')->addGroup($group1);
        $corp2 = (new Corporation())->setId(2)->setName('c2')->setTicker('t')->addGroup($group2);
        $player = (new Player())->setName('p')->addGroup($group2)->addGroup($group3)
            ->setLastUpdate(new \DateTime('2018-04-28 17:56:54'));
        $char1 = (new Character())->setId(1)->setName('ch1')->setMain(true)->setPlayer($player)
            ->setCharacterOwnerHash('h1')->setAccessToken('t1')->setCorporation($corp1);
        $char2 = (new Character())->setId(2)->setName('ch2')->setMain(false)->setPlayer($player)
            ->setCharacterOwnerHash('h2')->setAccessToken('t2')->setCorporation($corp2);

        $this->em->persist($group1);
        $this->em->persist($group2);
        $this->em->persist($group3);
        $this->em->persist($corp1);
        $this->em->persist($corp2);
        $this->em->persist($char1);
        $this->em->persist($char2);
        $this->em->persist($player);
        $this->em->flush();
        $this->em->clear();

        $this->playerId = $player->getId();
        $this->group1Id = $group1->getId();
        $this->group2Id = $group2->getId();
        $this->group3Id = $group3->getId();
    }
}
