<?php declare(strict_types=1);

namespace Tests\Functional\Core\Command;

use Brave\Core\Repository\RepositoryFactory;
use Brave\Core\Roles;
use Doctrine\ORM\EntityManagerInterface;
use Monolog\Logger;
use Monolog\Handler\TestHandler;
use Psr\Log\LoggerInterface;
use Tests\Functional\ConsoleTestCase;
use Tests\Helper;
use Tests\WriteErrorListener;

class MakeAdminTest extends ConsoleTestCase
{
    /**
     * @var \Doctrine\ORM\EntityManagerInterface
     */
    private static $em;

    public static function setUpBeforeClass()
    {
        $h = new Helper();
        $h->emptyDb();

        $h->addRoles([
            Roles::APP,
            Roles::APP_ADMIN,
            Roles::APP_MANAGER,
            Roles::GROUP_ADMIN,
            Roles::GROUP_MANAGER,
            Roles::USER_ADMIN,
            Roles::ESI,
        ]);
        $h->addCharacterMain('Admin', 1234, [Roles::USER, Roles::APP_ADMIN]);

        self::$em = $h->getEm();
    }

    public function testExecute()
    {
        $output = $this->runConsoleApp('make-admin', ['id' => 1234]);
        self::$em->clear();

        $this->assertSame('Added all applicable roles to the player account "Admin"'."\n", $output);

        $expected = [
            Roles::APP_ADMIN,
            Roles::APP_MANAGER,
            Roles::ESI,
            Roles::GROUP_ADMIN,
            Roles::GROUP_MANAGER,
            Roles::USER,
            Roles::USER_ADMIN,
        ];
        $actual = (new RepositoryFactory(self::$em))
            ->getCharacterRepository()->find(1234)->getPlayer()->getRoleNames();
        $this->assertSame($expected, $actual);
    }

    public function testExecuteNotFound()
    {
        $output = $this->runConsoleApp('make-admin', ['id' => 5678]);

        $this->assertSame('Character with ID "5678" not found'."\n", $output);
    }

    public function testExecuteException()
    {
        $em = (new Helper())->getEm(true);
        $em->getEventManager()->addEventListener(\Doctrine\ORM\Events::onFlush, new WriteErrorListener());

        $log = new Logger('Test');
        $log->pushHandler(new TestHandler());

        $output = $this->runConsoleApp('make-admin', ['id' => 1234], [
            EntityManagerInterface::class => $em,
            LoggerInterface::class => $log
        ]);

        $this->assertSame('', $output);
        $this->assertSame('error', $log->getHandlers()[0]->getRecords()[0]['message']);
    }
}
