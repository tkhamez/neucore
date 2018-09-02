<?php declare(strict_types=1);

namespace Tests;

class WriteErrorListener
{
    /**
     * @param \Doctrine\ORM\Event\OnFlushEventArgs $eventArgs
     * @throws \Exception
     */
    public function onFlush(\Doctrine\ORM\Event\OnFlushEventArgs $eventArgs)
    {
        throw new \Exception('error');
    }
}
