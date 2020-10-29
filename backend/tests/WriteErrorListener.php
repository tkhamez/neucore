<?php

declare(strict_types=1);

namespace Tests;

class WriteErrorListener
{
    /**
     * @throws \Exception
     */
    public function onFlush(): void
    {
        throw new \Exception('error');
    }
}
