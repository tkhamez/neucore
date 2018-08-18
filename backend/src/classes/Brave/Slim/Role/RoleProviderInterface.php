<?php declare(strict_types=1);

namespace Brave\Slim\Role;

use Psr\Http\Message\ServerRequestInterface;

interface RoleProviderInterface
{

    /**
     * Returns roles from an authenticated user.
     *
     * Example: ['role.one', 'role.two']
     */
    public function getRoles(ServerRequestInterface $request): array;
}
