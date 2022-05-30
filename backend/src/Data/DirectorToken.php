<?php

declare(strict_types=1);

namespace Neucore\Data;

class DirectorToken
{
    public ?string $access = null;

    public ?string $refresh = null;

    public ?int $expires = null;

    /**
     * @var string[]
     */
    public array $scopes = [];

    public ?int $characterId = null;

    public ?string $systemVariableName = null;
}
