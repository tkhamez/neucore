<?php

declare(strict_types=1);

namespace Neucore\Plugin;

interface ServiceInterface
{
    /**
     * @param int ...$characterIds
     * @return AccountData[]
     */
    public function getAccounts(int ...$characterIds): array;
}
