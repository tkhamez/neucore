<?php

namespace Brave\Slim\Role;

use Psr\Http\Message\ServerRequestInterface;

interface RoleProviderInterface
{
    /**
     * Returns roles from an authenticated user.
     *
     * @param ServerRequestInterface $request
     *
     * @return array e .g. ['role.one', 'role.two']
     */
    public function getRoles(ServerRequestInterface $request): array;
}
