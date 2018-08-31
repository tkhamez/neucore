<?php declare(strict_types=1);

namespace Tests\Functional\Core\Command;

use Brave\Core\Entity\Group;
use Brave\Core\Entity\Player;
use Brave\Core\Repository\RepositoryFactory;
use Tests\Functional\ConsoleTestCase;
use Tests\Helper;
use Brave\Core\Entity\Character;
use Brave\Core\Entity\Corporation;

class UpdatePlayerGroupsTest extends ConsoleTestCase
{
    public function testExecute()
    {
        // setup
        $h = new Helper();
        $h->emptyDb();
        $em = $h->getEm();

        $group = (new Group())->setName('g');
        $p1 = (new Player())->setName('p1');
        $p2 = (new Player())->setName('p2')->addGroup($group);
        $corp = (new Corporation())->setId(1)->setName('corp')->setTicker('t')->addGroup($group);
        $char = (new Character())->setId(1)->setName('char')
            ->setCharacterOwnerHash('h')->setAccessToken('t')
            ->setPlayer($p1)->setCorporation($corp);

        $em->persist($group);
        $em->persist($p1);
        $em->persist($p2);
        $em->persist($corp);
        $em->persist($char);
        $em->flush();

        // run
        $output = $this->runConsoleApp('update-player-groups', ['--sleep' => 0]);

        $em->clear();

        $expectedOutput = [
            'Updated '.$p1->getId(),
            'Updated '.$p2->getId(),
            'All done.',
        ];
        $this->assertSame(implode("\n", $expectedOutput)."\n", $output);

        # read result
        $actual = (new RepositoryFactory($em))->getPlayerRepository()->findBy([]);
        $this->assertSame($p1->getId(), $actual[0]->getId());
        $this->assertSame($p2->getId(), $actual[1]->getId());
        $this->assertNotNull($actual[0]->getLastUpdate());
        $this->assertNotNull($actual[1]->getLastUpdate());
        $this->assertSame(1, count($actual[0]->getGroups()));
        $this->assertSame(0, count($actual[1]->getGroups()));
        $this->assertSame('g', $actual[0]->getGroups()[0]->getName());
    }
}
