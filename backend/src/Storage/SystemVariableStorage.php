<?php

namespace Neucore\Storage;

use Neucore\Entity\SystemVariable;
use Neucore\Exception\RuntimeException;
use Neucore\Factory\RepositoryFactory;
use Neucore\Repository\SystemVariableRepository;
use Neucore\Service\ObjectManager;

class SystemVariableStorage implements StorageInterface
{
    public const PREFIX = '__storage__';

    /**
     * @var SystemVariableRepository
     */
    protected $systemVariableRepository;

    /**
     * @var ObjectManager
     */
    private $objectManager;

    public function __construct(RepositoryFactory $repositoryFactory, ObjectManager $objectManager)
    {
        $this->systemVariableRepository = $repositoryFactory->getSystemVariableRepository();
        $this->objectManager = $objectManager;
    }

    public function set(string $key, string $value): bool
    {
        if (mb_strlen($key) > 112 || mb_strlen($value) > 255) {
            throw new RuntimeException('String too long.');
        }

        $variable = $this->systemVariableRepository->find(self::PREFIX . $key);
        if (! $variable) {
            $variable = new SystemVariable(self::PREFIX . $key);
            $variable->setScope(SystemVariable::SCOPE_BACKEND);
        }
        $variable->setValue($value);
        $this->objectManager->persist($variable);

        return $this->objectManager->flush();
    }

    public function get(string $key): ?string
    {
        $variable = $this->systemVariableRepository->find(self::PREFIX . $key);
        if ($variable !== null) {
            return $variable->getValue();
        }
        return null;
    }
}
