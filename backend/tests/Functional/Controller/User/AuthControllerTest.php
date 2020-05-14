<?php
/** @noinspection DuplicatedCode */

declare(strict_types=1);

namespace Tests\Functional\Controller\User;

use Neucore\Api;
use Neucore\Controller\User\AuthController;
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

    protected function setUp(): void
    {
        $_SESSION = null;
        $this->client = new Client();
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
        (new Helper())->emptyDb();

        $response = $this->runApp('GET', '/login-managed');

        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame('Forbidden', $response->getReasonPhrase());
        $this->assertSame('Forbidden', $response->getBody()->__toString());
    }

    public function testLoginManaged()
    {
        // activate login "managed"
        $helper = new Helper();
        $helper->emptyDb();
        $setting = new SystemVariable(SystemVariable::ALLOW_LOGIN_MANAGED);
        $setting->setValue('1');
        $helper->getObjectManager()->persist($setting);
        $helper->getObjectManager()->flush();

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
        (new Helper())->emptyDb();

        $response = $this->runApp('GET', '/login-managed-alt');

        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame('Forbidden', $response->getReasonPhrase());
        $this->assertSame('Forbidden', $response->getBody()->__toString());
    }

    public function testLoginManagedAlt()
    {
        // activate login "managed"
        $helper = new Helper();
        $helper->emptyDb();
        $setting = new SystemVariable(SystemVariable::ALLOW_LOGIN_MANAGED);
        $setting->setValue('1');
        $helper->getObjectManager()->persist($setting);
        $helper->getObjectManager()->flush();

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
        (new Helper())->emptyDb();

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
        $h = new Helper();
        $h->emptyDb();
        $h->addRoles([Role::USER, Role::TRACKING, Role::WATCHLIST]);

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
        (new Helper())->emptyDb();

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
        $h = new Helper();
        $h->emptyDb();
        $h->addRoles([Role::TRACKING, Role::WATCHLIST]);

        $h->addCharacterMain('User1', 654, [Role::USER], ['group1']);
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
        $h = new Helper();
        $h->emptyDb();

        $var1 = new SystemVariable(SystemVariable::MAIL_CHARACTER);
        $var2 = new SystemVariable(SystemVariable::MAIL_TOKEN);
        $h->getObjectManager()->persist($var1);
        $h->getObjectManager()->persist($var2);
        $h->getObjectManager()->flush();

        $h->addCharacterMain('Test User', 123456, [Role::USER]);
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
        $h = new Helper();
        $h->emptyDb();

        $var1 = new SystemVariable(SystemVariable::MAIL_CHARACTER);
        $var2 = new SystemVariable(SystemVariable::MAIL_TOKEN);
        $h->getObjectManager()->persist($var1);
        $h->getObjectManager()->persist($var2);
        $h->getObjectManager()->flush();

        $h->addCharacterMain('Test User', 123456, [Role::USER, Role::SETTINGS]);
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

        $h = new Helper();
        $h->emptyDb();
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
        $h = new Helper();
        $h->emptyDb();
        $h->addCharacterMain('Test User', 123456, [Role::USER]);
        $this->loginUser(123456);

        $response = $this->runApp('POST', '/api/user/auth/logout');
        $this->assertSame(204, $response->getStatusCode());
    }
}
