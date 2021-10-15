<?php

declare(strict_types=1);

namespace Neucore\Data;

class DirectorToken
{
    /**
     * @var string|null
     */
    public $access;

    /**
     * @var string|null
     */
    public $refresh;

    /**
     * @var int|null
     */
    public $expires;

    /**
     * @var string[]
     */
    public $scopes = [];

    /**
     * @var int|null
     */
    public $characterId;

    /**
     * @var string|null
     */
    public $systemVariableName;
}
