<?php

declare(strict_types=1);

namespace Tests\Unit\Repository;

use Doctrine\Persistence\ObjectManager;
use Neucore\Entity\Character;
use Neucore\Entity\Corporation;
use Neucore\Entity\CorporationMember;
use Neucore\Entity\Player;
use Neucore\Factory\RepositoryFactory;
use PHPUnit\Framework\TestCase;
use Tests\Helper;

class CorporationMemberRepositoryTest extends TestCase
{
    /**
     * @var ObjectManager
     */
    private static $om;

    /**
     * @throws \Exception
     */
    public static function setupBeforeClass(): void
    {
        $helper = new Helper();
        $helper->emptyDb();
        self::$om = $helper->getObjectManager();

        $player1 = (new Player())->setName('Player 1');
        $char1 = (new Character())->setId(1)->setName('Char 1')->setPlayer($player1)->setValidToken(true);
        $char2 = (new Character())->setId(2)->setName('Char 2')->setPlayer($player1)
            ->setValidToken(false)->setValidTokenTime(new \DateTime("now -240 hours"));
        $char5 = (new Character())->setId(5)->setName('Char 5')->setPlayer($player1)->setValidToken(true);
        $corp1 = (new Corporation())->setId(1)->setName('Corp 1')->setTicker('C1');
        $corp2 = (new Corporation())->setId(2)->setName('Corp 2')->setTicker('C2');
        $member1 = (new CorporationMember())->setId(10)->setName('Member 1')->setCorporation($corp1)
            ->setLogonDate(new \DateTime('now -112 days +1 hour'))->setCharacter($char1);
        $member1a = (new CorporationMember())->setId(101)->setName('Member 1a')->setCorporation($corp1)
            ->setLogonDate(new \DateTime('now -112 days +30 minutes'));
        $member2 = (new CorporationMember())->setId(20)->setName('Member 2')->setCorporation($corp1)
            ->setLogonDate(new \DateTime('now -111 days +1 hour'))->setCharacter($char2);
        $member3 = (new CorporationMember())->setId(30)->setName('Member 3')->setCorporation($corp1)
            ->setLogonDate(new \DateTime('now -110 days +1 hour'));
        $member4 = (new CorporationMember())->setId(40)->setName('Member 4')->setCorporation($corp2)
            ->setLogonDate(new \DateTime('now -111 days +30 minutes'));
        $member5 = (new CorporationMember())->setId(50)->setName('Member 5')->setCorporation($corp2)
            ->setLogonDate(new \DateTime('now -110 days +30 minutes'))->setCharacter($char5);

        self::$om->persist($player1);
        self::$om->persist($char1);
        self::$om->persist($char2);
        self::$om->persist($char5);
        self::$om->persist($corp1);
        self::$om->persist($corp2);
        self::$om->persist($member1);
        self::$om->persist($member1a);
        self::$om->persist($member2);
        self::$om->persist($member3);
        self::$om->persist($member4);
        self::$om->persist($member5);

        self::$om->flush();
    }

    public function testResetCriteria()
    {
        $repository = (new RepositoryFactory(self::$om))->getCorporationMemberRepository();

        $repository->setAccount(false);
        $repository->setActive(110);
        $repository->setInactive(110);
        $repository->setValidToken(false);
        $repository->setTokenChanged(100);
        $this->assertSame(0, count($repository->findMatching(1)));

        $repository->resetCriteria();

        $actual = $repository->findMatching(1);

        $this->assertSame(4, count($repository->findMatching(1)));

        $this->assertSame('Member 3', $actual[0]->getName());
        $this->assertSame('Member 2', $actual[1]->getName());
        $this->assertSame('Member 1', $actual[2]->getName());
        $this->assertSame('Member 1a', $actual[3]->getName());
    }

    public function testFindMatchingActive()
    {
        $repository = (new RepositoryFactory(self::$om))->getCorporationMemberRepository();

        $actual1 = $repository->setActive(109)->findMatching(1);
        $actual2 = $repository->setActive(110)->findMatching(1);
        $actual3 = $repository->setActive(112)->findMatching(1);

        $this->assertSame(0, count($actual1));
        $this->assertSame(1, count($actual2));
        $this->assertSame(4, count($actual3));

        $this->assertSame('Member 3', $actual2[0]->getName());
        $this->assertSame('Member 3', $actual3[0]->getName());
        $this->assertSame('Member 2', $actual3[1]->getName());
        $this->assertSame('Member 1', $actual3[2]->getName());
        $this->assertSame('Member 1a', $actual3[3]->getName());
    }

    public function testFindMatchingInactive()
    {
        $repository = (new RepositoryFactory(self::$om))->getCorporationMemberRepository();

        $actual1 = $repository->setInactive(109)->findMatching(1);
        $actual2 = $repository->setInactive(110)->findMatching(1);
        $actual3 = $repository->setInactive(112)->findMatching(1);

        $this->assertSame(4, count($actual1));
        $this->assertSame(3, count($actual2));
        $this->assertSame(0, count($actual3));

        $this->assertSame('Member 3', $actual1[0]->getName());
        $this->assertSame('Member 2', $actual1[1]->getName());
        $this->assertSame('Member 1', $actual1[2]->getName());
        $this->assertSame('Member 1a', $actual1[3]->getName());
        $this->assertSame('Member 2', $actual2[0]->getName());
        $this->assertSame('Member 1', $actual2[1]->getName());
        $this->assertSame('Member 1a', $actual2[2]->getName());
    }

    public function testFindMatchingActiveRange()
    {
        $repository = (new RepositoryFactory(self::$om))->getCorporationMemberRepository();

        $actual = $repository->setInactive(110)->setActive(111)->findMatching(1);

        $this->assertSame(1, count($actual));

        $this->assertSame('Member 2', $actual[0]->getName());
    }

    public function testFindMatchingWithOutAccount()
    {
        $repository = (new RepositoryFactory(self::$om))->getCorporationMemberRepository();

        $actual1 = $repository->setAccount(true)->findMatching(1);
        $actual2 = $repository->setAccount(false)->findMatching(1);

        $this->assertSame(2, count($actual1));
        $this->assertSame(2, count($actual2));

        $this->assertSame('Member 2', $actual1[0]->getName());
        $this->assertSame('Member 1', $actual1[1]->getName());
        $this->assertSame('Char 2', $actual1[0]->getCharacter()->getName());
        $this->assertSame('Char 1', $actual1[1]->getCharacter()->getName());
        $this->assertSame('Member 3', $actual2[0]->getName());
        $this->assertSame('Member 1a', $actual2[1]->getName());
    }

    public function testFindMatchingWithToken()
    {
        $repository = (new RepositoryFactory(self::$om))->getCorporationMemberRepository();

        $actual1 = $repository->setValidToken(true)->findMatching(1);
        $actual2 = $repository->setValidToken(false)->findMatching(1);

        $this->assertSame(1, count($actual1));
        $this->assertSame(1, count($actual2));

        $this->assertSame('Char 1', $actual1[0]->getCharacter()->getName());
        $this->assertSame('Char 2', $actual2[0]->getCharacter()->getName());
    }

    public function testFindMatchingTokenChanged()
    {
        $repository = (new RepositoryFactory(self::$om))->getCorporationMemberRepository();

        $actual = $repository->setTokenChanged(9)->findMatching(1);

        $this->assertSame(1, count($actual));
        $this->assertSame('Char 2', $actual[0]->getCharacter()->getName());
    }

    public function testRemoveFormerMembers()
    {
        $repository = (new RepositoryFactory(self::$om))->getCorporationMemberRepository();
        $actual = $repository->removeFormerMembers(1, [10, 30]);
        $this->assertSame(2, $actual); // removed ids: 20, 101
    }

    public function testFindByCorporationsWithoutAccount()
    {
        $repository = (new RepositoryFactory(self::$om))->getCorporationMemberRepository();

        $actual0 = $repository->findByCorporationsWithoutAccountAndActive([1, 2], 111);
        $this->assertSame(2, count($actual0));
        $this->assertSame(30, $actual0[0]->getId());
        $this->assertSame(40, $actual0[1]->getId());

        $actual1 = $repository->findByCorporationsWithoutAccountAndActive([1, 2], 111, 1, 0);
        $this->assertSame(1, count($actual1));
        $this->assertSame(30, $actual1[0]->getId());

        $actual2 = $repository->findByCorporationsWithoutAccountAndActive([1, 2], 111, 1, 1);
        $this->assertSame(1, count($actual2));
        $this->assertSame(40, $actual2[0]->getId());
    }
}
