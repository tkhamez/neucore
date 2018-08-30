<?php declare(strict_types=1);

namespace Tests\Unit\Core\Entity;

use Brave\Core\Entity\Corporation;
use Brave\Core\Repository\CorporationRepository;
use Brave\Core\Entity\Group;
use Tests\Helper;

class CorporationRepositoryTest extends \PHPUnit\Framework\TestCase
{
    public function testConstruct()
    {
        $em = (new Helper())->getEm();
        $r = new CorporationRepository($em);
        $this->assertInstanceOf('Doctrine\ORM\EntityRepository', $r);
    }

    public function testGetAllWithGroups()
    {
        // setup

        $h = new Helper();
        $h->emptyDb();
        $em = $h->getEm();
        $r = new CorporationRepository($em);

        $corp1 = (new Corporation())->setId(111)->setTicker('t1')->setName('corp 1');
        $corp2 = (new Corporation())->setId(222)->setTicker('t2')->setName('corp 2');
        $corp3 = (new Corporation())->setId(333)->setTicker('t3')->setName('corp 3');

        $group1 = (new Group())->setName('group 1');
        $group2 = (new Group())->setName('group 2');

        $corp2->addGroup($group1);
        $corp2->addGroup($group2);
        $corp3->addGroup($group1);

        $em->persist($corp1);
        $em->persist($corp2);
        $em->persist($corp3);
        $em->persist($group1);
        $em->persist($group2);

        $em->flush();

        // test

        $actual = $r->getAllWithGroups();

        $this->assertSame(2, count($actual));
        $this->assertSame(222, $actual[0]->getId());
        $this->assertSame(333, $actual[1]->getId());
    }
}
