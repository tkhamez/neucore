<?php

namespace Tests\Unit\Factory;

use Neucore\Factory\SessionHandlerFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\PdoSessionHandler;
use Tests\Helper;

class SessionHandlerFactoryTest extends TestCase
{
    public function testInvoke()
    {
        $em = (new Helper())->getEm();
        $shf = new SessionHandlerFactory($em);

        $result = $shf();

        $this->assertInstanceOf(\SessionHandlerInterface::class, $result);
        $this->assertInstanceOf(PdoSessionHandler::class, $result);
    }
}
