<?php

declare(strict_types=1);

namespace Neucore\Command\Traits;

trait Argv
{
    /**
     * @var ?string[]
     */
    private ?array $argv = null;

    /**
     * @param string[] $argv
     */
    public function setArgv(array $argv): void
    {
        $this->argv = $argv;
    }
}
