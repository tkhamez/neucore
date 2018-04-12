<?php
namespace Tests\Functional\Core\Command;

use Tests\Helper;
use Tests\Functional\WebTestCase;
use Brave\Core\Entity\CharacterRepository;

class MakeAdminTest extends WebTestCase
{

    private static $em;

    public static function setUpBeforeClass()
    {
        $h = new Helper();
        $h->emptyDb();

        $h->addRoles(['app', 'app-admin', 'app-manager', 'group-admin', 'group-manager', 'user-admin']);
        $h->addCharacterMain('Admin', 1234, ['user', 'user-admin']);

        self::$em = $h->getEm();
    }

    public function testExecute()
    {
        $output = $this->runConsoleApp('make-admin', ['id' => 1234]);
        self::$em->clear();

        $this->assertSame('Added all applicable roles to the player account "Admin"'."\n", $output);

        $expected = ['app-admin', 'app-manager', 'group-admin', 'group-manager', 'user', 'user-admin'];
        $actual = (new CharacterRepository(self::$em))->find(1234)->getPlayer()->getRoleNames();
        $this->assertSame($expected, $actual);
    }

    public function testExecuteNotFound()
    {
        $output = $this->runConsoleApp('make-admin', ['id' => 5678]);

        $this->assertSame('Character with ID "5678" not found'."\n", $output);
    }
}
