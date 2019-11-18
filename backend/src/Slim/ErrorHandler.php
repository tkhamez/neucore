<?php declare(strict_types=1);

namespace Neucore\Slim;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Log\LoggerInterface;
use Slim\Exception\HttpMethodNotAllowedException;
use Slim\Exception\HttpNotFoundException;
use Slim\Interfaces\CallableResolverInterface;

/**
 * Extends Slim's error handler to add a logger.
 */
class ErrorHandler extends \Slim\Handlers\ErrorHandler
{
    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var string
     */
    protected $environment;

    public function __construct(
        CallableResolverInterface $callableResolver,
        ResponseFactoryInterface $responseFactory,
        LoggerInterface $logger,
        string $environment
    ) {
        $this->logger = $logger;
        $this->environment = $environment;

        parent::__construct($callableResolver, $responseFactory);
    }

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

        $this->logger->critical($this->exception->getMessage() . $additionalMessage, $context);
    }
}
