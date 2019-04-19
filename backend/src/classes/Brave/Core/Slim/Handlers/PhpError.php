<?php declare(strict_types=1);

namespace Brave\Core\Slim\Handlers;

use Psr\Log\LoggerInterface;

/**
 * Extends Slim's error handler to add a logger.
 */
class PhpError extends \Slim\Handlers\PhpError
{
    protected $logger;

    public function __construct($displayErrorDetails, LoggerInterface $logger)
    {
        $this->logger = $logger;

        parent::__construct($displayErrorDetails);
    }

    /**
     * @param \Throwable $error
     */
    protected function writeToErrorLog($error)
    {
        $this->logger->critical($error->getMessage(), ['exception' => $error]);
    }
}
