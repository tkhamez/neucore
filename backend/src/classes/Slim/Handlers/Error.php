<?php declare(strict_types=1);

namespace Neucore\Slim\Handlers;

use Psr\Log\LoggerInterface;

/**
 * Extends Slim's error handler to add a logger.
 */
class Error extends \Slim\Handlers\Error
{
    protected $logger;

    public function __construct($displayErrorDetails, LoggerInterface $logger)
    {
        $this->logger = $logger;

        parent::__construct($displayErrorDetails);
    }

    /**
     * @param \Exception $exception
     */
    protected function writeToErrorLog($exception)
    {
        $this->logger->critical($exception->getMessage(), ['exception' => $exception]);
    }
}
