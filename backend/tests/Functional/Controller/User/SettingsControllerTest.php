<?php
/** @noinspection DuplicatedCode */

declare(strict_types=1);

namespace Tests\Functional\Controller\User;

require_once __DIR__ . '/SettingsController/plugin/src/TestService.php';

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Doctrine\Persistence\ObjectManager;
use Neucore\Entity\Alliance;
use Neucore\Entity\Corporation;
use Neucore\Entity\Group;
use Neucore\Entity\Role;
use Neucore\Entity\Plugin;
use Neucore\Data\PluginConfigurationDatabase;
use Neucore\Entity\SystemVariable;
use Neucore\Factory\RepositoryFactory;
use Neucore\Repository\SystemVariableRepository;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use Monolog\Handler\TestHandler;
use Psr\Log\LoggerInterface;
use Tests\Client;
use Tests\Functional\Controller\User\SettingsController\TestService;
use Tests\Logger;
use Tests\Functional\WebTestCase;
use Tests\Helper;
use Tests\WriteErrorListener;

class SettingsControllerTest extends WebTestCase
{
    private static WriteErrorListener $writeErrorListener;

    private Helper $helper;

    private EntityManagerInterface $em;

    private SystemVariableRepository $systemVariableRepository;

    private Logger $logger;

    private Plugin $service1;

    private Plugin $service2;

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

        $this->logger = new Logger();
        $this->logger->pushHandler(new TestHandler());

    }

    public function tearDown(): void
    {
        $this->em->getEventManager()->removeEventListener(Events::onFlush, self::$writeErrorListener);
    }

    public function testSystemList200Anonymous()
    {
        $this->setupDb(false);

        // Note: the list of variables is not complete

        $response = $this->runApp(
            'GET',
            '/api/user/settings/system/list',
            null,
            null,
            [LoggerInterface::class => $this->logger],
            [['NEUCORE_PLUGINS_INSTALL_DIR', __DIR__ . '/SettingsController']],
        );
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertSame([
            ['name' => SystemVariable::ACCOUNT_DEACTIVATION_ALLIANCES, 'value' => '11'],
            ['name' => SystemVariable::ACCOUNT_DEACTIVATION_CORPORATIONS, 'value' => '101'],
            ['name' => SystemVariable::ALLOW_CHARACTER_DELETION, 'value' => '0'],
            ['name' => 'esiDataSource', 'value' => $_ENV['NEUCORE_EVE_DATASOURCE'] ?? 'tranquility'],
            ['name' => 'esiHost', 'value' => 'https://esi.evetech.net'],
            ['name' => 'navigationShowGroups', 'value' => '0'],
            ['name' => 'navigationServices', 'value' => \json_encode([])],
            ['name' => 'navigationGeneralPlugins', 'value' => \json_encode([])],
            ['name' => 'repository', 'value' => 'https://github.com/tkhamez/neucore'],
        ], $this->parseJsonBody($response));
    }

    public function testSystemList200Authenticated()
    {
        $this->setupDb();
        $this->loginUser(5); // role: USER, GROUP_MANAGER; groups: g1

        // Note: the list of variables is not complete

        $response = $this->runApp(
            'GET',
            '/api/user/settings/system/list',
            null,
            null,
            [LoggerInterface::class => $this->logger],
            [['NEUCORE_PLUGINS_INSTALL_DIR', __DIR__ . '/SettingsController']],
        );
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertSame([
            ['name' => SystemVariable::ACCOUNT_DEACTIVATION_ALLIANCES, 'value' => '11'],
            ['name' => SystemVariable::ACCOUNT_DEACTIVATION_CORPORATIONS, 'value' => '101'],
            ['name' => SystemVariable::ALLOW_CHARACTER_DELETION, 'value' => '0'],
            ['name' => 'esiDataSource', 'value' => $_ENV['NEUCORE_EVE_DATASOURCE'] ?? 'tranquility'],
            ['name' => 'esiHost', 'value' => 'https://esi.evetech.net'],
            ['name' => 'navigationShowGroups', 'value' => '1'],
            ['name' => 'navigationServices', 'value' => \json_encode([
                $this->service1->jsonSerialize(),
                $this->service2->jsonSerialize(),
            ])],
            ['name' => 'navigationGeneralPlugins', 'value' => \json_encode([
                ['parent' => 'root', 'name' => 'Test', 'url' => '/plugin/'.$this->service1->getId().'/t1',
                    'target' => '_blank'],
                ['parent' => 'root', 'name' => 'Test', 'url' => '/plugin/'.$this->service1->getId().'/t4',
                    'target' => '_self'],
                ['parent' => 'root', 'name' => 'Test', 'url' => '/plugin/'.$this->service1->getId().'/t6',
                    'target' => '_self'],
                ['parent' => 'root', 'name' => 'Test', 'url' => '/plugin/'.$this->service2->getId().'/t1',
                    'target' => '_blank'],
                ['parent' => 'root', 'name' => 'Test', 'url' => '/plugin/'.$this->service2->getId().'/t4',
                    'target' => '_self'],
                ['parent' => 'root', 'name' => 'Test', 'url' => '/plugin/'.$this->service2->getId().'/t6',
                    'target' => '_self'],
            ])],
            ['name' => 'repository', 'value' => 'https://github.com/tkhamez/neucore'],
        ], $this->parseJsonBody($response));
        $this->assertSame([
            'Plugin navigation item: invalid URL "http://invalid", plugin ID ' . $this->service1->getId(),
            'Plugin navigation item: invalid position "invalid", plugin ID ' . $this->service1->getId(),
            'Plugin navigation item: invalid URL "http://invalid", plugin ID ' . $this->service2->getId(),
            'Plugin navigation item: invalid position "invalid", plugin ID ' . $this->service2->getId(),
        ], $this->logger->getMessages());
    }

    public function testSystemList200AuthenticatedGroupsDeactivated()
    {
        $this->setupDb();
        $this->loginUser(51); // role: USER; groups: g1, but deactivated

        $response = $this->runApp(
            'GET',
            '/api/user/settings/system/list',
            null,
            null,
            [LoggerInterface::class => $this->logger],
            [['NEUCORE_PLUGINS_INSTALL_DIR', __DIR__ . '/SettingsController']],
        );
        $this->assertEquals(200, $response->getStatusCode());
        $parsedBody = $this->parseJsonBody($response);
        $this->assertSame([
            'name' => 'navigationGeneralPlugins', 'value' => \json_encode([]) // had t4 with active groups
        ], $parsedBody[7]);

    }

    public function testSystemList200RoleSetting()
    {
        $this->setupDb();
        $this->loginUser(6); // roles: USER, GROUP_MANAGER, SETTINGS; groups: none

        // Note: the list of variables is not complete

        $response = $this->runApp(
            'GET',
            '/api/user/settings/system/list',
            null,
            null,
            [LoggerInterface::class => $this->logger],
            [['NEUCORE_PLUGINS_INSTALL_DIR', __DIR__ . '/SettingsController']],
        );
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertSame([
            ['name' => SystemVariable::ACCOUNT_DEACTIVATION_ALLIANCES, 'value' => '11'],
            ['name' => SystemVariable::ACCOUNT_DEACTIVATION_CORPORATIONS, 'value' => '101'],
            ['name' => SystemVariable::ALLOW_CHARACTER_DELETION, 'value' => '0'],
            ['name' => SystemVariable::ALLOW_LOGIN_NO_SCOPES, 'value' => '0'],
            ['name' => SystemVariable::DISABLE_ALT_LOGIN, 'value' => '0'],
            ['name' => SystemVariable::GROUPS_REQUIRE_VALID_TOKEN, 'value' => '1'],
            ['name' => SystemVariable::MAIL_CHARACTER, 'value' => 'The char'],
            ['name' => 'esiDataSource', 'value' => $_ENV['NEUCORE_EVE_DATASOURCE'] ?? 'tranquility'],
            ['name' => 'esiHost', 'value' => 'https://esi.evetech.net'],
            ['name' => 'navigationShowGroups', 'value' => '1'],
            ['name' => 'navigationServices', 'value' => \json_encode([$this->service1->jsonSerialize()])],
            ['name' => 'navigationGeneralPlugins', 'value' => \json_encode([
                ['parent' => 'root', 'name' => 'Test', 'url' => '/plugin/'.$this->service1->getId().'/t1',
                    'target' => '_blank']
            ])],
            ['name' => 'repository', 'value' => 'https://github.com/tkhamez/neucore'],
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

        $response = $this->runApp(
            'PUT',
            '/api/user/settings/system/change/'.SystemVariable::ALLOW_CHARACTER_DELETION,
            ['value' => '1'],
            null,
            [ObjectManager::class => $this->em, LoggerInterface::class => $this->logger]
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

    private function setupDb(bool $publicGroup = true): void
    {
        $group = (new Group())->setName('g1');
        if ($publicGroup) {
            $group->setVisibility(Group::VISIBILITY_PUBLIC);
        }
        $this->em->persist($group);
        $this->em->flush();

        TestService::$groupId = $group->getId();
        TestService::$managerGroupId = $group->getId();

        $char = $this->helper->addCharacterMain('User', 5, [Role::USER, Role::GROUP_MANAGER], ['g1']);
        $group->addManager($char->getPlayer());
        $admin = $this->helper->addCharacterMain('Admin', 6, [Role::USER, Role::GROUP_MANAGER, Role::SETTINGS]);

        $char2 = $this->helper->addCharacterMain('User 2', 51, [Role::USER], ['g1']);

        $conf1 = new PluginConfigurationDatabase();
        $conf1->directoryName = 'plugin';
        $conf1->active = true;
        $this->service1 = (new Plugin())->setName('s1')->setConfigurationDatabase($conf1);

        $conf2 = new PluginConfigurationDatabase();
        $conf2->directoryName = 'plugin';
        $conf2->active = true;
        $conf2->requiredGroups = [$group->getId()];
        $this->service2 = (new Plugin())->setName('s2')->setConfigurationDatabase($conf2);

        $conf3 = new PluginConfigurationDatabase();
        $conf3->directoryName = 'plugin';
        $conf3->active = true;
        $conf3->requiredGroups = [$group->getId()+99];
        $service3 = (new Plugin())->setName('s3')->setConfigurationDatabase($conf3);

        // Inactive service, will be ignored
        $conf4 = new PluginConfigurationDatabase();
        $conf4->directoryName = 'plugin';
        $service4 = (new Plugin())->setName('s4')->setConfigurationDatabase($conf4);

        $alli = (new Alliance())->setId(456);
        $corp = (new Corporation())->setId(2020)->setAlliance($alli);
        $admin->setCorporation($corp);

        $var1 = (new SystemVariable(SystemVariable::ALLOW_CHARACTER_DELETION))->setValue("0");
        $var4 = (new SystemVariable(SystemVariable::MAIL_CHARACTER))->setValue("The char")
            ->setScope(SystemVariable::SCOPE_SETTINGS);
        $var5 = (new SystemVariable(SystemVariable::MAIL_TOKEN))->setValue((string) \json_encode([
            'id' => 123,
            'access' => 'access-token',
            'refresh' => 'refresh-token',
            'expires' => time() + 10000,
        ]))->setScope(SystemVariable::SCOPE_BACKEND);
        $var8 = (new SystemVariable(SystemVariable::ALLOW_LOGIN_NO_SCOPES))->setValue("0")
            ->setScope(SystemVariable::SCOPE_SETTINGS);
        $var9 = (new SystemVariable(SystemVariable::DISABLE_ALT_LOGIN))->setValue('0')
            ->setScope(SystemVariable::SCOPE_SETTINGS);

        $this->em->persist($var1);
        $this->em->persist($var4);
        $this->em->persist($var5);
        $this->em->persist($var8);
        $this->em->persist($var9);
        $this->em->persist($this->service1);
        $this->em->persist($this->service2);
        $this->em->persist($service3);
        $this->em->persist($service4);
        $this->em->persist($alli);
        $this->em->persist($corp);

        $this->helper->setupDeactivateAccount($char2->getPlayer());

        $this->em->flush();
        $this->em->clear();
    }
}
