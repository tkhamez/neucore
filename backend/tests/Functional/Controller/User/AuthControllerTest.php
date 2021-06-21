<?php
/** @noinspection DuplicatedCode */

declare(strict_types=1);

namespace Tests\Functional\Controller\User;

use Neucore\Api;
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

    public function testLogin()
    {
        $response = $this->runApp('GET', '/login');

        $this->assertSame(302, $response->getStatusCode());
        $this->assertStringContainsString('eveonline.com/v2/oauth/authorize', $response->getHeader('location')[0]);

        $sess = new SessionData();
        $this->assertSame('/#login', $sess->get('auth_redirect'));
        $this->assertSame(12, strlen($sess->get('auth_state')));
    }

    public function testLoginManagedForbidden()
    {
        $response = $this->runApp('GET', '/login-managed');

        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame('Forbidden', $response->getReasonPhrase());
        $this->assertSame('Forbidden', $response->getBody()->__toString());
    }

    public function testLoginManaged()
    {
        // activate login "managed"
        $setting = new SystemVariable(SystemVariable::ALLOW_LOGIN_MANAGED);
        $setting->setValue('1');
        $this->helper->getObjectManager()->persist($setting);
        $this->helper->getObjectManager()->flush();

        $response = $this->runApp('GET', '/login-managed');

        $this->assertSame(302, $response->getStatusCode());
        $this->assertStringContainsString('eveonline.com/v2/oauth/authorize', $response->getHeader('location')[0]);

        $sess = new SessionData();
        $this->assertSame('/#login', $sess->get('auth_redirect'));
        $this->assertSame(14, strlen($sess->get('auth_state')));
        $this->assertStringStartsWith(AuthController::STATE_PREFIX_STATUS_MANAGED, $sess->get('auth_state'));
    }

    public function testLoginManagedAltForbidden()
    {
        $response = $this->runApp('GET', '/login-managed-alt');

        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame('Forbidden', $response->getReasonPhrase());
        $this->assertSame('Forbidden', $response->getBody()->__toString());
    }

    public function testLoginManagedAlt()
    {
        // activate login "managed"
        $setting = new SystemVariable(SystemVariable::ALLOW_LOGIN_MANAGED);
        $setting->setValue('1');
        $this->helper->getObjectManager()->persist($setting);
        $this->helper->getObjectManager()->flush();

        $response = $this->runApp('GET', '/login-managed-alt');

        $this->assertSame(302, $response->getStatusCode());
        $this->assertStringContainsString('eveonline.com/v2/oauth/authorize', $response->getHeader('location')[0]);

        $sess = new SessionData();
        $this->assertSame('/#login', $sess->get('auth_redirect'));
        $this->assertSame(14, strlen($sess->get('auth_state')));
        $this->assertStringStartsWith(AuthController::STATE_PREFIX_STATUS_MANAGED_ALT, $sess->get('auth_state'));
    }

    public function testLoginAlt()
    {
        $response = $this->runApp('GET', '/login-alt');

        $this->assertSame(302, $response->getStatusCode());
        $this->assertStringContainsString('eveonline.com/v2/oauth/authorize', $response->getHeader('location')[0]);

        $sess = new SessionData();
        $this->assertSame('/#login-alt', $sess->get('auth_redirect'));
        $this->assertSame(14, strlen($sess->get('auth_state')));
        $this->assertStringStartsWith(AuthController::STATE_PREFIX_ALT, $sess->get('auth_state'));
    }

    public function testLoginMail()
    {
        $response = $this->runApp('GET', '/login-mail');

        $this->assertSame(302, $response->getStatusCode());
        $this->assertStringContainsString('eveonline.com/v2/oauth/authorize', $response->getHeader('location')[0]);

        $sess = new SessionData();
        $this->assertSame('/#login-mail', $sess->get('auth_redirect'));
        $this->assertSame(14, strlen($sess->get('auth_state')));
        $this->assertStringStartsWith(AuthController::STATE_PREFIX_MAIL, $sess->get('auth_state'));
    }

    public function testLoginDirector()
    {
        $response = $this->runApp('GET', '/login-director');

        $this->assertSame(302, $response->getStatusCode());
        $this->assertStringContainsString('eveonline.com/v2/oauth/authorize', $response->getHeader('location')[0]);

        $sess = new SessionData();
        $this->assertSame('/#login-director', $sess->get('auth_redirect'));
        $this->assertSame(14, strlen($sess->get('auth_state')));
        $this->assertStringStartsWith(AuthController::STATE_PREFIX_DIRECTOR, $sess->get('auth_state'));
    }

    public function testCallbackException()
    {
        $state = '1jdHR64hSdYf';
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
    public function testCallbackAuthError()
    {
        $state = '1jdHR64hSdYf';
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
            [
                ClientInterface::class => $this->client,
                LoggerInterface::class => $log,
            ],
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
    public function testCallbackSuccess()
    {
        $this->helper->getEm()->persist((new EveLogin())->setId(EveLogin::ID_DEFAULT));
        $this->helper->addRoles(
            [Role::USER, Role::TRACKING, Role::WATCHLIST, Role::WATCHLIST_MANAGER, Role::GROUP_MANAGER]
        );

        list($token, $keySet) = Helper::generateToken(['read-this', 'and-this']);
        $state = '1jdHR64hSdYf';
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
    public function testCallbackAltLoginError()
    {
        list($token, $keySet) = Helper::generateToken(['read-this']);
        $state = AuthController::STATE_PREFIX_ALT . '1jdHR64hSdYf';
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
    public function testCallbackAltLogin()
    {
        $this->helper->addRoles([Role::TRACKING, Role::WATCHLIST, Role::WATCHLIST_MANAGER, Role::GROUP_MANAGER]);

        $this->helper->addCharacterMain('User1', 654, [Role::USER], ['group1']);
        $this->loginUser(654);

        list($token, $keySet) = Helper::generateToken(['read-this']);
        $state = AuthController::STATE_PREFIX_ALT . '1jdHR64hSdYf';
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
    public function testCallbackMailLoginNotAuthorized()
    {
        $var1 = new SystemVariable(SystemVariable::MAIL_CHARACTER);
        $var2 = new SystemVariable(SystemVariable::MAIL_TOKEN);
        $this->helper->getObjectManager()->persist($var1);
        $this->helper->getObjectManager()->persist($var2);
        $this->helper->getObjectManager()->flush();

        $this->helper->addCharacterMain('Test User', 123456, [Role::USER]);
        $this->loginUser(123456);

        list($token, $keySet) = Helper::generateToken([Api::SCOPE_MAIL]);
        $state = AuthController::STATE_PREFIX_MAIL . '1jdHR64hSdYf';
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
    public function testCallbackMailLogin()
    {
        $var1 = new SystemVariable(SystemVariable::MAIL_CHARACTER);
        $var2 = new SystemVariable(SystemVariable::MAIL_TOKEN);
        $this->helper->getObjectManager()->persist($var1);
        $this->helper->getObjectManager()->persist($var2);
        $this->helper->getObjectManager()->flush();

        $this->helper->addCharacterMain('Test User', 123456, [Role::USER, Role::SETTINGS]);
        $this->loginUser(123456);

        list($token, $keySet) = Helper::generateToken([Api::SCOPE_MAIL]);
        $state = AuthController::STATE_PREFIX_MAIL . '1jdHR64hSdYf';
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
    public function testCallbackDirectorLoginWrongScopes()
    {
        list($token, $keySet) = Helper::generateToken([API::SCOPE_ROLES]);
        $state = AuthController::STATE_PREFIX_DIRECTOR . '1jdHR64hSdYf';
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
    public function testCallbackDirectorLoginSuccess()
    {
        list($token, $keySet) = Helper::generateToken(
            [API::SCOPE_ROLES, API::SCOPE_TRACKING, Api::SCOPE_STRUCTURES],
            'hs'
        );

        $state = AuthController::STATE_PREFIX_DIRECTOR . '1jdHR64hSdYf';
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
