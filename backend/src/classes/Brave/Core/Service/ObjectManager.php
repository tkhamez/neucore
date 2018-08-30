<?php declare(strict_types=1);

namespace Brave\Core\Service;

use Doctrine\ORM\Decorator\EntityManagerDecorator;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class ObjectManager extends EntityManagerDecorator
{
    /**
     * @var LoggerInterface
     */
    private $log;

    public function __construct(EntityManagerInterface $wrapped, LoggerInterface $log)
    {
        parent::__construct($wrapped);
        $this->log = $log;
    }

    public function flush($entity = null): bool
    {
        try {
            parent::flush($entity);
        } catch (\Exception $e) {
            $this->log->critical($e->getMessage(), ['exception' => $e]);
            return false;
        }

        return true;
    }
}
