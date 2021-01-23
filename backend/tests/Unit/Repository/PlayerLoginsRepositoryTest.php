<?php

declare(strict_types=1);

namespace Tests\Unit\Repository;

use Neucore\Entity\Player;
use Neucore\Entity\PlayerLogins;
use Neucore\Factory\RepositoryFactory;
use PHPUnit\Framework\TestCase;
use Tests\Helper;

class PlayerLoginsRepositoryTest extends TestCase
{
    public function testMonthlySummary()
    {
        $helper = new Helper();
        $helper->emptyDb();
        $om = $helper->getObjectManager();
        $player1 = new Player();
        $player2 = new Player();
        $om->persist((new PlayerLogins())->setPlayer($player1)->setCount(1)->setYear(2019)->setMonth(12));
        $om->persist((new PlayerLogins())->setPlayer($player1)->setCount(4)->setYear(2020)->setMonth(1));
        $om->persist((new PlayerLogins())->setPlayer($player1)->setCount(1)->setYear(2020)->setMonth(2));
        $om->persist((new PlayerLogins())->setPlayer($player1)->setCount(1)->setYear(2020)->setMonth(3));
        $om->persist((new PlayerLogins())->setPlayer($player1)->setCount(1)->setYear(2020)->setMonth(4));
        $om->persist((new PlayerLogins())->setPlayer($player1)->setCount(1)->setYear(2020)->setMonth(5));
        $om->persist((new PlayerLogins())->setPlayer($player1)->setCount(1)->setYear(2020)->setMonth(6));
        $om->persist((new PlayerLogins())->setPlayer($player1)->setCount(1)->setYear(2020)->setMonth(7));
        $om->persist((new PlayerLogins())->setPlayer($player1)->setCount(1)->setYear(2020)->setMonth(8));
        $om->persist((new PlayerLogins())->setPlayer($player1)->setCount(1)->setYear(2020)->setMonth(9));
        $om->persist((new PlayerLogins())->setPlayer($player1)->setCount(1)->setYear(2020)->setMonth(10));
        $om->persist((new PlayerLogins())->setPlayer($player1)->setCount(1)->setYear(2020)->setMonth(11));
        $om->persist((new PlayerLogins())->setPlayer($player1)->setCount(8) ->setYear(2020)->setMonth(12));
        $om->persist((new PlayerLogins())->setPlayer($player1)->setCount(3)->setYear(2021)->setMonth(1));
        $om->persist((new PlayerLogins())->setPlayer($player2)->setCount(1)->setYear(2021)->setMonth(1));
        $om->persist($player1);
        $om->persist($player2);
        $om->flush();

        $this->assertSame([
            ['unique_logins' => 2, 'total_logins' => 4, 'year' => 2021, 'month' => 1],
            ['unique_logins' => 1, 'total_logins' => 8, 'year' => 2020, 'month' => 12],
            ['unique_logins' => 1, 'total_logins' => 1, 'year' => 2020, 'month' => 11],
            ['unique_logins' => 1, 'total_logins' => 1, 'year' => 2020, 'month' => 10],
            ['unique_logins' => 1, 'total_logins' => 1, 'year' => 2020, 'month' => 9],
            ['unique_logins' => 1, 'total_logins' => 1, 'year' => 2020, 'month' => 8],
            ['unique_logins' => 1, 'total_logins' => 1, 'year' => 2020, 'month' => 7],
            ['unique_logins' => 1, 'total_logins' => 1, 'year' => 2020, 'month' => 6],
            ['unique_logins' => 1, 'total_logins' => 1, 'year' => 2020, 'month' => 5],
            ['unique_logins' => 1, 'total_logins' => 1, 'year' => 2020, 'month' => 4],
            ['unique_logins' => 1, 'total_logins' => 1, 'year' => 2020, 'month' => 3],
            ['unique_logins' => 1, 'total_logins' => 1, 'year' => 2020, 'month' => 2],
            ['unique_logins' => 1, 'total_logins' => 4, 'year' => 2020, 'month' => 1],
            //['unique_logins' => 1, 'total_logins' => 1, 'year' => 2019, 'month' => 12],
        ], (new RepositoryFactory($om))->getPlayerLoginsRepository()->monthlySummary());
    }
}
