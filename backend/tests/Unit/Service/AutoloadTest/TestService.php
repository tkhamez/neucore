<?php

declare(strict_types=1);

namespace Tests\AutoloadTest;

use Neucore\Plugin\ServiceInterface;

class TestService implements ServiceInterface
{
    public function getAccounts(int ...$characterIds): array
    {
        return [];
    }
}
