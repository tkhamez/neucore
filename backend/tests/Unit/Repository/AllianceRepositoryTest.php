<?php
/** @noinspection DuplicatedCode */

declare(strict_types=1);

namespace Tests\Unit\Repository;

use Doctrine\Persistence\ObjectManager;
use Neucore\Factory\RepositoryFactory;
use Neucore\Repository\AllianceRepository;
use PHPUnit\Framework\TestCase;
use Tests\Helper;
use Neucore\Entity\Alliance;
use Neucore\Entity\Group;

class AllianceRepositoryTest extends TestCase
{
    private ObjectManager $om;

    private AllianceRepository $repository;

    protected function setUp(): void
    {
        $helper = new Helper();
        $helper->emptyDb();
        $this->om = $helper->getObjectManager();
        $this->repository = (new RepositoryFactory($this->om))->getAllianceRepository();
    }

    public function testGetAllWithGroups()
    {
        $alli1 = (new Alliance())->setId(111)->setTicker('a1')->setName('alli 1');
        $alli2 = (new Alliance())->setId(222)->setTicker('a2')->setName('alli 2');
        $alli3 = (new Alliance())->setId(333)->setTicker('a3')->setName('alli 3');

        $group1 = (new Group())->setName('group 1');
        $group2 = (new Group())->setName('group 2');

        $alli2->addGroup($group1);
        $alli2->addGroup($group2);
        $alli3->addGroup($group1);

        $this->om->persist($alli1);
        $this->om->persist($alli2);
        $this->om->persist($alli3);
        $this->om->persist($group1);
        $this->om->persist($group2);

        $this->om->flush();

        // test

        $actual = $this->repository->getAllWithGroups();

        $this->assertSame(2, count($actual));
        $this->assertSame(222, $actual[0]->getId());
        $this->assertSame(333, $actual[1]->getId());
    }

    public function testFindByNameOrTickerPartialMatch()
    {
        $alli3 = (new Alliance())->setId(55)->setTicker('t300')->setName('company 3');
        $alli2 = (new Alliance())->setId(56)->setTicker('t200')->setName('alli 2');
        $alli1 = (new Alliance())->setId(57)->setTicker('t100')->setName('alli 1');
        $this->om->persist($alli3);
        $this->om->persist($alli2);
        $this->om->persist($alli1);
        $this->om->flush();

        $actual1 = $this->repository->findByNameOrTickerPartialMatch('lli');
        $this->assertSame(2, count($actual1));
        $this->assertSame('alli 1', $actual1[0]->getName());
        $this->assertSame('alli 2', $actual1[1]->getName());
        $this->assertSame(57, $actual1[0]->getID());
        $this->assertSame(56, $actual1[1]->getID());

        $actual2 = $this->repository->findByNameOrTickerPartialMatch('100');
        $this->assertSame(1, count($actual2));
        $this->assertSame('alli 1', $actual2[0]->getName());
        $this->assertSame(57, $actual2[0]->getID());
    }
}
