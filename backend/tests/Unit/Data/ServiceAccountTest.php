<?php

declare(strict_types=1);

namespace Tests\Unit\Data;

use Neucore\Entity\Player;
use Neucore\Entity\Service;
use Neucore\Plugin\AccountData;
use Neucore\Data\ServiceAccount;
use PHPUnit\Framework\TestCase;

class ServiceAccountTest extends TestCase
{
    public function testJsonSerialize()
    {
        $service = new ServiceAccount();

        $this->assertSame(
            ['service' => null, 'player' => null, 'accountData' => []],
            json_decode((string) json_encode($service), true)
        );
    }

    public function testSetGetService()
    {
        $serviceAccount = new ServiceAccount();
        $service = new Service();
        $this->assertNull($serviceAccount->getService());
        $this->assertSame($service, $serviceAccount->setService($service)->getService());
    }

    public function testSetGetPlayer()
    {
        $serviceAccount = new ServiceAccount();
        $player = new Player();
        $this->assertNull($serviceAccount->getService());
        $this->assertSame($player, $serviceAccount->setPlayer($player)->getPlayer());
    }

    public function testSetGetData()
    {
        $serviceAccount = new ServiceAccount();
        $data = [new AccountData(1), new AccountData(2)];
        $this->assertSame([], $serviceAccount->getAccountData());
        $this->assertSame($data, $serviceAccount->setAccountData(...$data)->getAccountData());
    }
}
