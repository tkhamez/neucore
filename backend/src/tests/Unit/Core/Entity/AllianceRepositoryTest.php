<?php declare(strict_types=1);

namespace Tests\Unit\Core\Entity;

use Brave\Core\Repository\AllianceRepository;
use Tests\Helper;
use Brave\Core\Entity\Alliance;
use Brave\Core\Entity\Group;

class AllianceRepositoryTest extends \PHPUnit\Framework\TestCase
{
    public function testConstruct()
    {
        $em = (new Helper())->getEm();
        $r = new AllianceRepository($em);
        $this->assertInstanceOf('Doctrine\ORM\EntityRepository', $r);
    }

    public function testGetAllWithGroups()
    {
        // setup

        $h = new Helper();
        $h->emptyDb();
        $em = $h->getEm();
        $r = new AllianceRepository($em);

        $alli1 = (new Alliance())->setId(111)->setTicker('a1')->setName('alli 1');
        $alli2 = (new Alliance())->setId(222)->setTicker('a2')->setName('alli 2');
        $alli3 = (new Alliance())->setId(333)->setTicker('a3')->setName('alli 3');

        $group1 = (new Group())->setName('group 1');
        $group2 = (new Group())->setName('group 2');

        $alli2->addGroup($group1);
        $alli2->addGroup($group2);
        $alli3->addGroup($group1);

        $em->persist($alli1);
        $em->persist($alli2);
        $em->persist($alli3);
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
