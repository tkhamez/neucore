<?php

declare(strict_types=1);

namespace Neucore\Slim;

use Slim\Exception\HttpMethodNotAllowedException;
use Slim\Exception\HttpNotFoundException;

/**
 * Extends Slim's error handler to adjust the log message.
 */
class ErrorHandler extends \Slim\Handlers\ErrorHandler
{
    protected function writeToErrorLog(): void
    {
        $logErrorDetails = $this->logErrorDetails;
        $additionalMessage = '';

        if (
            $this->exception instanceof HttpNotFoundException ||
            $this->exception instanceof HttpMethodNotAllowedException
        ) {
            $logErrorDetails = false;
            $additionalMessage = ' - Request: ' . $this->request->getMethod() . ' ' .
                $this->request->getUri()->getPath();
        }

        $context = [];
        if ($logErrorDetails) {
            $context['exception'] = $this->exception;
        }

        $this->logger->error($this->exception->getMessage() . $additionalMessage, $context);
    }
}
