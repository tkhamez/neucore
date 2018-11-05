<?php declare(strict_types=1);

namespace Brave\Core;

/**
 * Definition of all roles used for authorization.
 */
class Roles
{
    /**
     * Role for third party apps.
     *
     * @var string
     */
    const APP = 'app';

    /**
     * This role is given to unauthenticated user.
     *
     * @var string
     */
    const ANONYMOUS = 'anonymous';

    /**
     * This role is given to every authenticated user.
     *
     * @var string
     */
    const USER = 'user';

    /**
     * Allows to add and remove roles from players.
     *
     * @var string
     */
    const USER_ADMIN = 'user-admin';

    /**
     * Allows to create apps and assign them to players (managers).
     *
     * @var string
     */
    const APP_ADMIN = 'app-admin';

    /**
     * Allows a player to change the password of his apps.
     *
     * @var string
     */
    const APP_MANAGER = 'app-manager';

    /**
     * Allows to create groups and assign them to players (managers).
     *
     * @var string
     */
    const GROUP_ADMIN = 'group-admin';

    /**
     * Allows a player to add and remove players to his groups.
     *
     * @var string
     */
    const GROUP_MANAGER = 'group-manager';

    /**
     * Allows a player to make ESI request for any character in the database.
     *
     * @var string
     */
    const ESI = 'esi';

    /**
     * Allows a player to change the system settings.
     *
     * @var string
     */
    const SETTINGS = 'settings';
}
