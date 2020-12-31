<?php
/** @noinspection DuplicatedCode */

declare(strict_types=1);

namespace Tests\Functional\Controller\User;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Doctrine\Persistence\ObjectManager;
use Neucore\Entity\Alliance;
use Neucore\Entity\Corporation;
use Neucore\Entity\Group;
use Neucore\Entity\Role;
use Neucore\Entity\Service;
use Neucore\Entity\SystemVariable;
use Neucore\Factory\RepositoryFactory;
use Neucore\Repository\SystemVariableRepository;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use Monolog\Handler\TestHandler;
use Psr\Log\LoggerInterface;
use Tests\Client;
use Tests\Logger;
use Tests\Functional\WebTestCase;
use Tests\Helper;
use Tests\WriteErrorListener;

class SettingsControllerTest extends WebTestCase
{
    /**
     * @var WriteErrorListener
     */
    private static $writeErrorListener;

    /**
     * @var Helper
     */
    private $helper;

    /**
     * @var EntityManagerInterface
     */
    private $em;

    /**
     * @var SystemVariableRepository
     */
    private $systemVariableRepository;

    /**
     * @var Service
     */
    private $service1;

    /**
     * @var Service
     */
    private $service2;

    public static function setupBeforeClass(): void
    {
        self::$writeErrorListener = new WriteErrorListener();
    }

    protected function setUp(): void
    {
        $_SESSION = null;

        $this->helper = new Helper();
        $this->helper->emptyDb();

        $this->em = $this->helper->getEm();
        $this->systemVariableRepository = (new RepositoryFactory($this->em))->getSystemVariableRepository();
    }

    public function tearDown(): void
    {
        $this->em->getEventManager()->removeEventListener(Events::onFlush, self::$writeErrorListener);
    }

    public function testSystemList200Anonymous()
    {
        $this->setupDb(false);

        $response = $this->runApp('GET', '/api/user/settings/system/list');
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertSame([
            ['name' => SystemVariable::ALLOW_CHARACTER_DELETION, 'value' => '0'],
            ['name' => 'esiDataSource', 'value' => getenv('NEUCORE_EVE_DATASOURCE') ?: 'tranquility'],
            ['name' => 'esiHost', 'value' => 'https://esi.evetech.net'],
            ['name' => 'navigationShowGroups', 'value' => '0'],
            ['name' => 'navigationServices', 'value' => \json_encode([])],
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
            ['name' => 'esiDataSource', 'value' => getenv('NEUCORE_EVE_DATASOURCE') ?: 'tranquility'],
            ['name' => 'esiHost', 'value' => 'https://esi.evetech.net'],
            ['name' => 'navigationShowGroups', 'value' => '1'],
            ['name' => 'navigationServices', 'value' => \json_encode([
                $this->service1->jsonSerialize(),
                $this->service2->jsonSerialize(),
            ])],
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
            ['name' => 'esiDataSource', 'value' => getenv('NEUCORE_EVE_DATASOURCE') ?: 'tranquility'],
            ['name' => 'esiHost', 'value' => 'https://esi.evetech.net'],
            ['name' => 'navigationShowGroups', 'value' => '1'],
            ['name' => 'navigationServices', 'value' => \json_encode([$this->service1->jsonSerialize()])],
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

        $this->em->getEventManager()->addEventListener(Events::onFlush, self::$writeErrorListener);

        $log = new Logger('Test');
        $log->pushHandler(new TestHandler());

        $response = $this->runApp(
            'PUT',
            '/api/user/settings/system/change/'.SystemVariable::ALLOW_CHARACTER_DELETION,
            ['value' => '1'],
            null,
            [ObjectManager::class => $this->em, LoggerInterface::class => $log]
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
            ['value' => '1'],
            ['Content-Type' => 'application/x-www-form-urlencoded']
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

    public function testSendInvalidTokenMail403()
    {
        $response = $this->runApp('POST', '/api/user/settings/system/send-invalid-token-mail');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testSendInvalidTokenMail200Deactivated()
    {
        $this->setupDb();
        $this->loginUser(6); // role: SETTINGS

        $response = $this->runApp('POST', '/api/user/settings/system/send-invalid-token-mail');
        $this->assertEquals(200, $response->getStatusCode());

        $this->assertSame('Mail is deactivated.', $this->parseJsonBody($response));
    }

    public function testSendInvalidTokenMail200MissingSettings()
    {
        $var = (new SystemVariable(SystemVariable::MAIL_INVALID_TOKEN_ACTIVE))->setValue('1');
        $this->em->persist($var);

        $this->setupDb();
        $this->loginUser(6); // role: SETTINGS

        $response = $this->runApp('POST', '/api/user/settings/system/send-invalid-token-mail');
        $this->assertEquals(200, $response->getStatusCode());

        $this->assertSame('Alliance and/or Corporation settings variable not found.', $this->parseJsonBody($response));
    }

    public function testSendInvalidTokenMail200MissingChar()
    {
        $var1 = (new SystemVariable(SystemVariable::MAIL_INVALID_TOKEN_ACTIVE))->setValue('1');
        $var2 = (new SystemVariable(SystemVariable::MAIL_INVALID_TOKEN_ALLIANCES))->setValue('123,456');
        $var3 = (new SystemVariable(SystemVariable::MAIL_INVALID_TOKEN_CORPORATIONS))->setValue('');
        $this->em->persist($var1);
        $this->em->persist($var2);
        $this->em->persist($var3);

        $this->setupDb();
        $this->loginUser(6); // role: SETTINGS

        $response = $this->runApp('POST', '/api/user/settings/system/send-invalid-token-mail');
        $this->assertEquals(200, $response->getStatusCode());

        $this->assertSame('Missing subject or body text.', $this->parseJsonBody($response));
    }

    public function testMissingCharacterMail403()
    {
        $response = $this->runApp('POST', '/api/user/settings/system/send-missing-character-mail');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testMissingCharacterMail200()
    {
        $this->setupDb();
        $this->loginUser(6); // role: SETTINGS

        $response1 = $this->runApp('POST', '/api/user/settings/system/send-missing-character-mail');
        $this->assertEquals(200, $response1->getStatusCode());
        $this->assertSame('Mail is deactivated.', $this->parseJsonBody($response1));

        $activeVar = (new SystemVariable(SystemVariable::MAIL_MISSING_CHARACTER_ACTIVE))->setValue('1');
        $this->em->persist($activeVar);
        $this->em->flush();
        $this->em->clear();

        $response2 = $this->runApp('POST', '/api/user/settings/system/send-missing-character-mail');
        $this->assertEquals(200, $response2->getStatusCode());
        $this->assertSame('Invalid config.', $this->parseJsonBody($response2));

        $daysVar = (new SystemVariable(SystemVariable::MAIL_MISSING_CHARACTER_RESEND))->setValue('20');
        $varSubject = (new SystemVariable(SystemVariable::MAIL_MISSING_CHARACTER_SUBJECT))->setValue('s');
        $varBody = (new SystemVariable(SystemVariable::MAIL_MISSING_CHARACTER_BODY))->setValue('b');
        $this->em->persist($daysVar);
        $this->em->persist($varSubject);
        $this->em->persist($varBody);
        $this->em->flush();
        $this->em->clear();
        $client = new Client();
        $client->setResponse(
            new Response(200, [], '373515628') // for postCharactersCharacterIdMail()
        );

        $response3 = $this->runApp('POST', '/api/user/settings/system/send-missing-character-mail', null, null, [
            ClientInterface::class => $client
        ]);
        $this->assertEquals(200, $response3->getStatusCode());
        $this->assertSame('', $this->parseJsonBody($response3)); // success
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

    private function setupDb(bool $publicGroup = true): void
    {
        $group = (new Group())->setName('g1');
        if ($publicGroup) {
            $group->setVisibility(Group::VISIBILITY_PUBLIC);
        }
        $this->em->persist($group);
        $this->em->flush();

        $this->helper->addCharacterMain('User', 5, [Role::USER], ['g1']);
        $admin = $this->helper->addCharacterMain('Admin', 6, [Role::USER, Role::SETTINGS]);

        $this->service1 = (new Service())->setName('s1');
        $this->service2 = (new Service())->setName('s2')
            ->setConfiguration((string)json_encode(['groups' => $group->getId()]));
        $service3 = (new Service())->setName('s3')
            ->setConfiguration((string)json_encode(['groups' => $group->getId()+99]));
        $alli = (new Alliance())->setId(456);
        $corp = (new Corporation())->setId(2020)->setAlliance($alli);
        $admin->setCorporation($corp);

        $var1 = new SystemVariable(SystemVariable::ALLOW_CHARACTER_DELETION);
        $var2 = new SystemVariable(SystemVariable::GROUPS_REQUIRE_VALID_TOKEN);
        $var4 = new SystemVariable(SystemVariable::MAIL_CHARACTER);
        $var5 = new SystemVariable(SystemVariable::MAIL_TOKEN);
        $var6 = new SystemVariable(SystemVariable::DIRECTOR_CHAR . 1);
        $var7 = new SystemVariable(SystemVariable::DIRECTOR_TOKEN . 1);
        $var8 = new SystemVariable(SystemVariable::ALLOW_LOGIN_MANAGED);

        $var1->setValue("0");
        $var2->setValue("1");
        $var4->setValue("The char");
        $var5->setValue((string) \json_encode([
            'id' => 123,
            'access' => 'access-token',
            'refresh' => 'refresh-token',
            'expires' => time() + 10000,
        ]));
        $var6->setValue('{"character_id": "10", "corporation_id": "101"}');
        $var7->setValue('{"access": "at", "refresh": "rt", "expires": '.(time() + 60*20).'}');
        $var8->setValue("0");

        $var2->setScope(SystemVariable::SCOPE_SETTINGS);
        $var4->setScope(SystemVariable::SCOPE_SETTINGS);
        $var5->setScope(SystemVariable::SCOPE_BACKEND);
        $var6->setScope(SystemVariable::SCOPE_SETTINGS);
        $var7->setScope(SystemVariable::SCOPE_BACKEND);
        $var8->setScope(SystemVariable::SCOPE_SETTINGS);

        $this->em->persist($var1);
        $this->em->persist($var2);
        $this->em->persist($var4);
        $this->em->persist($var5);
        $this->em->persist($var6);
        $this->em->persist($var7);
        $this->em->persist($var8);
        $this->em->persist($this->service1);
        $this->em->persist($this->service2);
        $this->em->persist($service3);
        $this->em->persist($alli);
        $this->em->persist($corp);

        $this->em->flush();
    }
}
