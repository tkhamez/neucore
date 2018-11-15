<?php declare(strict_types=1);

namespace Tests\Unit\Slim\Session;

use Brave\Slim\Session\SessionData;
use Tests\Helper;

class SessionDataTest extends \PHPUnit\Framework\TestCase
{
    public function setUp()
    {
        (new Helper())->resetSessionData();
    }

    public function testConstructWithSession()
    {
        $_SESSION = [];
        $sd = new SessionData();
        $this->assertSame([], $sd->getAll());
    }

    public function testConstructWithoutSession()
    {
        $sd = new SessionData();
        $this->assertNull($sd->getAll());
    }

    public function testGetThrowsExceptionWithoutSession()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Session not started.');

        $sd = new SessionData();
        $sd->get('k');
    }

    public function testSetThrowsExceptionForReadOnlyOrNotStartedSession()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Session is read-only or not started.');

        $sd = new SessionData();
        $sd->set('k', 'v');
    }

    public function testDeleteThrowsExceptionForReadOnlySession()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Session is read-only.');

        $sd = new SessionData();
        $sd->delete('k');
    }

    public function testClearThrowsExceptionForReadOnlySession()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Session is read-only.');

        $sd = new SessionData();
        $sd->clear();
    }

    public function testSetReadOnlyGetReadOnly()
    {
        $sd = new SessionData();
        $this->assertTrue($sd->isReadOnly());

        $sd->setReadOnly(false);
        $this->assertFalse($sd->isReadOnly());

        $sd->setReadOnly(true);
        $this->assertTrue($sd->isReadOnly());
    }

    public function testSetGetDelete()
    {
        $_SESSION = []; // "start" session
        $sd = new SessionData();
        $sd->setReadOnly(false);

        $this->assertSame(null, $sd->get('k'));

        $sd->set('k', 'v');
        $this->assertSame('v', $sd->get('k'));

        $ret1 = $sd->set('k', 'v2');
        $this->assertSame('v2', $sd->get('k'));
        $this->assertSame($sd, $ret1);

        $ret2 = $sd->delete('k');
        $this->assertSame(null, $sd->get('k'));
        $this->assertSame($sd, $ret2);
    }

    public function testGetAllClear()
    {
        $_SESSION = []; // "start" session
        $sd = new SessionData();
        $sd->setReadOnly(false);

        $sd->set('k', 'v');
        $sd->set('k2', 'v2');

        $this->assertSame(['k' => 'v', 'k2' => 'v2'], $sd->getAll());

        $ret = $sd->clear();
        $this->assertSame([], $sd->getAll());
        $this->assertSame($sd, $ret);
    }

    public function testSuperGlobalIsReference()
    {
        $_SESSION = []; // "start" session
        $sd = new SessionData();
        $sd->setReadOnly(false);

        $this->assertSame($_SESSION, $sd->getAll());

        $_SESSION['k'] = 'v';
        $this->assertSame($_SESSION, $sd->getAll());

        $sd->set('k2', 'v2');
        $this->assertSame($_SESSION, $sd->getAll());
    }
}
