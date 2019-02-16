<?php declare(strict_types=1);

namespace Tests\Unit\Core\Repository;

use Brave\Core\Entity\Corporation;
use Brave\Core\Entity\CorporationMember;
use Brave\Core\Factory\RepositoryFactory;
use Tests\Helper;

class CorporationMemberRepositoryTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var \Doctrine\ORM\EntityManagerInterface
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

        $corp1 = (new Corporation())->setId(1)->setName('Corp 1')->setTicker('C1');
        $corp2 = (new Corporation())->setId(2)->setName('Corp 2')->setTicker('C2');
        $member1 = (new CorporationMember())->setId(10)->setName('Member 1')->setCorporation($corp1)
            ->setLogonDate(new \DateTime('now -112 days +1 hour'));
        $member2 = (new CorporationMember())->setId(20)->setName('Member 2')->setCorporation($corp1)
            ->setLogonDate(new \DateTime('now -111 days +1 hour'));
        $member3 = (new CorporationMember())->setId(30)->setName('Member 3')->setCorporation($corp1)
            ->setLogonDate(new \DateTime('now -110 days +1 hour'));
        $member4 = (new CorporationMember())->setId(40)->setName('Member 4')->setCorporation($corp2)
            ->setLogonDate(new \DateTime('now -111 days +1 hour'));

        self::$em->persist($corp1);
        self::$em->persist($corp2);
        self::$em->persist($member1);
        self::$em->persist($member2);
        self::$em->persist($member3);
        self::$em->persist($member4);

        self::$em->flush();
    }

    public function testFindByLogonDateActive()
    {
        $repository = (new RepositoryFactory(self::$em))->getCorporationMemberRepository();

        $actual1 = $repository->findByLogonDate(1, null, 109);
        $actual2 = $repository->findByLogonDate(1, null, 110);
        $actual3 = $repository->findByLogonDate(1, null, 112);

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

    public function testFindByLogonDateInactive()
    {
        $repository = (new RepositoryFactory(self::$em))->getCorporationMemberRepository();

        $actual1 = $repository->findByLogonDate(1, 109);
        $actual2 = $repository->findByLogonDate(1, 110);
        $actual3 = $repository->findByLogonDate(1, 112);

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

    public function testFindByLogonDateActiveRange()
    {
        $repository = (new RepositoryFactory(self::$em))->getCorporationMemberRepository();

        $actual = $repository->findByLogonDate(1, 110, 111);

        $this->assertSame(1, count($actual));

        $this->assertSame('Member 2', $actual[0]->getName());
        $this->assertSame('Corp 1', $actual[0]->getCorporation()->getName());
    }
}
