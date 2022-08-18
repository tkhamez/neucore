<?php

declare(strict_types=1);

namespace Neucore;

/* @phan-suppress-next-line PhanUnreferencedUseNormal */
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
 */
class Api
{
    public const DATE_FORMAT = 'Y-m-d\TH:i:s\Z';

    public const MAIL_OK = 'OK';
    public const MAIL_ERROR_BLOCKED = 'Blocked';
    public const MAIL_ERROR_CSPA = 'CSPA charge > 0'; // CSPA = CONCORD Spam Protection Act
}
