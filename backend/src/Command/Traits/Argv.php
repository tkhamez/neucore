<?php

declare(strict_types=1);

namespace Neucore\Command\Traits;

trait Argv
{
    private ?array $argv = null;

    /**
     * @param string[] $argv
     */
    public function setArgv(array $argv): void
    {
        $this->argv = $argv;
    }
}
