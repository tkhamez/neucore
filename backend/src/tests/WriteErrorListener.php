<?php
namespace Tests;

class WriteErrorListener
{
    public function onFlush(\Doctrine\ORM\Event\OnFlushEventArgs $eventArgs)
    {
        throw new \Exception('error');
    }
}
