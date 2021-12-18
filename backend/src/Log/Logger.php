<?php

declare(strict_types=1);

namespace Neucore\Log;

class Logger extends \Monolog\Logger
{
    public function addRecord(int $level, string $message, array $context = []): bool
    {
        // TODO remove once these are fixed
        if (
            (
                (
                    strpos($message, 'Kevinrob\GuzzleCache') !== false ||
                    strpos($message, 'League\OAuth2\Client') !== false ||
                    strpos($message, 'Swagger\Client\Eve') !== false
                )
                &&
                strpos($message, '#[\ReturnTypeWillChange]') !== false
            )

            ||

            // league/oauth2-client, Tool/QueryBuilderTrait
            strpos($message, 'E_DEPRECATED: http_build_query(): Passing null to parameter') !== false
        ) {
            return true; // do not log
        }

        return parent::addRecord($level, $message, $context);
    }
}
