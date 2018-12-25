<?php declare(strict_types=1);

namespace Tests\Unit\Core\Repository;

use Brave\Core\Entity\Corporation;
use Brave\Core\Entity\CorporationMember;
use Brave\Core\Entity\Group;
use Brave\Core\Factory\RepositoryFactory;
use Brave\Core\Repository\CorporationRepository;
use Tests\Helper;

class CorporationRepositoryTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var \Doctrine\ORM\EntityManagerInterface
     */
    private $em;

    /**
     * @var CorporationRepository
     */
    private $repository;

    public function setUp()
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
        $member2 = (new CorporationMember())->setId(101)->setName('member 2');
        $this->em->persist($corp1);
        $this->em->persist($corp2);
        $this->em->persist($member1);
        $this->em->persist($member2);
        $this->em->flush();

        $actual = $this->repository->getAllWithMemberTrackingData();
        $this->assertSame(1, count($actual));
        $this->assertSame(10, $actual[0]->getId());
    }
}
