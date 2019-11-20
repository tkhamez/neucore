<?php declare(strict_types=1);

namespace Tests\Unit\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Neucore\Entity\Alliance;
use Neucore\Entity\Corporation;
use Neucore\Entity\CorporationMember;
use Neucore\Entity\Group;
use Neucore\Factory\RepositoryFactory;
use Neucore\Repository\CorporationRepository;
use PHPUnit\Framework\TestCase;
use Tests\Helper;

class CorporationRepositoryTest extends TestCase
{
    /**
     * @var EntityManagerInterface
     */
    private $em;

    /**
     * @var CorporationRepository
     */
    private $repository;

    protected function setUp(): void
    {
        $helper = new Helper();
        $helper->emptyDb();
        $this->em = $helper->getEm();
        $this->repository = (new RepositoryFactory($this->em))->getCorporationRepository();
    }

    public function testGetAllWithGroups()
    {
        $corp1 = (new Corporation())->setId(111)->setTicker('t1')->setName('corp 1');
        $corp2 = (new Corporation())->setId(222)->setTicker('t2')->setName('corp 2');
        $corp3 = (new Corporation())->setId(333)->setTicker('t3')->setName('corp 3');
        $group1 = (new Group())->setName('group 1');
        $group2 = (new Group())->setName('group 2');
        $corp2->addGroup($group1);
        $corp2->addGroup($group2);
        $corp3->addGroup($group1);
        $this->em->persist($corp1);
        $this->em->persist($corp2);
        $this->em->persist($corp3);
        $this->em->persist($group1);
        $this->em->persist($group2);
        $this->em->flush();

        $actual = $this->repository->getAllWithGroups();
        $this->assertSame(2, count($actual));
        $this->assertSame(222, $actual[0]->getId());
        $this->assertSame(333, $actual[1]->getId());
    }

    public function testGetAllWithMemberTrackingData()
    {
        $corp1 = (new Corporation())->setId(10)->setTicker('t1')->setName('corp 1');
        $corp2 = (new Corporation())->setId(11)->setTicker('t2')->setName('corp 2');
        $member1 = (new CorporationMember())->setId(100)->setName('member 1')->setCorporation($corp1);
        $member2 = (new CorporationMember())->setId(101)->setName('member 2')->setCorporation($corp1);
        $this->em->persist($corp1);
        $this->em->persist($corp2);
        $this->em->persist($member1);
        $this->em->persist($member2);
        $this->em->flush();

        $actual = $this->repository->getAllWithMemberTrackingData();
        $this->assertSame(1, count($actual));
        $this->assertSame(10, $actual[0]->getId());
    }

    public function testGetAllFromAlliances()
    {
        $alli1 = (new Alliance())->setId(11)->setTicker('a1')->setName('alli 1');
        $alli2 = (new Alliance())->setId(12)->setTicker('a2')->setName('alli 2');
        $alli3 = (new Alliance())->setId(13)->setTicker('a3')->setName('alli 3');
        $corp1a = (new Corporation())->setId(1010)->setTicker('t1a')->setName('corp 1a');
        $corp1b = (new Corporation())->setId(1011)->setTicker('t1b')->setName('corp 1b');
        $corp2 = (new Corporation())->setId(102)->setTicker('t2')->setName('corp 2');
        $corp3 = (new Corporation())->setId(103)->setTicker('t3')->setName('corp 3');
        $corp1a->setAlliance($alli1);
        $corp1b->setAlliance($alli1);
        $corp2->setAlliance($alli2);
        $corp3->setAlliance($alli3);
        $this->em->persist($alli1);
        $this->em->persist($alli2);
        $this->em->persist($alli3);
        $this->em->persist($corp1a);
        $this->em->persist($corp1b);
        $this->em->persist($corp2);
        $this->em->persist($corp3);
        $this->em->flush();

        $this->em->clear();

        $actual = $this->repository->getAllFromAlliances([11, 12]);
        $this->assertSame(3, count($actual));
        $this->assertSame(102, $actual[0]->getId());
        $this->assertSame(1010, $actual[1]->getId());
        $this->assertSame(1011, $actual[2]->getId());
    }
}
