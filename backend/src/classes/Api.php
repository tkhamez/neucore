<?php declare(strict_types=1);

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
 */
class Api
{
    const DATE_FORMAT = 'Y-m-d\TH:i:s\Z';
}
