<?php
/** @noinspection DuplicatedCode */

declare(strict_types=1);

namespace Tests\Unit\Repository;

use Neucore\Factory\RepositoryFactory;
use PHPUnit\Framework\TestCase;
use Tests\Helper;
use Neucore\Entity\Alliance;
use Neucore\Entity\Group;

class AllianceRepositoryTest extends TestCase
{
    public function testGetAllWithGroups()
    {
        // setup

        $h = new Helper();
        $h->emptyDb();
        $om = $h->getObjectManager();
        $r = (new RepositoryFactory($om))->getAllianceRepository();

        $alli1 = (new Alliance())->setId(111)->setTicker('a1')->setName('alli 1');
        $alli2 = (new Alliance())->setId(222)->setTicker('a2')->setName('alli 2');
        $alli3 = (new Alliance())->setId(333)->setTicker('a3')->setName('alli 3');

        $group1 = (new Group())->setName('group 1');
        $group2 = (new Group())->setName('group 2');

        $alli2->addGroup($group1);
        $alli2->addGroup($group2);
        $alli3->addGroup($group1);

        $om->persist($alli1);
        $om->persist($alli2);
        $om->persist($alli3);
        $om->persist($group1);
        $om->persist($group2);

        $om->flush();

        // test

        $actual = $r->getAllWithGroups();

        $this->assertSame(2, count($actual));
        $this->assertSame(222, $actual[0]->getId());
        $this->assertSame(333, $actual[1]->getId());
    }
}
