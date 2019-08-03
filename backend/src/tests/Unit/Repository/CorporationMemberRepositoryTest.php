<?php declare(strict_types=1);

namespace Tests\Unit\Repository;

use Doctrine\ORM\EntityManagerInterface;
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
     * @var EntityManagerInterface
     */
    private static $em;

    /**
     * @throws \Exception
     */
    public static function setUpBeforeClass()
    {
        $helper = new Helper();
        $helper->emptyDb();
        self::$em = $helper->getEm();

        $player1 = (new Player())->setName('Player 1');
        $char1 = (new Character())->setId(1)->setName('Char 1')->setPlayer($player1)->setValidToken(true);
        $char2 = (new Character())->setId(2)->setName('Char 2')->setPlayer($player1)
            ->setValidToken(false)->setValidTokenTime(new \DateTime("now -240 hours"));
        $corp1 = (new Corporation())->setId(1)->setName('Corp 1')->setTicker('C1');
        $corp2 = (new Corporation())->setId(2)->setName('Corp 2')->setTicker('C2');
        $member1 = (new CorporationMember())->setId(10)->setName('Member 1')->setCorporation($corp1)
            ->setLogonDate(new \DateTime('now -112 days +1 hour'))->setCharacter($char1);
        $member2 = (new CorporationMember())->setId(20)->setName('Member 2')->setCorporation($corp1)
            ->setLogonDate(new \DateTime('now -111 days +1 hour'))->setCharacter($char2);
        $member3 = (new CorporationMember())->setId(30)->setName('Member 3')->setCorporation($corp1)
            ->setLogonDate(new \DateTime('now -110 days +1 hour'));
        $member4 = (new CorporationMember())->setId(40)->setName('Member 4')->setCorporation($corp2)
            ->setLogonDate(new \DateTime('now -111 days +1 hour'));

        self::$em->persist($player1);
        self::$em->persist($char1);
        self::$em->persist($char2);
        self::$em->persist($corp1);
        self::$em->persist($corp2);
        self::$em->persist($member1);
        self::$em->persist($member2);
        self::$em->persist($member3);
        self::$em->persist($member4);

        self::$em->flush();
    }

    public function testResetCriteria()
    {
        $repository = (new RepositoryFactory(self::$em))->getCorporationMemberRepository();

        $repository->setAccount(false);
        $repository->setActive(110);
        $repository->setInactive(110);
        $repository->setValidToken(false);
        $repository->setTokenChanged(100);
        $this->assertSame(0, count($repository->findMatching(1)));

        $repository->resetCriteria();

        $this->assertSame(3, count($repository->findMatching(1)));
    }

    public function testFindMatchingActive()
    {
        $repository = (new RepositoryFactory(self::$em))->getCorporationMemberRepository();

        $actual1 = $repository->setActive(109)->findMatching(1);
        $actual2 = $repository->setActive(110)->findMatching(1);
        $actual3 = $repository->setActive(112)->findMatching(1);

        $this->assertSame(0, count($actual1));
        $this->assertSame(1, count($actual2));
        $this->assertSame(3, count($actual3));

        $this->assertSame('Member 3', $actual2[0]->getName());
        $this->assertSame('Member 3', $actual3[0]->getName());
        $this->assertSame('Member 2', $actual3[1]->getName());
        $this->assertSame('Member 1', $actual3[2]->getName());
        $this->assertSame('Corp 1', $actual2[0]->getCorporation()->getName());
        $this->assertSame('Corp 1', $actual3[0]->getCorporation()->getName());
        $this->assertSame('Corp 1', $actual3[1]->getCorporation()->getName());
    }

    public function testFindMatchingInactive()
    {
        $repository = (new RepositoryFactory(self::$em))->getCorporationMemberRepository();

        $actual1 = $repository->setInactive(109)->findMatching(1);
        $actual2 = $repository->setInactive(110)->findMatching(1);
        $actual3 = $repository->setInactive(112)->findMatching(1);

        $this->assertSame(3, count($actual1));
        $this->assertSame(2, count($actual2));
        $this->assertSame(0, count($actual3));

        $this->assertSame('Member 3', $actual1[0]->getName());
        $this->assertSame('Member 2', $actual1[1]->getName());
        $this->assertSame('Member 1', $actual1[2]->getName());
        $this->assertSame('Member 2', $actual2[0]->getName());
        $this->assertSame('Member 1', $actual2[1]->getName());
        $this->assertSame('Corp 1', $actual1[0]->getCorporation()->getName());
        $this->assertSame('Corp 1', $actual1[1]->getCorporation()->getName());
        $this->assertSame('Corp 1', $actual2[0]->getCorporation()->getName());
    }

    public function testFindMatchingActiveRange()
    {
        $repository = (new RepositoryFactory(self::$em))->getCorporationMemberRepository();

        $actual = $repository->setInactive(110)->setActive(111)->findMatching(1);

        $this->assertSame(1, count($actual));

        $this->assertSame('Member 2', $actual[0]->getName());
        $this->assertSame('Corp 1', $actual[0]->getCorporation()->getName());
    }

    public function testFindMatchingWithOutAccount()
    {
        $repository = (new RepositoryFactory(self::$em))->getCorporationMemberRepository();

        $actual1 = $repository->setAccount(true)->findMatching(1);
        $actual2 = $repository->setAccount(false)->findMatching(1);

        $this->assertSame(2, count($actual1));
        $this->assertSame(1, count($actual2));

        $this->assertSame('Member 2', $actual1[0]->getName());
        $this->assertSame('Member 1', $actual1[1]->getName());
        $this->assertSame('Char 2', $actual1[0]->getCharacter()->getName());
        $this->assertSame('Char 1', $actual1[1]->getCharacter()->getName());
        $this->assertSame('Member 3', $actual2[0]->getName());
    }

    public function testFindMatchingWithToken()
    {
        $repository = (new RepositoryFactory(self::$em))->getCorporationMemberRepository();

        $actual1 = $repository->setValidToken(true)->findMatching(1);
        $actual2 = $repository->setValidToken(false)->findMatching(1);

        $this->assertSame(1, count($actual1));
        $this->assertSame(1, count($actual2));

        $this->assertSame('Char 1', $actual1[0]->getCharacter()->getName());
        $this->assertSame('Char 2', $actual2[0]->getCharacter()->getName());
    }

    public function testFindMatchingTokenChanged()
    {
        $repository = (new RepositoryFactory(self::$em))->getCorporationMemberRepository();

        $actual = $repository->setTokenChanged(9)->findMatching(1);

        $this->assertSame(1, count($actual));
        $this->assertSame('Char 2', $actual[0]->getCharacter()->getName());
    }
}
