<?php
/** @noinspection DuplicatedCode */

declare(strict_types=1);

namespace Tests\Functional\Controller\User;

use Neucore\Controller\User\AuthController;
use Neucore\Entity\EveLogin;
use Neucore\Entity\Role;
use Neucore\Entity\SystemVariable;
use Neucore\Service\SessionData;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Tests\Helper;
use Tests\Functional\WebTestCase;
use Tests\Client;

class AuthControllerTest extends WebTestCase
{
    private static $state = '1jdHR64hSdYf';

    /**
     * @var Client
     */
    private $client;

    /**
     * @var Helper
     */
    private $helper;

    protected function setUp(): void
    {
        $_SESSION = null;
        $this->client = new Client();
        $this->helper = new Helper();
        $this->helper->emptyDb();
    }

    public function testLogin_404()
    {
        $response = $this->runApp('GET', '/login/invalid');
        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame('Login not found.', $response->getReasonPhrase());
        $this->assertSame('Login not found.<br><br><a href="/">Home</a>', $response->getBody()->__toString());
    }

    public function testLogin_CustomLogin()
    {
        // add EveLogin
        $loginId = 'custom1';
        $eveLogin = new EveLogin();
        $eveLogin->setId($loginId);
        $this->helper->getObjectManager()->persist($eveLogin);
        $this->helper->getObjectManager()->flush();

        $response = $this->runApp('GET', "/login/$loginId");

        $this->assertSame(302, $response->getStatusCode());
        $this->assertStringContainsString('eveonline.com/v2/oauth/authorize', $response->getHeader('location')[0]);

        $sess = new SessionData();
        $this->assertSame('/#login-custom', $sess->get('auth_redirect'));
        $this->assertStringStartsWith(AuthController::getStatePrefix($loginId), $sess->get('auth_state'));
    }

    public function testLogin_Default()
    {
        $response = $this->runApp('GET', '/login/'.EveLogin::ID_DEFAULT);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertStringContainsString('eveonline.com/v2/oauth/authorize', $response->getHeader('location')[0]);

        $sess = new SessionData();
        $this->assertSame('/#login', $sess->get('auth_redirect'));
        $this->assertStringStartsWith(AuthController::getStatePrefix(EveLogin::ID_DEFAULT), $sess->get('auth_state'));
    }

    public function testLogin_Alt()
    {
        $response = $this->runApp('GET', '/login/'.EveLogin::ID_ALT);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertStringContainsString('eveonline.com/v2/oauth/authorize', $response->getHeader('location')[0]);

        $sess = new SessionData();
        $this->assertSame('/#login-alt', $sess->get('auth_redirect'));
        $this->assertStringStartsWith(AuthController::getStatePrefix(EveLogin::ID_ALT), $sess->get('auth_state'));
    }

    public function testLogin_ManagedForbidden()
    {
        $response1 = $this->runApp('GET', '/login/'.EveLogin::ID_MANAGED);
        $response2 = $this->runApp('GET', '/login/'.EveLogin::ID_MANAGED_ALT);

        $this->assertSame(403, $response1->getStatusCode());
        $this->assertSame(403, $response2->getStatusCode());
        $this->assertSame('Forbidden', $response1->getReasonPhrase());
        $this->assertSame('Forbidden', $response2->getReasonPhrase());
        $this->assertSame('Forbidden', $response1->getBody()->__toString());
        $this->assertSame('Forbidden', $response2->getBody()->__toString());
    }

    public function testLogin_Managed()
    {
        // activate login "managed"
        $setting = new SystemVariable(SystemVariable::ALLOW_LOGIN_MANAGED);
        $setting->setValue('1');
        $this->helper->getObjectManager()->persist($setting);
        $this->helper->getObjectManager()->flush();

        $response = $this->runApp('GET', '/login/'.EveLogin::ID_MANAGED);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertStringContainsString('eveonline.com/v2/oauth/authorize', $response->getHeader('location')[0]);

        $sess = new SessionData();
        $this->assertSame('/#login', $sess->get('auth_redirect'));
        $this->assertStringStartsWith(AuthController::getStatePrefix(EveLogin::ID_MANAGED), $sess->get('auth_state'));
    }

    public function testLogin_ManagedAlt()
    {
        // activate login "managed"
        $setting = new SystemVariable(SystemVariable::ALLOW_LOGIN_MANAGED);
        $setting->setValue('1');
        $this->helper->getObjectManager()->persist($setting);
        $this->helper->getObjectManager()->flush();

        $response = $this->runApp('GET', '/login/'.EveLogin::ID_MANAGED_ALT);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertStringContainsString('eveonline.com/v2/oauth/authorize', $response->getHeader('location')[0]);

        $sess = new SessionData();
        $this->assertSame('/#login', $sess->get('auth_redirect'));
        $this->assertStringStartsWith(
            AuthController::getStatePrefix(EveLogin::ID_MANAGED_ALT),
            $sess->get('auth_state')
        );
    }

    public function testLogin_Mail()
    {
        $response = $this->runApp('GET', '/login/'.EveLogin::ID_MAIL);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertStringContainsString('eveonline.com/v2/oauth/authorize', $response->getHeader('location')[0]);

        $sess = new SessionData();
        $this->assertSame('/#login-mail', $sess->get('auth_redirect'));
        $this->assertStringStartsWith(AuthController::getStatePrefix(EveLogin::ID_MAIL), $sess->get('auth_state'));
    }

    public function testLogin_Director()
    {
        $response = $this->runApp('GET', '/login/'.EveLogin::ID_DIRECTOR);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertStringContainsString('eveonline.com/v2/oauth/authorize', $response->getHeader('location')[0]);

        $sess = new SessionData();
        $this->assertSame('/#login-director', $sess->get('auth_redirect'));
        $this->assertStringStartsWith(AuthController::getStatePrefix(EveLogin::ID_DIRECTOR), $sess->get('auth_state'));
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

    public function testCallback_CustomLogin()
    {
        # TODO
    }

    public function testCallback_CustomLoginRoleError()
    {
        # TODO
    }

    /**
     * @throws \Exception
     */
    public function testCallback_DefaultAuthError()
    {
        $state = AuthController::getStatePrefix(EveLogin::ID_DEFAULT) . self::$state;
        $_SESSION = ['auth_state' => $state, 'auth_result' => null];

        list($token, $keySet) = Helper::generateToken(['read-this']);

        $this->client->setResponse(
            new Response(200, [], '{"access_token": ' . json_encode($token). '}'), // for getAccessToken
            new Response(200, [], '{"keys": ' . json_encode($keySet). '}') // for JTW key set
        );

        $log = new Logger('ignore');
        $log->pushHandler(new TestHandler());

        $response = $this->runApp(
            'GET',
            '/login-callback?state='.$state,
            null,
            null,
            [ClientInterface::class => $this->client, LoggerInterface::class => $log],
            ['NEUCORE_EVE_SCOPES=read-this', 'NEUCORE_EVE_DATASOURCE=tranquility']
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
        $this->helper->getEm()->persist((new EveLogin())->setId(EveLogin::ID_DEFAULT));
        $this->helper->addRoles(
            [Role::USER, Role::TRACKING, Role::WATCHLIST, Role::WATCHLIST_MANAGER, Role::GROUP_MANAGER]
        );

        list($token, $keySet) = Helper::generateToken(['read-this', 'and-this']);
        $state = AuthController::getStatePrefix(EveLogin::ID_DEFAULT) . self::$state;
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
            ['NEUCORE_EVE_SCOPES=read-this and-this', 'NEUCORE_EVE_DATASOURCE=tranquility']
        );
        $this->assertSame(302, $response->getStatusCode());

        $this->assertSame(['success' => true, 'message' => 'Login successful.'], $_SESSION['auth_result']);
    }

    /**
     * @throws \Exception
     */
    public function testCallback_AltLoginError()
    {
        list($token, $keySet) = Helper::generateToken(['read-this']);
        $state = AuthController::getStatePrefix(EveLogin::ID_ALT) . self::$state;
        $_SESSION = ['auth_state' => $state, 'auth_result' => null];

        $this->client->setResponse(
            new Response(200, [], '{"access_token": ' . \json_encode($token) . '}'), // for getAccessToken()
            new Response(200, [], '{"keys": ' . \json_encode($keySet) . '}') // for JWT key set
        );

        $log = new Logger('ignore');
        $log->pushHandler(new TestHandler());

        $response = $this->runApp(
            'GET',
            '/login-callback?state='.$state,
            null,
            null,
            [
                ClientInterface::class => $this->client,
                LoggerInterface::class => $log,
            ],
            ['NEUCORE_EVE_SCOPES=read-this', 'NEUCORE_EVE_DATASOURCE=tranquility']
        );
        $this->assertSame(302, $response->getStatusCode());

        // fails because Role "user" is missing in database

        $this->assertSame(
            ['success' => false, 'message' => 'Failed to add alt to account.'],
            $_SESSION['auth_result']
        );
    }

    /**
     * @throws \Exception
     */
    public function testCallback_AltLogin()
    {
        $this->helper->addRoles([Role::TRACKING, Role::WATCHLIST, Role::WATCHLIST_MANAGER, Role::GROUP_MANAGER]);

        $this->helper->addCharacterMain('User1', 654, [Role::USER], ['group1']);
        $this->loginUser(654);

        list($token, $keySet) = Helper::generateToken(['read-this']);
        $state = AuthController::getStatePrefix(EveLogin::ID_ALT) . self::$state;
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
            ['NEUCORE_EVE_SCOPES=read-this', 'NEUCORE_EVE_DATASOURCE=tranquility']
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
        $state = AuthController::getStatePrefix(EveLogin::ID_MAIL) . self::$state;
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
            ['NEUCORE_EVE_DATASOURCE=tranquility']
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
        $state = AuthController::getStatePrefix(EveLogin::ID_MAIL) . self::$state;
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
            ['NEUCORE_EVE_DATASOURCE=tranquility']
        );
        $this->assertSame(302, $response->getStatusCode());

        $this->assertSame(
            ['success' => true, 'message' => 'Mail character authenticated.'],
            $_SESSION['auth_result']
        );
    }

    /**
     * @throws \Exception
     */
    public function testCallback_DirectorLoginWrongScopes()
    {
        list($token, $keySet) = Helper::generateToken([EveLogin::SCOPE_ROLES]);
        $state = AuthController::getStatePrefix(EveLogin::ID_DIRECTOR) . self::$state;
        $_SESSION['auth_state'] = $state;
        $this->client->setResponse(
            new Response(200, [], '{"access_token": ' . \json_encode($token) . '}'), // for getAccessToken()

            // for JWT key set
            new Response(200, [], '{"keys": ' . \json_encode($keySet) . '}')
        );

        $response = $this->runApp(
            'GET',
            '/login-callback?state='.$state,
            null,
            null,
            [ClientInterface::class => $this->client],
            ['NEUCORE_EVE_DATASOURCE=tranquility']
        );
        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame(
            ['success' => false, 'message' => 'Required scopes do not match.'],
            $_SESSION['auth_result']
        );
    }

    /**
     * @throws \Exception
     */
    public function testCallback_DirectorLoginSuccess()
    {
        list($token, $keySet) = Helper::generateToken(
            [EveLogin::SCOPE_ROLES, EveLogin::SCOPE_TRACKING, EveLogin::SCOPE_STRUCTURES],
            'hs'
        );

        $state = AuthController::getStatePrefix(EveLogin::ID_DIRECTOR) . self::$state;
        $_SESSION['auth_state'] = $state;
        $this->client->setResponse(
            // for getAccessToken()
            new Response(200, [], '{"access_token": ' . \json_encode($token) . '}'),

            // for JWT key set
            new Response(200, [], '{"keys": ' . \json_encode($keySet) . '}'),

            // for getCharactersCharacterId
            new Response(200, [], '{"corporation_id": 123}'),

            // for getCharactersCharacterIdRoles
            new Response(200, [], '{"roles": ["Director"]}'),

            // for getCorporation
            new Response(200, [], '{"name": "c123", "ticker": "-c-"}')
        );

        $response = $this->runApp(
            'GET',
            '/login-callback?state='.$state,
            null,
            null,
            [ClientInterface::class => $this->client],
            ['NEUCORE_EVE_DATASOURCE=tranquility']
        );
        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame(
            ['success' => true, 'message' => 'Character with director roles added.'],
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
}
