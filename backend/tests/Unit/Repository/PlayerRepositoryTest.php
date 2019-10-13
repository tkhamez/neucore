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
        
        $player1 = $helper->addCharacterMain('c1', 1)->getPlayer();
        $player1->getCharacters()[0]->setValidToken(true);
        $char1a = (new Character())->setId(11)->setName('c1a')->setValidToken(false);
        $char1a->setPlayer($player1);
        $corp = (new Corporation())->setId(100)->setName('corp1');
        $char1a->setCorporation($corp);
        $player1->addCharacter($char1a);
        $em->persist($char1a);
        $em->persist($corp);

        $player2 = $helper->addCharacterMain('c2', 2)->getPlayer();
        $player2->addGroup(self::$group2);
        $player2->addRole($roleTracking);

        $player3 = (new Player())->setName('p3');
        $player3->addGroup(self::$group1);
        $player3->addGroup(self::$group2);

        $player4 = $helper->addCharacterMain('c4', 3)->getPlayer();
        $player4->getCharacters()[0]->setValidToken(true);
        $player4->addRole($roleTracking);

        $player5 = (new Player())->setName('p5');

        $player1->setStatus(Player::STATUS_MANAGED);
        $player3->setStatus(Player::STATUS_MANAGED);
        $player4->setStatus(Player::STATUS_MANAGED);

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

        $this->assertSame(3, count($actual));
        $this->assertSame('c1', $actual[0]->getName());
        $this->assertSame('c1', $actual[0]->getCharacters()[0]->getName());
        $this->assertSame('c1a', $actual[0]->getCharacters()[1]->getName());
        $this->assertSame('c2', $actual[1]->getName());
        $this->assertSame('c4', $actual[2]->getName());
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
        $actual = $this->repo->findInCorporation(100);

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
}
