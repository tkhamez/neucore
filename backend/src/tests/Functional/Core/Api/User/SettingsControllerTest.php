<?php declare(strict_types=1);

namespace Tests\Functional\Core\Api\User;

use Brave\Core\Entity\Alliance;
use Brave\Core\Entity\Corporation;
use Brave\Core\Entity\Role;
use Brave\Core\Entity\SystemVariable;
use Brave\Core\Factory\RepositoryFactory;
use Brave\Core\Repository\SystemVariableRepository;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use Monolog\Handler\TestHandler;
use Psr\Log\LoggerInterface;
use Tests\Client;
use Tests\Logger;
use Tests\WebTestCase;
use Tests\Helper;
use Tests\WriteErrorListener;

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
        $this->helper->emptyDb();

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
            ['name' => 'esiDataSource', 'value' => getenv('BRAVECORE_EVE_DATASOURCE') ?: 'tranquility'],
            ['name' => 'esiHost', 'value' => 'https://esi.evetech.net'],
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
            ['name' => 'esiDataSource', 'value' => getenv('BRAVECORE_EVE_DATASOURCE') ?: 'tranquility'],
            ['name' => 'esiHost', 'value' => 'https://esi.evetech.net'],
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
            ['name' => SystemVariable::ALLOW_LOGIN_MANAGED, 'value' => '0'],
            ['name' => SystemVariable::DIRECTOR_CHAR . 1, 'value' => '{"character_id": "10", "corporation_id": "101"}'],
            ['name' => SystemVariable::GROUPS_REQUIRE_VALID_TOKEN, 'value' => '1'],
            ['name' => SystemVariable::MAIL_CHARACTER, 'value' => 'The char'],
            ['name' => SystemVariable::SHOW_PREVIEW_BANNER, 'value' => '0'],
            ['name' => 'esiDataSource', 'value' => getenv('BRAVECORE_EVE_DATASOURCE') ?: 'tranquility'],
            ['name' => 'esiHost', 'value' => 'https://esi.evetech.net'],
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

    public function testSystemChange500()
    {
        $this->setupDb();
        $this->loginUser(6); // role: SETTINGS

        $em = $this->helper->getEm(true);
        $em->getEventManager()->addEventListener(\Doctrine\ORM\Events::onFlush, new WriteErrorListener());

        $log = new Logger('Test');
        $log->pushHandler(new TestHandler());

        $response = $this->runApp(
            'PUT',
            '/api/user/settings/system/change/'.SystemVariable::ALLOW_CHARACTER_DELETION,
            ['value' => '1'],
            null,
            [EntityManagerInterface::class => $em, LoggerInterface::class => $log]
        );
        $this->assertEquals(500, $response->getStatusCode());
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

    public function testSystemChange204RemoveDirector()
    {
        $this->setupDb();
        $this->loginUser(6); // role: SETTINGS

        $response = $this->runApp(
            'PUT',
            '/api/user/settings/system/change/'.SystemVariable::DIRECTOR_CHAR . 1,
            ['value' => 'does-not-matter']
        );
        $this->assertEquals(204, $response->getStatusCode());

        $this->em->clear();

        $actual = $this->systemVariableRepository->find(SystemVariable::DIRECTOR_CHAR . 1);
        $this->assertNull($actual);
    }

    public function testSendAccountDisabledMail403()
    {
        $response = $this->runApp('POST', '/api/user/settings/system/send-account-disabled-mail');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testSendAccountDisabledMail200Deactivated()
    {
        $this->setupDb();
        $this->loginUser(6); // role: SETTINGS

        $response = $this->runApp('POST', '/api/user/settings/system/send-account-disabled-mail');
        $this->assertEquals(200, $response->getStatusCode());

        $this->assertSame('Mail is deactivated.', $this->parseJsonBody($response));
    }

    public function testSendAccountDisabledMail200MissingSettings()
    {
        $var = (new SystemVariable(SystemVariable::MAIL_ACCOUNT_DISABLED_ACTIVE))->setValue('1');
        $this->em->persist($var);

        $this->setupDb();
        $this->loginUser(6); // role: SETTINGS

        $response = $this->runApp('POST', '/api/user/settings/system/send-account-disabled-mail');
        $this->assertEquals(200, $response->getStatusCode());

        $this->assertSame('Alliance settings variable not found.', $this->parseJsonBody($response));
    }

    public function testSendAccountDisabledMail200MissingChar()
    {
        $var1 = (new SystemVariable(SystemVariable::MAIL_ACCOUNT_DISABLED_ACTIVE))->setValue('1');
        $var2 = (new SystemVariable(SystemVariable::MAIL_ACCOUNT_DISABLED_ALLIANCES))->setValue('123,456');
        $this->em->persist($var1);
        $this->em->persist($var2);

        $this->setupDb();
        $this->loginUser(6); // role: SETTINGS

        $response = $this->runApp('POST', '/api/user/settings/system/send-account-disabled-mail');
        $this->assertEquals(200, $response->getStatusCode());

        $this->assertSame('Missing subject.', $this->parseJsonBody($response));
    }

    public function testValidateDirector403()
    {
        $this->setupDb();

        $response1 = $this->runApp('PUT', '/api/user/settings/system/validate-director/director_char_1');
        $this->assertEquals(403, $response1->getStatusCode());

        $this->loginUser(5);

        $response2 = $this->runApp('PUT', '/api/user/settings/system/validate-director/director_char_1');
        $this->assertEquals(403, $response2->getStatusCode());
    }

    public function testValidateDirector200()
    {
        $this->setupDb();
        $this->loginUser(6);

        $client = new Client();
        $client->setResponse(
            new Response(200, [], '{"name": "changed", "corporation_id": 102}'), // getCharactersCharacterId()
            new Response(200, [], '{"name": "n", "ticker": "t"}'), // getCorporationsCorporationId()
            new Response(200, [], '{"roles": ["Director"]}') // /characters/10/roles
        );

        $response = $this->runApp('PUT', '/api/user/settings/system/validate-director/director_char_1', null, null, [
            ClientInterface::class => $client
        ]);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($this->parseJsonBody($response));
    }

    private function setupDb()
    {
        $this->helper->addCharacterMain('User', 5, [Role::USER]);
        $admin = $this->helper->addCharacterMain('Admin', 6, [Role::USER, Role::SETTINGS]);

        $alli = (new Alliance())->setId(456);
        $corp = (new Corporation())->setId(2020)->setAlliance($alli);
        $admin->setCorporation($corp);

        $var1 = new SystemVariable(SystemVariable::ALLOW_CHARACTER_DELETION);
        $var2 = new SystemVariable(SystemVariable::GROUPS_REQUIRE_VALID_TOKEN);
        $var3 = new SystemVariable(SystemVariable::SHOW_PREVIEW_BANNER);
        $var4 = new SystemVariable(SystemVariable::MAIL_CHARACTER);
        $var5 = new SystemVariable(SystemVariable::MAIL_TOKEN);
        $var6 = new SystemVariable(SystemVariable::DIRECTOR_CHAR . 1);
        $var7 = new SystemVariable(SystemVariable::DIRECTOR_TOKEN . 1);
        $var8 = new SystemVariable(SystemVariable::ALLOW_LOGIN_MANAGED);

        $var1->setValue("0");
        $var2->setValue("1");
        $var3->setValue("0");
        $var4->setValue("The char");
        $var5->setValue('{"ID": "123", "TOKEN": "abc"}');
        $var6->setValue('{"character_id": "10", "corporation_id": "101"}');
        $var7->setValue('{"access": "at", "refresh": "rt", "expires": '.(time() + 60*20).'}');
        $var8->setValue("0");

        $var4->setScope(SystemVariable::SCOPE_SETTINGS);
        $var5->setScope(SystemVariable::SCOPE_BACKEND);
        $var6->setScope(SystemVariable::SCOPE_SETTINGS);
        $var7->setScope(SystemVariable::SCOPE_BACKEND);
        $var8->setScope(SystemVariable::SCOPE_SETTINGS);

        $this->em->persist($var1);
        $this->em->persist($var2);
        $this->em->persist($var3);
        $this->em->persist($var4);
        $this->em->persist($var5);
        $this->em->persist($var6);
        $this->em->persist($var7);
        $this->em->persist($var8);
        $this->em->persist($alli);
        $this->em->persist($corp);

        $this->em->flush();
    }
}
