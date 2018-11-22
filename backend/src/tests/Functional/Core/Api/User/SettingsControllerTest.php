<?php declare(strict_types=1);

namespace Tests\Functional\Core\Api\User;

use Brave\Core\Entity\Role;
use Brave\Core\Entity\SystemVariable;
use Brave\Core\Factory\RepositoryFactory;
use Brave\Core\Repository\SystemVariableRepository;
use Tests\WebTestCase;
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

    public function testSystemList200Anonymous()
    {
        $this->setupDb();

        $response = $this->runApp('GET', '/api/user/settings/system/list');
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertSame([
            ['name' => SystemVariable::ALLOW_CHARACTER_DELETION, 'value' => '0'],
            ['name' => SystemVariable::GROUPS_REQUIRE_VALID_TOKEN, 'value' => '1'],
            ['name' => SystemVariable::SHOW_PREVIEW_BANNER, 'value' => '0'],
        ], $this->parseJsonBody($response));
    }

    public function testSystemList200Authenticated()
    {
        $this->setupDb();
        $this->loginUser(5); // role: USER

        $response = $this->runApp('GET', '/api/user/settings/system/list');
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertSame([
            ['name' => SystemVariable::ALLOW_CHARACTER_DELETION, 'value' => '0'],
            ['name' => SystemVariable::GROUPS_REQUIRE_VALID_TOKEN, 'value' => '1'],
            ['name' => SystemVariable::SHOW_PREVIEW_BANNER, 'value' => '0'],
        ], $this->parseJsonBody($response));
    }

    public function testSystemList200RoleSetting()
    {
        $this->setupDb();
        $this->loginUser(6); // roles: USER, SETTINGS

        $response = $this->runApp('GET', '/api/user/settings/system/list');
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertSame([
            ['name' => SystemVariable::ALLOW_CHARACTER_DELETION, 'value' => '0'],
            ['name' => SystemVariable::GROUPS_REQUIRE_VALID_TOKEN, 'value' => '1'],
            ['name' => SystemVariable::MAIL_CHARACTER, 'value' => 'The char'],
            ['name' => SystemVariable::SHOW_PREVIEW_BANNER, 'value' => '0'],
        ], $this->parseJsonBody($response));
    }

    public function testSystemChange403()
    {
        $response1 = $this->runApp(
            'PUT',
            '/api/user/settings/system/change/'.SystemVariable::ALLOW_CHARACTER_DELETION,
            ['value' => '1']
        );
        $this->assertEquals(403, $response1->getStatusCode());

        $this->setupDb();
        $this->loginUser(5); // role: USER

        $response2 = $this->runApp(
            'PUT',
            '/api/user/settings/system/change/'.SystemVariable::ALLOW_CHARACTER_DELETION,
            ['value' => '1']
        );
        $this->assertEquals(403, $response2->getStatusCode());
    }

    public function testSystemChange404InvalidName()
    {
        $this->setupDb();
        $this->loginUser(6); // role: SETTINGS

        $response = $this->runApp(
            'PUT',
            '/api/user/settings/system/change/' . SystemVariable::MAIL_TOKEN,
            ['value' => '']
        );
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testSystemChange404InvalidScope()
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
            '/api/user/settings/system/change/'.SystemVariable::ALLOW_CHARACTER_DELETION,
            ['value' => '1']
        );
        $this->assertEquals(200, $response->getStatusCode());

        $this->assertSame(
            ['name' => SystemVariable::ALLOW_CHARACTER_DELETION, 'value' => '1'],
            $this->parseJsonBody($response)
        );

        $this->em->clear();
        $changed = $this->systemVariableRepository->find(SystemVariable::ALLOW_CHARACTER_DELETION);
        $this->assertSame("1", $changed->getValue());
    }

    public function testSystemChange200MailCharacterIsRemoveOnlyAndAlsoRemovesToken()
    {
        $this->setupDb();
        $this->loginUser(6); // role: SETTINGS

        $response = $this->runApp(
            'PUT',
            '/api/user/settings/system/change/'.SystemVariable::MAIL_CHARACTER,
            ['value' => 'does-not-matter']
        );
        $this->assertEquals(200, $response->getStatusCode());

        $this->em->clear();

        $changed1 = $this->systemVariableRepository->find(SystemVariable::MAIL_CHARACTER);
        $changed2 = $this->systemVariableRepository->find(SystemVariable::MAIL_TOKEN);
        $this->assertSame('', $changed1->getValue());
        $this->assertSame('', $changed2->getValue());
    }

    public function testSendAccountDisabledMail403()
    {
        $response = $this->runApp('POST', '/api/user/settings/system/send-account-disabled-mail');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testSendAccountDisabledMail200()
    {
        $this->setupDb();
        $this->loginUser(6); // role: SETTINGS

        $response = $this->runApp('POST', '/api/user/settings/system/send-account-disabled-mail');
        $this->assertEquals(200, $response->getStatusCode());

        $this->assertSame('Mail is deactivated.', $this->parseJsonBody($response));
    }

    private function setupDb()
    {
        $this->helper->emptyDb();

        $this->helper->addCharacterMain('User', 5, [Role::USER]);
        $this->helper->addCharacterMain('Admin', 6, [Role::USER, Role::SETTINGS]);

        $var1 = new SystemVariable(SystemVariable::ALLOW_CHARACTER_DELETION);
        $var2 = new SystemVariable(SystemVariable::GROUPS_REQUIRE_VALID_TOKEN);
        $var3 = new SystemVariable(SystemVariable::SHOW_PREVIEW_BANNER);
        $var4 = new SystemVariable(SystemVariable::MAIL_CHARACTER);
        $var5 = new SystemVariable(SystemVariable::MAIL_TOKEN);

        $var1->setValue("0");
        $var2->setValue("1");
        $var3->setValue("0");
        $var4->setValue("The char");
        $var5->setValue('{"ID": "123", "TOKEN": "abc"}');

        $var4->setScope(SystemVariable::SCOPE_SETTINGS);
        $var5->setScope(SystemVariable::SCOPE_BACKEND);

        $this->em->persist($var1);
        $this->em->persist($var2);
        $this->em->persist($var3);
        $this->em->persist($var4);
        $this->em->persist($var5);

        $this->em->flush();
    }
}
