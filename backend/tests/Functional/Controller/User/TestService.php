<?php

declare(strict_types=1);

namespace Tests\Functional\Controller\User;

use Neucore\Plugin\AccountData;
use Neucore\Plugin\ServiceInterface;

class TestService implements ServiceInterface
{
    public function getAccounts(int ...$characterIds): array
    {
        return [
            new AccountData($characterIds[0], 'u', 'p', 'e'),
            [],
            new AccountData(123456),
        ];
    }
}
