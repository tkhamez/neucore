<?php

declare(strict_types=1);

namespace Tests\Unit\Data;

use Neucore\Data\ServiceAccount;
use Neucore\Plugin\ServiceAccountData;
use PHPUnit\Framework\TestCase;

class ServiceAccountTest extends TestCase
{
    public function testJsonSerialize()
    {
        $obj = new ServiceAccount(1, 'name', 100, 'username', ServiceAccountData::STATUS_PENDING, 'name');

        $this->assertSame(
            [
                'serviceId' => 1,
                'serviceName' => 'name',
                'characterId' => 100,
                'username' => 'username',
                'status' => ServiceAccountData::STATUS_PENDING,
                'name' => 'name'
            ],
            $obj->jsonSerialize()
        );
    }
}
