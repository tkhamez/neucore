<?php

declare(strict_types=1);

namespace Neucore\Storage;

/**
 * A variable for the volatile storage.
 *
 * Depending on the storage, these are saved as a SystemVariable or in APCu (preferred).
 *
 * @see \Neucore\Entity\SystemVariable
 */
class Variables
{
    /**
     * Time, remain and reset from X-Esi-Error-Limit-* HTTP headers.
     */
    const ESI_ERROR_LIMIT = 'esi_error_limit';

    /**
     * https://github.com/esi/esi-issues/issues/1227
     */
    const ESI_THROTTLED = 'esi_throttled';

    const API_RATE_LIMIT = 'api_rate_limit';
}
