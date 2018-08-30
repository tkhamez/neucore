<?php declare(strict_types=1);

namespace Tests\Unit\Core\Service;

use Brave\Core\Repository\AllianceRepository;
use Brave\Core\Entity\Character;
use Brave\Core\Entity\Corporation;
use Brave\Core\Repository\CorporationRepository;
use Brave\Core\Entity\Group;
use Brave\Core\Repository\GroupRepository;
use Brave\Core\Entity\Player;
use Brave\Core\Repository\PlayerRepository;
use Brave\Core\Service\AutoGroupAssignment;
use Brave\Core\Service\ObjectManager;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Tests\Helper;
use Tests\WriteErrorListener;
use Brave\Core\Entity\Alliance;

class AutoGroupAssignmentTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var Helper
     */
    private $th;

    /**
     * @var \Doctrine\ORM\EntityManagerInterface
     */
    private $em;

    /**
     * @var PlayerRepository
     */
    private $playerRepo;

    /**
     * @var AutoGroupAssignment
     */
    private $aga;

    /**
     * @var AutoGroupAssignment
     */
    private $agaError;

    private $playerId;

    private $group1Id;

    private $group2Id;

    private $group3Id;

    private $group4Id;

    private $group5Id;

    private $group6Id;

    private $group7Id;

    public function setUp()
    {
        $this->th = new Helper();
        $this->em = $this->th->getEm();

        $log = new Logger('Test');
        $log->pushHandler(new TestHandler());

        $alliRepo = new AllianceRepository($this->em);
        $corpRepo = new CorporationRepository($this->em);
        $groupRepo = new GroupRepository($this->em);
        $this->playerRepo = new PlayerRepository($this->em);

        $this->aga = new AutoGroupAssignment(
            new ObjectManager($this->em, $log), $alliRepo, $corpRepo, $groupRepo, $this->playerRepo);

        // a second EsiCharacter instance with another entity manager that throws an exception on flush.
        $em = (new Helper())->getEm(true);
        $em->getEventManager()->addEventListener(\Doctrine\ORM\Events::onFlush, new WriteErrorListener());
        $this->agaError = new AutoGroupAssignment(
            new ObjectManager($em, $log), $alliRepo, $corpRepo, $groupRepo, $this->playerRepo);
    }

    public function testAssignNotFound()
    {
        $player = $this->aga->assign(123);
        $this->assertNull($player);
    }

    public function testAssign()
    {
        $this->setUpData();

        // Player belongs to corps with groups 1, 2, 3 and 7
        // Group 4 belongs to another corp
        // Group 5 does not belong to any group
        // Group 6 belongs to the player's alliance
        // Group 7 belongs to the player's alliance and corp 2

        $playerBefore = $this->playerRepo->find($this->playerId);
        $this->assertSame([$this->group4Id, $this->group5Id], $playerBefore->getGroupIds());

        $player = $this->aga->assign($this->playerId);
        $this->assertSame($this->playerId, $player->getId());
        $this->em->clear();

        $playerDb = $this->playerRepo->find($this->playerId);
        $groupIds = $playerDb->getGroupIds();
        $this->assertSame(
            [$this->group1Id, $this->group2Id, $this->group3Id, $this->group5Id, $this->group6Id, $this->group7Id],
            $groupIds
        );
        $this->assertGreaterThan('2018-04-28 17:56:54', $playerDb->getLastUpdate()->format('Y-m-d H:i:s'));
    }

    public function testAssignFlushError()
    {
        $this->setUpData();
        $player = $this->agaError->assign($this->playerId);
        $this->assertNull($player);
    }

    private function setUpData()
    {
        $this->th->emptyDb();

        $group1 = (new Group())->setName('g1');
        $group2 = (new Group())->setName('g2');
        $group3 = (new Group())->setName('g3');
        $group4 = (new Group())->setName('g4');
        $group5 = (new Group())->setName('g5');
        $group6 = (new Group())->setName('g6');
        $group7 = (new Group())->setName('g7');
        $alliance = (new Alliance())->setId(1)->setName('a1')->setTicker('ta1')->addGroup($group6)->addGroup($group7);
        $corp1 = (new Corporation())->setId(1)->setName('c1')->setTicker('t1')->addGroup($group1)->addGroup($group2);
        $corp2 = (new Corporation())->setId(2)->setName('c2')->setTicker('t2')->addGroup($group1)->addGroup($group3)
            ->addGroup($group7)->setAlliance($alliance);
        $corp3 = (new Corporation())->setId(3)->setName('c2')->setTicker('t3')->addGroup($group4);
        $player = (new Player())->setName('p')->addGroup($group4)->addGroup($group5)
            ->setLastUpdate(new \DateTime('2018-04-28 17:56:54'));
        $char1 = (new Character())->setId(1)->setName('ch1')->setMain(true)->setPlayer($player)
            ->setCharacterOwnerHash('h1')->setAccessToken('t1')->setCorporation($corp1);
        $char2 = (new Character())->setId(2)->setName('ch2')->setMain(false)->setPlayer($player)
            ->setCharacterOwnerHash('h2')->setAccessToken('t2')->setCorporation($corp2);
        $char3 = (new Character())->setId(3)->setName('ch3')->setMain(false)->setPlayer($player)
            ->setCharacterOwnerHash('h2')->setAccessToken('t2');

        $this->em->persist($group1);
        $this->em->persist($group2);
        $this->em->persist($group3);
        $this->em->persist($group4);
        $this->em->persist($group5);
        $this->em->persist($group6);
        $this->em->persist($group7);
        $this->em->persist($alliance);
        $this->em->persist($corp1);
        $this->em->persist($corp2);
        $this->em->persist($corp3);
        $this->em->persist($char1);
        $this->em->persist($char2);
        $this->em->persist($char3);
        $this->em->persist($player);
        $this->em->flush();
        $this->em->clear();

        $this->playerId = $player->getId();
        $this->group1Id = $group1->getId();
        $this->group2Id = $group2->getId();
        $this->group3Id = $group3->getId();
        $this->group4Id = $group4->getId();
        $this->group5Id = $group5->getId();
        $this->group6Id = $group6->getId();
        $this->group7Id = $group7->getId();
    }
}
