<?php

declare(strict_types=1);

namespace Neucore;

use OpenApi\Attributes as OA;

#[OA\Info(version: NEUCORE_VERSION, description: "Client library of Neucore API", title: "Neucore API")]
#[OA\Server(url: "https://localhost/api")]
class Api
{
    public const DATE_FORMAT = 'Y-m-d\TH:i:s\Z';

    public const MAIL_OK = 'OK';
    public const MAIL_ERROR_BLOCKED = 'Blocked';
    public const MAIL_ERROR_CSPA = 'CSPA charge > 0'; // CSPA = CONCORD Spam Protection Act
}
