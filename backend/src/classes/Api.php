<?php declare(strict_types=1);

namespace Neucore;

use Swagger\Annotations as SWG;

/**
 * @SWG\Swagger(
 *     schemes={"https", "http"},
 *     host="localhost",
 *     basePath="/api",
 *     produces={"application/json"},
 *     @SWG\Info(
 *       title="Neucore API",
 *       description="Client library of Neucore API",
 *       version=BRAVE_CORE_VERSION
 *     )
 * )
 */
class Api
{
    const DATE_FORMAT = 'Y-m-d\TH:i:s\Z';
}
