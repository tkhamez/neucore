<?php
namespace Tests\Functional\Core\Command;

use Brave\Core\Entity\CharacterRepository;
use Brave\Core\Roles;
use Tests\Helper;
use Tests\Functional\WebTestCase;

class MakeAdminTest extends WebTestCase
{

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
            Roles::USER_ADMIN
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
            Roles::GROUP_ADMIN,
            Roles::GROUP_MANAGER,
            Roles::USER,
            Roles::USER_ADMIN
        ];
        $actual = (new CharacterRepository(self::$em))->find(1234)->getPlayer()->getRoleNames();
        $this->assertSame($expected, $actual);
    }

    public function testExecuteNotFound()
    {
        $output = $this->runConsoleApp('make-admin', ['id' => 5678]);

        $this->assertSame('Character with ID "5678" not found'."\n", $output);
    }
}
