<?php

declare(strict_types=1);

namespace Neucore\Service;

use Doctrine\Persistence\ObjectManagerDecorator;
use Exception;
use Neucore\Log\Context;
use Psr\Log\LoggerInterface;

/**
 * @psalm-suppress MissingTemplateParam
 */
class ObjectManager extends ObjectManagerDecorator
{
    private LoggerInterface $log;

    public function __construct(\Doctrine\Persistence\ObjectManager $wrapped, LoggerInterface $log)
    {
        /* @phan-suppress-next-line PhanTypeMismatchProperty */
        $this->wrapped = $wrapped;
        $this->log = $log;
    }

    public function isUninitializedObject(mixed $value): bool
    {
        return $this->wrapped->isUninitializedObject($value);
    }

    /**
     * @return bool
     */
    public function flush(): bool
    {
        try {
            parent::flush();
        } catch (Exception $e) {
            $this->log->critical($e->getMessage(), [Context::EXCEPTION => $e]);
            return false;
        }

        return true;
    }
}
