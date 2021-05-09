<?php

declare(strict_types=1);

namespace Neucore;

use OpenApi\Annotations as OA;

/**
 * @OA\OpenApi(
 *     @OA\Info(
 *         title="Neucore API",
 *         description="Client library of Neucore API",
 *         version=NEUCORE_VERSION
 *     ),
 *     @OA\Server(
 *         url="https://localhost/api"
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="ServiceAccount",
 *     required={"serviceId", "serviceName", "characterId", "username", "status"},
 *     @OA\Property(property="serviceId", type="integer"),
 *     @OA\Property(property="serviceName", type="string", nullable=true),
 *     @OA\Property(property="characterId", type="integer", format="int64"),
 *     @OA\Property(property="username", type="string", nullable=true),
 *     @OA\Property(property="status", type="string", nullable=true,
 *                  enum={"Pending", "Active", "Deactivated", "Unknown"})
 * )
 */
class Api
{
    public const DATE_FORMAT = 'Y-m-d\TH:i:s\Z';

    public const SCOPE_MAIL = 'esi-mail.send_mail.v1';
    public const SCOPE_ROLES = 'esi-characters.read_corporation_roles.v1';
    public const SCOPE_TRACKING = 'esi-corporations.track_members.v1';
    public const SCOPE_STRUCTURES = 'esi-universe.read_structures.v1';
    public const SCOPE_MEMBERSHIP = 'esi-corporations.read_corporation_membership.v1';

    public const MAIL_OK = 'OK';
    public const MAIL_ERROR_BLOCKED = 'Blocked';
    public const MAIL_ERROR_CSPA = 'CSPA charge > 0'; // CSPA = CONCORD Spam Protection Act
}
