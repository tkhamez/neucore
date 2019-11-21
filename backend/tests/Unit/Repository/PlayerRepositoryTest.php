<?php declare(strict_types=1);

namespace Tests\Unit\Repository;

use Neucore\Entity\Character;
use Neucore\Entity\Corporation;
use Neucore\Entity\Group;
use Neucore\Entity\Player;
use Neucore\Entity\Role;
use Neucore\Factory\RepositoryFactory;
use Neucore\Repository\PlayerRepository;
use PHPUnit\Framework\TestCase;
use Tests\Helper;

class PlayerRepositoryTest extends TestCase
{
    /**
     * @var Group
     */
    private static $group1;
    
    /**
     * @var Group
     */
    private static $group2;

    /**
     * @var Player
     */
    private static $player4;

    /**
     * @var PlayerRepository
     */
    private $repo;

    public static function setupBeforeClass(): void
    {
        $helper = new Helper();
        $helper->emptyDb();
        $em = $helper->getEm();

        self::$group1 = (new Group())->setName('g1');
        self::$group2 = (new Group())->setName('g2');
        $em->persist(self::$group1);
        $em->persist(self::$group2);

        $roleTracking = (new Role(10))->setName(Role::TRACKING);
        $em->persist($roleTracking);

        $corp1 = (new Corporation())->setId(98000101)->setName('corp1');
        $corp2 = (new Corporation())->setId(98000102)->setName('corp2');
        $corp3 = (new Corporation())->setId(98000103)->setName('corp3');
        $corp4 = (new Corporation())->setId(1000500)->setName('corp4'); // NPC corp
        $em->persist($corp1);
        $em->persist($corp2);
        $em->persist($corp3);
        $em->persist($corp4);

        $player1 = $helper->addCharacterMain('c1', 1)->getPlayer();
        $player1->getCharacters()[0]->setValidToken(true);
        $player1->getCharacters()[0]->setCorporation($corp1);
        $char1b = (new Character())->setId(12)->setName('c1b')->setValidToken(false);
        $char1b->setPlayer($player1);
        $char1b->setCorporation($corp2);
        $char1c = (new Character())->setId(13)->setName('c1c')->setValidToken(false);
        $char1c->setPlayer($player1);
        $char1c->setCorporation($corp4);
        $player1->addCharacter($char1b);
        $player1->addCharacter($char1c);
        $em->persist($char1b);
        $em->persist($char1c);

        $player2 = $helper->addCharacterMain('c2', 2)->getPlayer();
        $player2->addGroup(self::$group2);
        $player2->addRole($roleTracking);
        $player2->getCharacters()[0]->setCorporation($corp2);

        $player3 = (new Player())->setName('p3');
        $player3->addGroup(self::$group1);
        $player3->addGroup(self::$group2);

        self::$player4 = $helper->addCharacterMain('c4', 3)->getPlayer();
        self::$player4->getCharacters()[0]->setValidToken(true);
        self::$player4->addRole($roleTracking);
        self::$player4->getCharacters()[0]->setCorporation($corp3);

        $player5 = (new Player())->setName('p5');

        $char6 = $helper->addCharacterMain('c6', 6);
        $char6->setCorporation($corp4)->setValidToken(true);

        $player1->setStatus(Player::STATUS_MANAGED);
        $player3->setStatus(Player::STATUS_MANAGED);
        self::$player4->setStatus(Player::STATUS_MANAGED);

        // corps/chars for findInCorporationsWithExcludes test

        $em->persist($player3);
        $em->persist($player5);

        $em->flush();
    }

    protected function setup(): void
    {
        $em = (new Helper())->getEm();
        $this->repo = (new RepositoryFactory($em))->getPlayerRepository();
    }

    public function testFindWithCharacters()
    {
        $actual = $this->repo->findWithCharacters();

        $this->assertSame(4, count($actual));
        $this->assertSame('c1', $actual[0]->getName());
        $this->assertSame('c1', $actual[0]->getCharacters()[0]->getName());
        $this->assertSame('c1b', $actual[0]->getCharacters()[1]->getName());
        $this->assertSame('c2', $actual[1]->getName());
        $this->assertSame('c4', $actual[2]->getName());
        $this->assertSame('c6', $actual[3]->getName());
    }

    public function testFindWithoutCharacters()
    {
        $actual = $this->repo->findWithoutCharacters();

        $this->assertSame(2, count($actual));
        $this->assertSame('p3', $actual[0]->getName());
        $this->assertSame('p5', $actual[1]->getName());
    }

    public function testFindWithCharactersAndStatus()
    {
        $actual = $this->repo->findWithCharactersAndStatus(Player::STATUS_MANAGED);

        $this->assertSame(2, count($actual));
        $this->assertSame('c1', $actual[0]->getName());
        $this->assertSame('c4', $actual[1]->getName());
    }

    public function testFindWithInvalidToken()
    {
        $actual = $this->repo->findWithInvalidToken();

        $this->assertSame(1, count($actual));
        $this->assertSame('c1', $actual[0]->getName());
    }

    public function testFindWithNoToken()
    {
        $actual = $this->repo->findWithNoToken();

        $this->assertSame(1, count($actual));
        $this->assertSame('c2', $actual[0]->getName());
    }

    public function testFindInCorporation()
    {
        $actual = $this->repo->findInCorporation(98000101);

        $this->assertSame(1, count($actual));
        $this->assertSame('c1', $actual[0]->getName());
    }

    public function testFindWithGroups()
    {
        $actual = $this->repo->findWithGroups([self::$group1->getId(), self::$group2->getId()]);
        
        $this->assertSame(2, count($actual));
        $this->assertSame('c2', $actual[0]->getName());
        $this->assertSame('p3', $actual[1]->getName());
    }

    public function testFindWithRole()
    {
        $actual = $this->repo->findWithRole(10);
        
        $this->assertSame(2, count($actual));
        $this->assertSame('c2', $actual[0]->getName());
        $this->assertSame('c4', $actual[1]->getName());
    }

    public function testFindInCorporationsWithExcludes()
    {
        // player c1 is in corp 98000101, 98000102, 1000500
        // player c2 is in corp 98000102
        // player c4 is in corp 98000103
        // player 3, 5 have no character/corp
        // player 6 is in corp 1000500

        $actual = $this->repo->findInCorporationsWithExcludes(
            [98000101, 98000102, 98000103],
            [self::$player4]
        );

        $this->assertSame(2, count($actual));
        $this->assertSame('c1', $actual[0]->getName());
        $this->assertSame('c2', $actual[1]->getName());
    }

    public function testFindNotInNpcCorporationsWithExcludes()
    {
        // player c1 is in corp 98000101, 98000102, 1000500
        // player c2 is in corp 98000102
        // player c4 is in corp 98000103
        // player 3, 5 have no character/corp
        // player 6 is in corp 1000500

        $actual = $this->repo->findNotInNpcCorporationsWithExcludes(
            [98000102],
            [self::$player4]
        );

        $this->assertSame(1, count($actual));
        $this->assertSame('c1', $actual[0]->getName());
    }
}
