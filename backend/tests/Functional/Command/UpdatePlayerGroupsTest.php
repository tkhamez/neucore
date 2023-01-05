<?php

declare(strict_types=1);

namespace Tests\Functional\Command;

use Neucore\Entity\Group;
use Neucore\Entity\Player;
use Neucore\Entity\Role;
use Neucore\Factory\RepositoryFactory;
use Psr\Log\LoggerInterface;
use Tests\Functional\ConsoleTestCase;
use Tests\Helper;
use Neucore\Entity\Character;
use Neucore\Entity\Corporation;
use Tests\Logger;

class UpdatePlayerGroupsTest extends ConsoleTestCase
{
    public function testExecute()
    {
        // setup
        $h = new Helper();
        $h->emptyDb();
        $h->addRoles([Role::TRACKING, Role::WATCHLIST, Role::WATCHLIST_MANAGER]);
        $om = $h->getObjectManager();

        $group = (new Group())->setName('g');
        $p1 = (new Player())->setName('p1');
        $p2 = (new Player())->setName('p2')->addGroup($group);
        $p3 = (new Player())->setName('p3')->setStatus(Player::STATUS_MANAGED);
        $corp = (new Corporation())->setId(1)->setName('corp')->setTicker('t')->addGroup($group);
        $char = (new Character())->setId(1)->setName('char')->setCharacterOwnerHash('h')
            ->setPlayer($p1)->setCorporation($corp);

        $om->persist($group);
        $om->persist($p1);
        $om->persist($p2);
        $om->persist($p3);
        $om->persist($corp);
        $om->persist($char);
        $om->flush();

        // run
        $output = $this->runConsoleApp('update-player-groups', ['--sleep' => 0], [
            LoggerInterface::class => new Logger()
        ]);

        $om->clear();

        $actual = explode("\n", $output);
        $this->assertSame(5, count($actual));
        $this->assertStringEndsWith('Started "update-player-groups"', $actual[0]);
        $this->assertStringEndsWith('  Account '.$p1->getId().' groups updated', $actual[1]);
        $this->assertStringEndsWith('  Account '.$p2->getId().' groups updated', $actual[2]);
        $this->assertStringEndsWith('Finished "update-player-groups"', $actual[3]);
        $this->assertStringEndsWith('', $actual[4]);

        # read result
        $actual = (new RepositoryFactory($om))->getPlayerRepository()->findBy([]);
        $this->assertSame($p1->getId(), $actual[0]->getId());
        $this->assertSame($p2->getId(), $actual[1]->getId());
        $this->assertNotNull($actual[0]->getLastUpdate());
        $this->assertNotNull($actual[1]->getLastUpdate());
        $this->assertSame(1, count($actual[0]->getGroups()));
        $this->assertSame(0, count($actual[1]->getGroups()));
        $this->assertSame('g', $actual[0]->getGroups()[0]->getName());
    }
}
