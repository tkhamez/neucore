<?php declare(strict_types=1);

namespace Tests\Functional\Command;

use Neucore\Entity\Character;
use Neucore\Entity\Role;
use Neucore\Factory\RepositoryFactory;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Psr\Log\LoggerInterface;
use Tests\Functional\ConsoleTestCase;
use Tests\Helper;
use Tests\Logger;
use Tests\WriteErrorListener;

class MakeAdminTest extends ConsoleTestCase
{
    /**
     * @var EntityManagerInterface
     */
    private static $em;

    private static $playerId;

    public static function setUpBeforeClass()
    {
        $h = new Helper();
        $h->emptyDb();

        $h->addRoles([
            Role::APP,
            Role::USER_ADMIN,
            Role::USER_MANAGER,
            Role::APP_ADMIN,
            Role::APP_MANAGER,
            Role::GROUP_ADMIN,
            Role::GROUP_MANAGER,
            Role::ESI,
            Role::SETTINGS,
            Role::TRACKING,
        ]);
        self::$playerId = $h->addCharacterMain('Admin', 1234, [Role::USER, Role::APP_ADMIN])->getPlayer()->getId();

        self::$em = $h->getEm();
    }

    public function testExecute()
    {
        $output = $this->runConsoleApp('make-admin', ['id' => self::$playerId]);
        self::$em->clear();

        $this->assertSame('Added all applicable roles to the player account "Admin"'."\n", $output);

        $expected = [
            Role::APP_ADMIN,
            Role::APP_MANAGER,
            Role::ESI,
            Role::GROUP_ADMIN,
            Role::GROUP_MANAGER,
            Role::SETTINGS,
            Role::TRACKING,
            Role::USER,
            Role::USER_ADMIN,
            Role::USER_MANAGER,
        ];
        $actual = (new RepositoryFactory(self::$em))
            ->getCharacterRepository()->find(1234)->getPlayer()->getRoleNames();
        $this->assertSame($expected, $actual);
    }

    public function testExecuteNotFound()
    {
        $output = $this->runConsoleApp('make-admin', ['id' => self::$playerId + 9]);

        $this->assertSame('Player with ID "' . (self::$playerId + 9) . '" not found'."\n", $output);
    }

    public function testExecuteException()
    {
        $em = (new Helper())->getEm(true);
        $em->getEventManager()->addEventListener(Events::onFlush, new WriteErrorListener());

        $log = new Logger('Test');

        $output = $this->runConsoleApp('make-admin', ['id' => self::$playerId], [
            EntityManagerInterface::class => $em,
            LoggerInterface::class => $log
        ]);

        $this->assertSame('', $output);
        $this->assertSame('error', $log->getHandler()->getRecords()[0]['message']);
    }
}
