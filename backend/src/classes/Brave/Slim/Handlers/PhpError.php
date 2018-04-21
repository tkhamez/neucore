<?php declare(strict_types=1);

namespace Brave\Slim\Handlers;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
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

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, \Throwable $error)
    {
        $this->logger->critical($error->getMessage(), ['exception' => $error]);

        return parent::__invoke($request, $response, $error);
    }
}
