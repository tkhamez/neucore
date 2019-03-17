<?php declare(strict_types=1);

namespace Brave\Core\Middleware;

use Brave\Core\Entity\SystemVariable;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

class GuzzleEsiHeaders
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var EntityManagerInterface
     */
    private $em;

    private $requestUri;

    public function __construct(LoggerInterface $logger, EntityManagerInterface $em)
    {
        $this->logger = $logger;
        $this->em = $em;
    }

    public function __invoke(callable $handler)
    {
        return function (RequestInterface $request, array $options) use ($handler) {
            $this->requestUri = $request->getUri();

            return $handler($request, $options)->then(
                function (ResponseInterface $response) {
                    $this->handleResponseHeaders($response);
                    return $response;
                }
            );
        };
    }

    private function handleResponseHeaders(ResponseInterface $response)
    {
        /// update ESI error limit
        if ($response->hasHeader('X-Esi-Error-Limit-Remain') && $response->hasHeader('X-Esi-Error-Limit-Reset')) {
            $remain = (int) $response->getHeader('X-Esi-Error-Limit-Remain')[0];
            $reset = (int) $response->getHeader('X-Esi-Error-Limit-Reset')[0];

            $entity = new SystemVariable(SystemVariable::ESI_ERROR_LIMIT);
            $entity->setScope(SystemVariable::SCOPE_BACKEND);
            $entity->setValue(\json_encode([
                'updated' => time(),
                'remain' => $remain,
                'reset' => $reset,
            ]));
            $this->em->merge($entity);
            $this->em->flush();
        }

        // log deprecated warnings
        if ($response->hasHeader('warning')) {
            $warning = $response->getHeader('warning')[0];
            if (strpos($warning, '299') !== false) { // i. e. "299 - This route is deprecated"
                $this->logger->warning($this->requestUri . ': ' .$warning);
            }
        }
    }
}
