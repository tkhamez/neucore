<?php

declare(strict_types=1);

namespace Tests\Functional\Command;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectManager;
use Neucore\Entity\Role;
use Neucore\Factory\RepositoryFactory;
use Doctrine\ORM\Events;
use Psr\Log\LoggerInterface;
use Tests\Functional\ConsoleTestCase;
use Tests\Helper;
use Tests\Logger;
use Tests\WriteErrorListener;

class MakeAdminTest extends ConsoleTestCase
{
    private static EntityManagerInterface $em;

    private static WriteErrorListener $writeErrorListener;

    private static int $playerId;

    public static function setupBeforeClass(): void
    {
        $h = new Helper();
        $h->emptyDb();

        $h->addRoles([
            Role::APP,
            Role::USER_ADMIN,
            Role::USER_MANAGER,
            Role::USER_CHARS,
            Role::APP_ADMIN,
            Role::APP_MANAGER,
            Role::GROUP_ADMIN,
            Role::PLUGIN_ADMIN,
            Role::STATISTICS,
            Role::GROUP_MANAGER,
            Role::ESI,
            Role::SETTINGS,
            Role::TRACKING,
            Role::TRACKING_ADMIN,
            Role::WATCHLIST,
            Role::WATCHLIST_ADMIN,
            Role::WATCHLIST_MANAGER,
        ]);
        self::$playerId = $h->addCharacterMain('Admin', 1234, [Role::USER, Role::APP_ADMIN])->getPlayer()->getId();

        self::$em = $h->getEm();
        self::$writeErrorListener = new WriteErrorListener();
    }

    public function tearDown(): void
    {
        self::$em->getEventManager()->removeEventListener(Events::onFlush, self::$writeErrorListener);
    }

    public function testExecute()
    {
        $output = $this->runConsoleApp('make-admin', ['id' => self::$playerId]);
        self::$em->clear();

        $this->assertSame('Added all applicable roles to the player account "Admin"'."\n", $output);

        $expected = [
            Role::APP_ADMIN,
            Role::ESI,
            Role::GROUP_ADMIN,
            Role::PLUGIN_ADMIN,
            Role::SETTINGS,
            Role::STATISTICS,
            Role::TRACKING_ADMIN,
            Role::USER,
            Role::USER_ADMIN,
            Role::USER_CHARS,
            Role::USER_MANAGER,
            Role::WATCHLIST_ADMIN,
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
        self::$em->getEventManager()->addEventListener(Events::onFlush, self::$writeErrorListener);

        $log = new Logger();

        $output = $this->runConsoleApp('make-admin', ['id' => self::$playerId], [
            ObjectManager::class => self::$em,
            LoggerInterface::class => $log
        ]);

        $this->assertSame('', $output);
        $this->assertSame('error', $log->getHandler()->getRecords()[0]['message']);
    }
}
