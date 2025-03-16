<?php

declare(strict_types=1);

namespace Neucore\Service;

use Doctrine\ORM\Decorator\EntityManagerDecorator;
use Doctrine\ORM\EntityManagerInterface;
use Neucore\Log\Context;
use Psr\Log\LoggerInterface;

class EntityManager extends EntityManagerDecorator
{
    private LoggerInterface $log;

    public function __construct(EntityManagerInterface $wrapped, LoggerInterface $log)
    {
        parent::__construct($wrapped);
        $this->log = $log;
    }

    public function isUninitializedObject(mixed $value): bool
    {
        return $this->wrapped->isUninitializedObject($value);
    }

    public function flush(): void
    {
        try {
            parent::flush();
        } catch (\Exception $e) {
            $this->log->critical($e->getMessage(), [Context::EXCEPTION => $e]);
        }
    }

    public function flush2(): bool
    {
        try {
            parent::flush();
        } catch (\Exception $e) {
            $this->log->critical($e->getMessage(), [Context::EXCEPTION => $e]);
            return false;
        }

        return true;
    }
}
