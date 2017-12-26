<?php
namespace Brave\Core\Handlers;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

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
        $this->logger->critical($error->getMessage());

        return parent::__invoke($request, $response, $error);
    }
}
