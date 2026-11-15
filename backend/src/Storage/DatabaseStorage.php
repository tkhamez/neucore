<?php

namespace Neucore\Storage;

use Neucore\Entity\SystemVariable;
use Neucore\Exception\RuntimeException;
use Neucore\Factory\RepositoryFactory;
use Neucore\Repository\SystemVariableRepository;
use Neucore\Service\ObjectManager;

class DatabaseStorage implements EsiHeaderStorageInterface, ApiRateLimitStoreInterface
{
    public const PREFIX = '__storage__';

    protected SystemVariableRepository $systemVariableRepository;

    private ObjectManager $objectManager;

    public function __construct(RepositoryFactory $repositoryFactory, ObjectManager $objectManager)
    {
        $this->systemVariableRepository = $repositoryFactory->getSystemVariableRepository();
        $this->objectManager = $objectManager;
    }

    public function set(string $key, string $value): bool
    {
        if (mb_strlen($key) > 112) {
            throw new RuntimeException('Key too long.');
        }

        $variable = $this->systemVariableRepository->find(self::PREFIX . $key);
        if (!$variable) {
            $variable = new SystemVariable(self::PREFIX . $key);
            $variable->setScope(SystemVariable::SCOPE_BACKEND);
        }
        $variable->setValue($value);
        $this->objectManager->persist($variable);

        return $this->objectManager->flush2();
    }

    public function get(string $key): ?string
    {
        $variable = $this->systemVariableRepository->find(self::PREFIX . $key);
        return $variable?->getValue();
    }

    /**
     * Note: This is not atomic, as it is implemented with get() and set().
     * This storage is therefore not suitable for rate limiting with high traffic.
     */
    public function consume(string $key, int $maxRequests, int $resetTime): RateLimitCounter
    {
        $now = microtime(true);
        $counter = RateLimitCounter::next($this->get($key), $maxRequests, $resetTime, $now);

        $this->set($key, $counter->toJson());

        return $counter;
    }
}
