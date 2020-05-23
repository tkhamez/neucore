<?php

namespace Tests\Functional\Command;

use Neucore\Factory\RepositoryFactory;
use Tests\Functional\ConsoleTestCase;
use Tests\Helper;

class AssureMainTest extends ConsoleTestCase
{
    public function testExecute()
    {
        $helper = new Helper();
        $helper->emptyDb();
        $om = $helper->getObjectManager();

        $main1 = $helper->addCharacterMain("Char 1a", 110)->setMain(true);
        $helper->addCharacterToPlayer("Char 1b", 111, $main1->getPlayer());

        $char2 = $helper->addCharacterMain("Char 2a", 120)->setMain(true)->setMain(false)
            ->setCreated(new \DateTime('2020-05-23 17:41:12'));
        $helper->addCharacterToPlayer("Char 2b", 121, $char2->getPlayer())
            ->setCreated(new \DateTime('2020-05-23 16:41:12'));

        $char3 = $helper->addCharacterMain("Char 3a", 130)->setMain(true)->setMain(false)
            ->setCreated(new \DateTime('2020-05-23 16:41:12'));
        $helper->addCharacterToPlayer("Char 3b", 131, $char3->getPlayer())
            ->setCreated(new \DateTime('2020-05-23 17:41:12'));
        $om->flush();

        $output = $this->runConsoleApp('assure-main', ['--db-result-limit' => 2]);
        $om->clear();

        $actual = explode("\n", $output);
        $this->assertSame(5, count($actual));
        $this->assertSame('Started "assure-main"', $actual[0]);
        $this->assertSame('  processed 1 in iteration 1', $actual[1]);
        $this->assertSame('  processed 1 in iteration 2', $actual[2]);
        $this->assertSame('Finished "assure-main"', $actual[3]);
        $this->assertSame('', $actual[4]);

        $chars = (new RepositoryFactory($om))->getCharacterRepository()->findBy([]);
        $this->assertSame(6, count($chars));

        $this->assertSame('Char 1a', $chars[0]->getPlayer()->getName());
        $this->assertSame(110, $chars[0]->getId());
        $this->assertTrue($chars[0]->getMain());
        $this->assertNull($chars[0]->getCreated());
        $this->assertSame(111, $chars[1]->getId());
        $this->assertFalse($chars[1]->getMain());
        $this->assertNull($chars[1]->getCreated());

        $this->assertSame('Char 2b', $chars[2]->getPlayer()->getName());
        $this->assertSame(120, $chars[2]->getId());
        $this->assertFalse($chars[2]->getMain());
        $this->assertSame('2020-05-23 17:41:12', $chars[2]->getCreated()->format('Y-m-d H:i:s'));
        $this->assertSame(121, $chars[3]->getId());
        $this->assertTrue($chars[3]->getMain());
        $this->assertSame('2020-05-23 16:41:12', $chars[3]->getCreated()->format('Y-m-d H:i:s'));

        $this->assertSame('Char 3a', $chars[4]->getPlayer()->getName());
        $this->assertSame(130, $chars[4]->getId());
        $this->assertTrue($chars[4]->getMain());
        $this->assertSame('2020-05-23 16:41:12', $chars[4]->getCreated()->format('Y-m-d H:i:s'));
        $this->assertSame(131, $chars[5]->getId());
        $this->assertFalse($chars[5]->getMain());
        $this->assertSame('2020-05-23 17:41:12', $chars[5]->getCreated()->format('Y-m-d H:i:s'));
    }
}
