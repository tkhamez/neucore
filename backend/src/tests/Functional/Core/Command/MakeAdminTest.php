<?php declare(strict_types=1);

namespace Tests\Functional\Core\Command;

use Brave\Core\Entity\Character;
use Brave\Core\Entity\Role;
use Brave\Core\Factory\RepositoryFactory;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Tests\Functional\ConsoleTestCase;
use Tests\Helper;
use Tests\Logger;
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
            Role::APP,
            Role::APP_ADMIN,
            Role::APP_MANAGER,
            Role::GROUP_ADMIN,
            Role::GROUP_MANAGER,
            Role::USER_ADMIN,
            Role::ESI,
            Role::SETTINGS,
            Role::TRACKING,
        ]);
        $h->addCharacterMain('Admin', 1234, [Role::USER, Role::APP_ADMIN]);

        self::$em = $h->getEm();

        $char = (new Character())->setId(666)->setName('Orphan');
        self::$em->persist($char);
        self::$em->flush();
    }

    public function testExecute()
    {
        $output = $this->runConsoleApp('make-admin', ['id' => 1234]);
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

    public function testExecuteCharWithoutPlayer()
    {
        $output = $this->runConsoleApp('make-admin', ['id' => 666]);

        $this->assertSame('Player not found for character.'."\n", $output);
    }

    public function testExecuteException()
    {
        $em = (new Helper())->getEm(true);
        $em->getEventManager()->addEventListener(\Doctrine\ORM\Events::onFlush, new WriteErrorListener());

        $log = new Logger('Test');

        $output = $this->runConsoleApp('make-admin', ['id' => 1234], [
            EntityManagerInterface::class => $em,
            LoggerInterface::class => $log
        ]);

        $this->assertSame('', $output);
        $this->assertSame('error', $log->getHandler()->getRecords()[0]['message']);
    }
}
