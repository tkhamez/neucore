<?php declare(strict_types=1);

namespace Neucore\Middleware\Guzzle;

use Neucore\Entity\SystemVariable;
use Neucore\Factory\RepositoryFactory;
use Neucore\Repository\SystemVariableRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

class EsiHeaders
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var SystemVariableRepository
     */
    private $systemVariableRepository;

    /**
     * @var EntityManagerInterface
     */
    private $em;

    public function __construct(
        LoggerInterface $logger,
        RepositoryFactory $repositoryFactory,
        EntityManagerInterface $em
    ) {
        $this->logger = $logger;
        $this->systemVariableRepository = $repositoryFactory->getSystemVariableRepository();
        $this->em = $em;
    }

    public function __invoke(callable $handler)
    {
        return function (RequestInterface $request, array $options) use ($handler) {
            $requestUri = $request->getUri()->__toString();

            return $handler($request, $options)->then(
                function (ResponseInterface $response) use ($requestUri) {
                    $this->handleResponseHeaders($requestUri, $response);
                    return $response;
                }
            );
        };
    }

    private function handleResponseHeaders(string $requestUri, ResponseInterface $response)
    {
        /// update ESI error limit
        if ($response->hasHeader('X-Esi-Error-Limit-Remain') && $response->hasHeader('X-Esi-Error-Limit-Reset')) {
            $remain = (int) $response->getHeader('X-Esi-Error-Limit-Remain')[0];
            $reset = (int) $response->getHeader('X-Esi-Error-Limit-Reset')[0];

            $entity = $this->systemVariableRepository->find(SystemVariable::ESI_ERROR_LIMIT);
            if (! $entity) {
                $this->logger->error(
                    'EsiHeaders::handleResponseHeaders: system variable' .
                    SystemVariable::ESI_ERROR_LIMIT . ' not found.'
                );
                return;
            }
            $entity->setValue((string) \json_encode([
                'updated' => time(),
                'remain' => $remain,
                'reset' => $reset,
            ]));
            $this->em->persist($entity);
            $this->em->flush();
        }

        // log deprecated warnings
        if ($response->hasHeader('warning')) {
            $warning = $response->getHeader('warning')[0];
            if (strpos($warning, '299') !== false) { // i. e. "299 - This route is deprecated"
                $this->logger->warning($requestUri . ': ' .$warning);
            }
        }
    }
}
