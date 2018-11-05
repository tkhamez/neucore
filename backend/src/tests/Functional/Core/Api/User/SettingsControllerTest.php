<?php declare(strict_types=1);

namespace Tests\Functional\Core\Api\User;

use Brave\Core\Entity\SystemVariable;
use Brave\Core\Factory\RepositoryFactory;
use Brave\Core\Repository\SystemVariableRepository;
use Brave\Core\Roles;
use Brave\Core\Variables;
use Tests\Functional\WebTestCase;
use Tests\Helper;

class SettingsControllerTest extends WebTestCase
{
    /**
     * @var Helper
     */
    private $helper;

    /**
     * @var \Doctrine\ORM\EntityManagerInterface
     */
    private $em;

    /**
     * @var SystemVariableRepository
     */
    private $systemVariableRepository;

    public function setUp()
    {
        $_SESSION = null;

        $this->helper = new Helper();
        $this->em = $this->helper->getEm();
        $this->systemVariableRepository = (new RepositoryFactory($this->em))->getSystemVariableRepository();
    }

    public function testSystemList403()
    {
        $response1 = $this->runApp('GET', '/api/user/settings/system/list');
        $this->assertEquals(403, $response1->getStatusCode());
    }

    public function testSystemList200()
    {
        $this->setupDb();
        $this->loginUser(5); // role: USER

        $response = $this->runApp('GET', '/api/user/settings/system/list');
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertSame([
            ['name' => Variables::ALLOW_CHARACTER_DELETION, 'value' => '0'],
            ['name' => Variables::GROUPS_REQUIRE_VALID_TOKEN, 'value' => '1'],
            ['name' => Variables::SHOW_PREVIEW_BANNER, 'value' => '0'],
        ], $this->parseJsonBody($response)
        );
    }

    public function testSystemChange403()
    {
        $response1 = $this->runApp(
            'PUT',
            '/api/user/settings/system/change/'.Variables::ALLOW_CHARACTER_DELETION,
            ['value' => '1']
        );
        $this->assertEquals(403, $response1->getStatusCode());

        $this->setupDb();
        $this->loginUser(5); // role: USER

        $response2 = $this->runApp(
            'PUT',
            '/api/user/settings/system/change/'.Variables::ALLOW_CHARACTER_DELETION,
            ['value' => '1']
        );
        $this->assertEquals(403, $response2->getStatusCode());
    }

    public function testSystemChange404()
    {
        $this->setupDb();
        $this->loginUser(6); // role: SETTINGS

        $response2 = $this->runApp(
            'PUT',
            '/api/user/settings/system/change/invalid-name',
            ['value' => '1']
        );
        $this->assertEquals(404, $response2->getStatusCode());
    }

    public function testSystemChange200()
    {
        $this->setupDb();
        $this->loginUser(6); // role: SETTINGS

        $response = $this->runApp(
            'PUT',
            '/api/user/settings/system/change/'.Variables::ALLOW_CHARACTER_DELETION,
            ['value' => '1']
        );
        $this->assertEquals(200, $response->getStatusCode());

        $this->assertSame(
            ['name' => Variables::ALLOW_CHARACTER_DELETION, 'value' => '1'],
            $this->parseJsonBody($response)
        );

        $this->em->clear();
        $changed = $this->systemVariableRepository->find(Variables::ALLOW_CHARACTER_DELETION);
        $this->assertSame("1", $changed->getValue());
    }

    private function setupDb()
    {
        $this->helper->emptyDb();

        $this->helper->addCharacterMain('User', 5, [Roles::USER]);
        $this->helper->addCharacterMain('Admin', 6, [Roles::SETTINGS]);

        $var1 = new SystemVariable(Variables::ALLOW_CHARACTER_DELETION);
        $var2 = new SystemVariable(Variables::GROUPS_REQUIRE_VALID_TOKEN);
        $var3 = new SystemVariable(Variables::SHOW_PREVIEW_BANNER);

        $var1->setValue("0");
        $var2->setValue("1");
        $var3->setValue("0");

        $this->em->persist($var1);
        $this->em->persist($var2);
        $this->em->persist($var3);

        $this->em->flush();
    }
}
