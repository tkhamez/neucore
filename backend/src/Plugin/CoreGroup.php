<?php

declare(strict_types=1);

namespace Neucore\Plugin;

class CoreGroup
{
    /**
     * @var int
     */
    public $identifier;

    /**
     * @var string
     */
    public $name;

    public function __construct(int $identifier, string $name)
    {
        $this->identifier = $identifier;
        $this->name = $name;
    }
}
