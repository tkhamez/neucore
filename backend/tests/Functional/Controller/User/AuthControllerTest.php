<?php
/** @noinspection DuplicatedCode */

declare(strict_types=1);

namespace Tests\Functional\Controller\User;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Neucore\Controller\User\AuthController;
use Neucore\Entity\EveLogin;
use Neucore\Entity\Role;
use Neucore\Entity\SystemVariable;
use Neucore\Factory\RepositoryFactory;
use Neucore\Service\SessionData;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use Psr\Log\LoggerInterface;
use Tests\Helper;
use Tests\Functional\WebTestCase;
use Tests\Client;
use Tests\Logger;
use Tests\WriteErrorListener;

class AuthControllerTest extends WebTestCase
{
    private static string $state = '1jdHR64hSdYf';

    private static EntityManagerInterface $em;

    private static WriteErrorListener $writeErrorListener;

    private Client $client;

    private Helper $helper;

    private Logger $log;

    public static function setupBeforeClass(): void
    {
        self::$em = (new Helper())->getEm();
        self::$writeErrorListener = new WriteErrorListener();
    }

    protected function setUp(): void
    {
        $_SESSION = null;
        $this->client = new Client();
        $this->helper = new Helper();
        $this->helper->emptyDb();
        $this->log = new Logger('Test');
    }

    public function tearDown(): void
    {
        self::$em->getEventManager()->removeEventListener(Events::onFlush, self::$writeErrorListener);
    }

    public function testLogin_404()
    {
        $response = $this->runApp('GET', '/login/invalid');
        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame('Login not found.<br><br><a href="/">Home</a>', $response->getBody()->__toString());
    }

    public function testLogin_CustomLogin()
    {
        // add EveLogin
        $loginId = 'custom1';
        $eveLogin = (new EveLogin())->setName($loginId);
        $this->helper->getObjectManager()->persist($eveLogin);
        $this->helper->getObjectManager()->flush();

        $response = $this->runApp('GET', "/login/$loginId");

        $this->assertSame(302, $response->getStatusCode());
        $this->assertStringContainsString('eveonline.com/v2/oauth/authorize', $response->getHeader('location')[0]);

        $sess = new SessionData();
        $this->assertStringStartsWith($this->getStatePrefix($loginId), $sess->get('auth_state'));
    }

    public function testLogin_DefaultOrAlt()
    {
        $response = $this->runApp('GET', '/login/'.EveLogin::NAME_DEFAULT);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertStringContainsString('eveonline.com/v2/oauth/authorize', $response->getHeader('location')[0]);

        $sess = new SessionData();
        $this->assertStringStartsWith($this->getStatePrefix(EveLogin::NAME_DEFAULT), $sess->get('auth_state'));
    }

    public function testLogin_ManagedForbidden()
    {
        $response = $this->runApp('GET', '/login/'.EveLogin::NAME_MANAGED);

        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame('Forbidden.', $response->getBody()->__toString());
    }

    public function testLogin_ManagedOrManagedAlt()
    {
        // activate login "managed"
        $setting = new SystemVariable(SystemVariable::ALLOW_LOGIN_MANAGED);
        $setting->setValue('1');
        $this->helper->getObjectManager()->persist($setting);
        $this->helper->getObjectManager()->flush();

        $response = $this->runApp('GET', '/login/'.EveLogin::NAME_MANAGED);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertStringContainsString('eveonline.com/v2/oauth/authorize', $response->getHeader('location')[0]);

        $sess = new SessionData();
        $this->assertStringStartsWith($this->getStatePrefix(EveLogin::NAME_MANAGED), $sess->get('auth_state'));
    }

    public function testLogin_Mail()
    {
        $response = $this->runApp('GET', '/login/'.EveLogin::NAME_MAIL);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertStringContainsString('eveonline.com/v2/oauth/authorize', $response->getHeader('location')[0]);

        $sess = new SessionData();
        $this->assertStringStartsWith($this->getStatePrefix(EveLogin::NAME_MAIL), $sess->get('auth_state'));
    }

    public function testCallback_InvalidStateException()
    {
        $state = self::$state;
        $_SESSION = ['auth_state' => $state, 'auth_result' => null];

        $response = $this->runApp('GET', '/login-callback?state=INVALID'); // fail early
        $this->assertSame(302, $response->getStatusCode());

        $this->assertfalse(isset($_SESSION['auth_state'])); // test that it was deleted
        $this->assertSame(
            ['success' => false, 'message' => 'OAuth state mismatch.'],
            $_SESSION['auth_result']
        );
    }

    /**
     * @throws \Exception
     */
    public function testCallback_CustomLogin_MissingEveLogin()
    {
        // add EveLogin
        $charId = 123; // the ID used in Helper::generateToken
        $this->helper->addCharacterMain('Test User', $charId, [Role::USER], [], false); // without ESI token
        $this->loginUser($charId);

        list($token, $keySet) = Helper::generateToken([]);
        $state = $this->getStatePrefix('custom1') . self::$state;
        $_SESSION['auth_state'] = $state;

        $this->client->setResponse(
            new Response(200, [], // for getAccessToken()
                '{"access_token": ' . json_encode($token) . ',
                "expires_in": 1200,
                "refresh_token": "gEy...fM0"}'),
            new Response(200, [], '{"keys": ' . \json_encode($keySet) . '}') // for JWT key set
        );

        $response = $this->runApp(
            'GET',
            '/login-callback?state='.$state,
            null,
            null,
            [ClientInterface::class => $this->client]
        );

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame(
            ['success' => false, 'message' => 'Error, ESI token not added: Invalid login link.'],
            $_SESSION['auth_result']
        );
    }

    /**
     * @throws \Exception
     */
    public function testCallback_CustomLogin_NotLoggedIn()
    {
        // add EveLogin
        $loginId = 'custom1';
        $charId = 123; // the ID used in Helper::generateToken
        $this->helper->getEm()->persist((new EveLogin())->setName($loginId)->setEsiScopes('scope1'));
        $this->helper->addCharacterMain('Test User', $charId, [Role::USER], [], false); // without ESI token
        // not logged in

        list($token, $keySet) = Helper::generateToken(['scope1']);
        $state = $this->getStatePrefix($loginId) . self::$state;
        $_SESSION['auth_state'] = $state;

        $this->client->setResponse(
            new Response(200, [], // for getAccessToken()
                '{"access_token": ' . json_encode($token) . ',
                "expires_in": 1200,
                "refresh_token": "gEy...fM0"}'),
            new Response(200, [], '{"keys": ' . \json_encode($keySet) . '}') // for JWT key set
        );

        $response = $this->runApp(
            'GET',
            '/login-callback?state='.$state,
            null,
            null,
            [ClientInterface::class => $this->client]
        );

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame(
            ['success' => false, 'message' => 'Error, ESI token not added: Not logged in, login first.'],
            $_SESSION['auth_result']
        );
    }

    /**
     * @throws \Exception
     */
    public function testCallback_CustomLogin_CharacterNotOnAccount()
    {
        // add EveLogin
        $loginId = 'custom1';
        $charId = 456; // Not the ID used in Helper::generateToken
        $this->helper->getEm()->persist((new EveLogin())->setName($loginId)->setEsiScopes('scope1'));
        $this->helper->addCharacterMain('Test User', $charId, [Role::USER], [], false); // without ESI token
        $this->loginUser($charId);

        list($token, $keySet) = Helper::generateToken(['scope1']);
        $state = $this->getStatePrefix($loginId) . self::$state;
        $_SESSION['auth_state'] = $state;

        $this->client->setResponse(
            new Response(200, [], // for getAccessToken()
                '{"access_token": ' . json_encode($token) . ',
                "expires_in": 1200,
                "refresh_token": "gEy...fM0"}'),
            new Response(200, [], '{"keys": ' . \json_encode($keySet) . '}') // for JWT key set
        );

        $response = $this->runApp(
            'GET',
            '/login-callback?state='.$state,
            null,
            null,
            [ClientInterface::class => $this->client]
        );

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame(
            [
                'success' => false,
                'message' => 'Error, ESI token not added: Character not found on this account, please add it first.'
            ],
            $_SESSION['auth_result']
        );
    }

    /**
     * @throws \Exception
     */
    public function testCallback_CustomLogin_RoleError()
    {
        // add EveLogin
        $loginId = 'custom1';
        $charId = 123; // the ID used in Helper::generateToken
        $this->helper->getEm()->persist(
            (new EveLogin())->setName($loginId)->setEsiScopes('scope1')->setEveRoles(['Auditor'])
        );
        $this->helper->addCharacterMain('Test User', $charId, [Role::USER], [], false); // without ESI token
        $this->loginUser($charId);

        list($token, $keySet) = Helper::generateToken(['scope1']);
        $state = $this->getStatePrefix($loginId) . self::$state;
        $_SESSION['auth_state'] = $state;

        $this->client->setResponse(
            new Response(200, [], // for getAccessToken()
                '{"access_token": ' . json_encode($token) . ',
                "expires_in": 1200,
                "refresh_token": "gEy...fM0"}'),
            new Response(200, [], '{"keys": ' . \json_encode($keySet) . '}'), // for JWT key set
            new Response(200, [], '{"roles": []}') // getCharactersCharacterIdRoles
        );

        $response = $this->runApp(
            'GET',
            '/login-callback?state='.$state,
            null,
            null,
            [ClientInterface::class => $this->client]
        );

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame(
            ['success' => false, 'message' => 'Error, ESI token not added: Character does not have required role(s).'],
            $_SESSION['auth_result']
        );
    }

    /**
     * @throws \Exception
     */
    public function testCallback_CustomLogin_Success()
    {
        // add EveLogin
        $loginId = 'custom1';
        $charId = 123; // the ID used in Helper::generateToken
        $this->helper->getEm()->persist(
            (new EveLogin())->setName($loginId)->setEsiScopes('scope1')->setEveRoles(['Auditor'])
        );
        $this->helper->addCharacterMain('Test User', $charId, [Role::USER], [], false); // without ESI token
        $this->loginUser($charId);

        list($token, $keySet) = Helper::generateToken(['scope1']);
        $state = $this->getStatePrefix($loginId) . self::$state;
        $_SESSION['auth_state'] = $state;

        $this->client->setResponse(
            new Response(200, [], // for getAccessToken()
                '{"access_token": ' . json_encode($token) . ',
                "expires_in": 1200,
                "refresh_token": "gEy...fM0"}'),
            new Response(200, [], '{"keys": ' . \json_encode($keySet) . '}'), // for JWT key set
            new Response(200, [], '{"roles": ["Auditor"]}') // getCharactersCharacterIdRoles
        );

        $response = $this->runApp(
            'GET',
            '/login-callback?state='.$state,
            null,
            null,
            [ClientInterface::class => $this->client]
        );

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame(['success' => true, 'message' => 'ESI token added.'], $_SESSION['auth_result']);

        $esiTokens = (new RepositoryFactory($this->helper->getObjectManager()))->getEsiTokenRepository()->findBy([]);
        $this->assertSame(1, count($esiTokens));
        $this->assertSame($charId, $esiTokens[0]->getCharacter()->getId());
        $this->assertSame($loginId, $esiTokens[0]->getEveLogin()->getName());
        $this->assertSame('gEy...fM0', $esiTokens[0]->getRefreshToken());
        $this->assertSame($token, $esiTokens[0]->getAccessToken());
        $this->assertLessThanOrEqual(time() + 1200, $esiTokens[0]->getExpires());
        $this->assertTrue($esiTokens[0]->getValidToken());
        $this->assertLessThanOrEqual(time(), $esiTokens[0]->getValidTokenTime()->getTimestamp());
        $this->assertTrue($esiTokens[0]->getHasRoles());
    }

    /**
     * @throws \Exception
     */
    public function testCallback_DefaultAuthError()
    {
        $state = $this->getStatePrefix(EveLogin::NAME_DEFAULT) . self::$state;
        $_SESSION = ['auth_state' => $state, 'auth_result' => null];

        list($token, $keySet) = Helper::generateToken(['read-this']);

        $this->client->setResponse(
            new Response(200, [], '{"access_token": ' . json_encode($token). '}'), // for getAccessToken
            new Response(200, [], '{"keys": ' . json_encode($keySet). '}') // for JTW key set
        );

        $response = $this->runApp(
            'GET',
            '/login-callback?state='.$state,
            null,
            null,
            [ClientInterface::class => $this->client, LoggerInterface::class => $this->log],
            [['NEUCORE_EVE_SCOPES', 'read-this'], ['NEUCORE_EVE_DATASOURCE', 'tranquility']]
        );
        $this->assertSame(302, $response->getStatusCode());

        // fails because Role "user" is missing in database

        $this->assertSame(
            ['success' => false, 'message' => 'Failed to authenticate user.'],
            $_SESSION['auth_result']
        );
    }

    /**
     * @throws \Exception
     */
    public function testCallback_DefaultSuccess()
    {
        $this->helper->getEm()->persist((new EveLogin())->setName(EveLogin::NAME_DEFAULT));
        $this->helper->addRoles(
            [Role::USER, Role::TRACKING, Role::WATCHLIST, Role::WATCHLIST_MANAGER, Role::GROUP_MANAGER]
        );

        list($token, $keySet) = Helper::generateToken(['read-this', 'and-this']);
        $state = $this->getStatePrefix(EveLogin::NAME_DEFAULT) . self::$state;
        $_SESSION = ['auth_state' => $state, 'auth_result' => null];

        $this->client->setResponse(
            new Response(200, [], '{"access_token": ' . \json_encode($token) . '}'), // for getAccessToken()
            new Response(200, [], '{"keys": ' . \json_encode($keySet) . '}'), // for JWT key set
            new Response(200, [], '{"name": "char name", "corporation_id": 102}'), // getCharactersCharacterId()
            new Response(200, [], '[]'), // postCharactersAffiliation())
            new Response(200, [], '{"name": "name corp", "ticker": "-TC-"}') // getCorporationsCorporationId()
        );

        $response = $this->runApp(
            'GET',
            '/login-callback?state='.$state,
            null,
            null,
            [ClientInterface::class => $this->client],
            [['NEUCORE_EVE_SCOPES', 'read-this   and-this'], ['NEUCORE_EVE_DATASOURCE', 'tranquility']]
        );
        $this->assertSame(302, $response->getStatusCode());

        $this->assertSame(['success' => true, 'message' => 'Login successful.'], $_SESSION['auth_result']);
        $this->assertSame('1', $response->getHeaderLine(AuthController::HEADER_LOGIN));
    }

    /**
     * @throws \Exception
     */
    public function testCallback_AddAltLoginError()
    {
        $this->helper->addCharacterMain('User1', 654);
        $this->loginUser(654);

        list($token, $keySet) = Helper::generateToken(['read-this']);
        $state = $this->getStatePrefix(EveLogin::NAME_DEFAULT) . self::$state;
        $_SESSION['auth_state'] = $state;

        $this->client->setResponse(
            new Response(200, [], '{"access_token": ' . \json_encode($token) . '}'), // for getAccessToken()
            new Response(200, [], '{"keys": ' . \json_encode($keySet) . '}') // for JWT key set
        );

        self::$em->getEventManager()->addEventListener(Events::onFlush, self::$writeErrorListener);
        $response = $this->runApp(
            'GET',
            '/login-callback?state='.$state,
            null,
            null,
            [ClientInterface::class => $this->client, LoggerInterface::class => $this->log],
            [['NEUCORE_EVE_SCOPES', 'read-this'], ['NEUCORE_EVE_DATASOURCE', 'tranquility']]
        );
        $this->assertSame(302, $response->getStatusCode());

        // Fails because flush() throws an exception.

        $this->assertSame(
            ['success' => false, 'message' => 'Failed to add character to account.'],
            $_SESSION['auth_result']
        );
    }

    /**
     * @throws \Exception
     */
    public function testCallback_AddAlt()
    {
        $this->helper->addRoles([Role::TRACKING, Role::WATCHLIST, Role::WATCHLIST_MANAGER, Role::GROUP_MANAGER]);

        $this->helper->addCharacterMain('User1', 654, [Role::USER], ['group1']);
        $this->loginUser(654);

        list($token, $keySet) = Helper::generateToken(['read-this']);
        $state = $this->getStatePrefix(EveLogin::NAME_DEFAULT) . self::$state;
        $_SESSION['auth_state'] = $state;

        $this->client->setResponse(
            new Response(200, [], '{"access_token": ' . \json_encode($token) . '}'), // for getAccessToken()
            new Response(200, [], '{"keys": ' . \json_encode($keySet) . '}'), // for JWT key set
            new Response(200, [], '{"name": "char name", "corporation_id": 102}'), // getCharactersCharacterId()
            new Response(200, [], '[]'), // postCharactersAffiliation())
            new Response(200, [], '{"name": "name corp", "ticker": "-TC-"}') // getCorporationsCorporationId()
        );

        $response = $this->runApp(
            'GET',
            '/login-callback?state='.$state,
            null,
            null,
            [ClientInterface::class => $this->client],
            [['NEUCORE_EVE_SCOPES', 'read-this'], ['NEUCORE_EVE_DATASOURCE', 'tranquility']]
        );
        $this->assertSame(302, $response->getStatusCode());

        $this->assertSame(
            ['success' => true, 'message' => 'Character added to player account.'],
            $_SESSION['auth_result']
        );
    }

    /**
     * @throws \Exception
     */
    public function testCallback_NoAltLoginError()
    {
        $setting = (new SystemVariable(SystemVariable::DISABLE_ALT_LOGIN))->setValue('1');
        $this->helper->getObjectManager()->persist($setting);
        $this->helper->addRoles([Role::TRACKING, Role::WATCHLIST, Role::WATCHLIST_MANAGER, Role::GROUP_MANAGER]);

        $main = $this->helper->addCharacterMain('User1', 654, [Role::USER], ['group1']);
        $alt = $this->helper->addCharacterToPlayer('Alt1', 987, $main->getPlayer());

        list($token, $keySet) = Helper::generateToken(
            ['read-this'],
            'Alt1',
            (string)$alt->getCharacterOwnerHash(),
            987
        );
        $state = $this->getStatePrefix(EveLogin::NAME_DEFAULT) . self::$state;
        $_SESSION['auth_state'] = $state;

        $this->client->setResponse(
            new Response(200, [], '{"access_token": ' . \json_encode($token) . '}'), // for getAccessToken()
            new Response(200, [], '{"keys": ' . \json_encode($keySet) . '}'), // for JWT key set
            new Response(200, [], '{"name": "char name", "corporation_id": 102}'), // getCharactersCharacterId()
            new Response(200, [], '[]'), // postCharactersAffiliation())
            new Response(200, [], '{"name": "name corp", "ticker": "-TC-"}') // getCorporationsCorporationId()
        );

        $response = $this->runApp(
            'GET',
            '/login-callback?state='.$state,
            null,
            null,
            [ClientInterface::class => $this->client, LoggerInterface::class => $this->log],
            [['NEUCORE_EVE_SCOPES', 'read-this'], ['NEUCORE_EVE_DATASOURCE', 'tranquility']]
        );
        $this->assertSame(302, $response->getStatusCode());

        $this->assertSame(
            ['success' => false, 'message' => 'Login failed. Please use your main character to login.'],
            $_SESSION['auth_result']
        );
    }

    /**
     * @throws \Exception
     */
    public function testCallback_AccountsMerged()
    {
        $this->helper->addRoles([Role::TRACKING, Role::WATCHLIST, Role::WATCHLIST_MANAGER, Role::GROUP_MANAGER]);

        $this->helper->addCharacterMain('User1', 654);
        $this->helper->addCharacterMain('User2', 123); // the ID used in Helper::generateToken
        $this->loginUser(654);

        // 123 is the character owner hash used in addCharacterMain()
        list($token, $keySet) = Helper::generateToken(['read-this'], 'Name', '123');

        $state = $this->getStatePrefix(EveLogin::NAME_DEFAULT) . self::$state;
        $_SESSION['auth_state'] = $state;

        $this->client->setResponse(
            new Response(200, [], '{"access_token": ' . \json_encode($token) . '}'), // for getAccessToken()
            new Response(200, [], '{"keys": ' . \json_encode($keySet) . '}'), // for JWT key set
            new Response(200, [], '{"name": "char name", "corporation_id": 102}'), // getCharactersCharacterId()
            new Response(200, [], '[]'), // postCharactersAffiliation())
            new Response(200, [], '{"name": "name corp", "ticker": "-TC-"}') // getCorporationsCorporationId()
        );

        $response = $this->runApp(
            'GET',
            '/login-callback?state='.$state,
            null,
            null,
            [ClientInterface::class => $this->client, LoggerInterface::class => $this->log],
            [['NEUCORE_EVE_SCOPES', 'read-this'], ['NEUCORE_EVE_DATASOURCE', 'tranquility']]
        );
        $this->assertSame(302, $response->getStatusCode());

        $this->assertSame([], $this->log->getMessages());
        $this->assertSame(
            ['success' => true, 'message' => 'Accounts successfully merged.'],
            $_SESSION['auth_result']
        );
    }

    /**
     * @throws \Exception
     */
    public function testCallback_MailLoginNotAuthorized()
    {
        $var1 = new SystemVariable(SystemVariable::MAIL_CHARACTER);
        $var2 = new SystemVariable(SystemVariable::MAIL_TOKEN);
        $this->helper->getObjectManager()->persist($var1);
        $this->helper->getObjectManager()->persist($var2);
        $this->helper->getObjectManager()->flush();

        $this->helper->addCharacterMain('Test User', 123456, [Role::USER]);
        $this->loginUser(123456);

        list($token, $keySet) = Helper::generateToken([EveLogin::SCOPE_MAIL]);
        $state = $this->getStatePrefix(EveLogin::NAME_MAIL) . self::$state;
        $_SESSION['auth_state'] = $state;

        $this->client->setResponse(
            new Response(200, [], '{"access_token": ' . \json_encode($token) . '}'), // for getAccessToken()
            new Response(200, [], '{"keys": ' . \json_encode($keySet) . '}') // for JWT key set
        );

        $response = $this->runApp(
            'GET',
            '/login-callback?state='.$state,
            null,
            null,
            [ClientInterface::class => $this->client],
            [['NEUCORE_EVE_DATASOURCE', 'tranquility']]
        );
        $this->assertSame(302, $response->getStatusCode());

        $this->assertSame(
            ['success' => false, 'message' => 'Failed to store character.'],
            $_SESSION['auth_result']
        );
    }

    /**
     * @throws \Exception
     */
    public function testCallback_MailLogin()
    {
        $var1 = new SystemVariable(SystemVariable::MAIL_CHARACTER);
        $var2 = new SystemVariable(SystemVariable::MAIL_TOKEN);
        $this->helper->getObjectManager()->persist($var1);
        $this->helper->getObjectManager()->persist($var2);
        $this->helper->getObjectManager()->flush();

        $this->helper->addCharacterMain('Test User', 123456, [Role::USER, Role::SETTINGS]);
        $this->loginUser(123456);

        list($token, $keySet) = Helper::generateToken([EveLogin::SCOPE_MAIL]);
        $state = $this->getStatePrefix(EveLogin::NAME_MAIL) . self::$state;
        $_SESSION['auth_state'] = $state;

        $this->client->setResponse(
            new Response(200, [], '{"access_token": ' . \json_encode($token) . '}'), // for getAccessToken()
            new Response(200, [], '{"keys": ' . \json_encode($keySet) . '}') // for JWT key set
        );

        $response = $this->runApp(
            'GET',
            '/login-callback?state='.$state,
            null,
            null,
            [ClientInterface::class => $this->client],
            [['NEUCORE_EVE_DATASOURCE', 'tranquility']]
        );
        $this->assertSame(302, $response->getStatusCode());

        $this->assertSame(
            ['success' => true, 'message' => 'Mail character authenticated.'],
            $_SESSION['auth_result']
        );
    }

    public function testResult()
    {
        $response = $this->runApp('GET', '/api/user/auth/result');
        $this->assertSame(200, $response->getStatusCode());

        $this->assertSame(
            ['success' => false, 'message' => 'No login attempt recorded.'],
            $this->parseJsonBody($response)
        );
    }

    public function testLogout403()
    {
        $response = $this->runApp('POST', '/api/user/auth/logout');
        $this->assertSame(403, $response->getStatusCode());
    }

    public function testLogout204()
    {
        $this->helper->addCharacterMain('Test User', 123456, [Role::USER]);
        $this->loginUser(123456);

        $response = $this->runApp('POST', '/api/user/auth/logout');
        $this->assertSame(204, $response->getStatusCode());
    }

    public function testCsrfToken403()
    {
        $response = $this->runApp('GET', '/api/user/auth/csrf-token');
        $this->assertSame(403, $response->getStatusCode());
    }

    public function testCsrfToken200()
    {
        $this->helper->addCharacterMain('Test User', 123456, [Role::USER]);
        $this->loginUser(123456);

        $response1 = $this->runApp('GET', '/api/user/auth/csrf-token');
        $response2 = $this->runApp('GET', '/api/user/auth/csrf-token');
        $this->assertSame(200, $response1->getStatusCode());
        $this->assertSame(200, $response2->getStatusCode());
        $this->assertSame($this->parseJsonBody($response1), $this->parseJsonBody($response2));
        $this->assertSame(39, strlen($this->parseJsonBody($response1)));
    }

    private function getStatePrefix(string $eveLoginName): string
    {
        return $eveLoginName . '*';
    }
}
