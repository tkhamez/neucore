<?php
namespace Brave\Core;

use Brave\Slim\Role\AuthRoleMiddleware;

class Roles
{
    const APP = 'app';

    const ANONYMOUS = AuthRoleMiddleware::ROLE_ANONYMOUS;

    const USER = 'user';

    const USER_ADMIN = 'user-admin';

    const APP_ADMIN = 'app-admin';

    const APP_MANAGER = 'app-manager';

    const GROUP_ADMIN = 'group-admin';

    const GROUP_MANAGER = 'group-manager';
}
