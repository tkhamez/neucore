<?php declare(strict_types=1);

namespace Tests\Unit\Service;

use Neucore\Entity\Character;
use Neucore\Entity\Corporation;
use Neucore\Entity\Group;
use Neucore\Entity\Player;
use Neucore\Entity\Role;
use Neucore\Repository\PlayerRepository;
use Neucore\Factory\RepositoryFactory;
use Neucore\Service\Account;
use Neucore\Service\AutoGroupAssignment;
use Neucore\Service\ObjectManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use PHPUnit\Framework\TestCase;
use Tests\Helper;
use Tests\Logger;
use Tests\WriteErrorListener;
use Neucore\Entity\Alliance;

class AutoGroupAssignmentTest extends TestCase
{
    /**
     * @var Helper
     */
    private $th;

    /**
     * @var EntityManagerInterface
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
     * @var Logger
     */
    private $log;

    /**
     * @var AutoGroupAssignment
     */
    private $agaError;

    private $playerId;

    private $playerManagedId;

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
     * @var Group
     */
    private $group4;

    private $group5Id;

    private $group6Id;

    private $group7Id;

    public function setUp()
    {
        $this->th = new Helper();
        $this->em = $this->th->getEm();

        $this->log = new Logger('Test');

        $repositoryFactory = new RepositoryFactory($this->em);
        $this->playerRepo = $repositoryFactory->getPlayerRepository();

        $objectManager = new ObjectManager($this->em, $this->log);
        $account = new Account($this->log, $objectManager, $repositoryFactory);
        
        $this->aga = new AutoGroupAssignment($objectManager, $repositoryFactory, $this->log, $account);

        // a second AutoGroupAssignment instance with another entity manager that throws an exception on flush.
        $em = (new Helper())->getEm(true);
        $em->getEventManager()->addEventListener(Events::onFlush, new WriteErrorListener());
        $this->agaError = new AutoGroupAssignment(
            new ObjectManager($em, $this->log),
            $repositoryFactory,
            $this->log,
            $account
        );
    }

    public function testAssignNotFound()
    {
        $success = $this->aga->assign(123);
        $this->assertFalse($success);
    }

    public function testAssignManaged()
    {
        $this->setUpData();

        $success = $this->aga->assign($this->playerManagedId);
        $this->assertTrue($success);
    }

    public function testAssign()
    {
        $this->setUpData();
        $this->em->clear();

        // Player belongs to corps 1 and 2 with groups 1, 2, 3 and 7
        // Group 4 belongs to another corp
        // Group 5 does not belong to any corp or alliance and is assigned to the player directly
        // Group 6 belongs to the player's alliance
        // Group 7 belongs to the player's alliance and corp 2

        $playerBefore = $this->playerRepo->find($this->playerId);
        $this->assertSame([$this->group4->getId(), $this->group5Id], $playerBefore->getGroupIds());

        $success = $this->aga->assign($this->playerId);

        $this->assertTrue($success);
        $this->em->clear();

        $playerDb = $this->playerRepo->find($this->playerId);
        $groupIds = $playerDb->getGroupIds();
        $this->assertSame([
            $this->group1->getId(),
            $this->group2->getId(),
            $this->group3->getId(),
            $this->group5Id,
            $this->group6Id,
            $this->group7Id
        ], $groupIds);
        $this->assertGreaterThan('2018-04-28 17:56:54', $playerDb->getLastUpdate()->format('Y-m-d H:i:s'));

        $logs = $this->log->getHandler()->getRecords();
        $this->assertSame(6, count($logs));
        $this->assertContains('removed group g4 ['.$this->group4->getId().']', $logs[0]['message']);
        $this->assertContains('added group g1 ['.$this->group1->getId().']', $logs[1]['message']);
        $this->assertContains('added group g2 ['.$this->group2->getId().']', $logs[2]['message']);
        $this->assertContains('added group g3 ['.$this->group3->getId().']', $logs[3]['message']);
        $this->assertContains('added group g7 ['.$this->group7Id.']', $logs[4]['message']);
        $this->assertContains('added group g6 ['.$this->group6Id.']', $logs[5]['message']);
    }

    public function testAssignFlushError()
    {
        $this->setUpData();
        $success = $this->agaError->assign($this->playerId);
        $this->assertFalse($success);
    }

    public function testCheckRequiredGroups()
    {
        $this->setUpData();
        $playerBefore = $this->playerRepo->find($this->playerId);
        $playerBefore->addGroup($this->group1);
        $playerBefore->addGroup($this->group2);
        $playerBefore->addGroup($this->group3);
        $this->em->flush();
        $this->assertSame(
            [
                $this->group4->getId(),
                $this->group5Id,
                $this->group1->getId(),
                $this->group2->getId(),
                $this->group3->getId(),
            ],
            $playerBefore->getGroupIds()
        );

        // group1 depends on group5 -> player has g5
        // group5 depends on group6 -> player has not g6
        // group2 depends on group3 -> player has g3
        // group4 has no required groups

        $success = $this->aga->checkRequiredGroups($this->playerId);
        $this->assertTrue($success);

        $this->em->clear();
        $playerAfter = $this->playerRepo->find($this->playerId);
        $this->assertSame(
            [
                $this->group2->getId(),
                $this->group3->getId(),
                $this->group4->getId(),
            ],
            $playerAfter->getGroupIds()
        );
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
        $playerManaged = (new Player())->setName('pm')->setStatus(Player::STATUS_MANAGED);
        $char1 = (new Character())->setId(1)->setName('ch1')->setMain(true)->setPlayer($player)
            ->setCharacterOwnerHash('h1')->setAccessToken('t1')->setCorporation($corp1);
        $char2 = (new Character())->setId(2)->setName('ch2')->setMain(false)->setPlayer($player)
            ->setCharacterOwnerHash('h2')->setAccessToken('t2')->setCorporation($corp2);
        $char3 = (new Character())->setId(3)->setName('ch3')->setMain(false)->setPlayer($player)
            ->setCharacterOwnerHash('h2')->setAccessToken('t2');
        $char4 = (new Character())->setId(4)->setName('ch4')->setPlayer($playerManaged)->setCorporation($corp2);

        $group1->addRequiredGroup($group5);
        $group5->addRequiredGroup($group6);
        $group2->addRequiredGroup($group3);

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
        $this->em->persist($char4);
        $this->em->persist($player);
        $this->em->persist((new Role(11))->setName(Role::TRACKING));
        $this->em->persist($playerManaged);
        $this->em->flush();

        $this->playerId = $player->getId();
        $this->playerManagedId = $playerManaged->getId();
        $this->group1 = $group1;
        $this->group2 = $group2;
        $this->group3 = $group3;
        $this->group4 = $group4;
        $this->group5Id = $group5->getId();
        $this->group6Id = $group6->getId();
        $this->group7Id = $group7->getId();
    }
}
