<?php declare(strict_types=1);

namespace Tests\Functional\Controller\User;

use Neucore\Controller\User\AuthController;
use Neucore\Service\Config;
use Neucore\Entity\Role;
use Neucore\Entity\SystemVariable;
use Neucore\Slim\Session\SessionData;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use League\OAuth2\Client\Provider\GenericProvider;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Tests\Helper;
use Tests\Functional\WebTestCase;
use Tests\OAuthProvider;
use Tests\Client;

class AuthControllerTest extends WebTestCase
{
    /**
     * @var Client
     */
    private $client;

    public function setUp()
    {
        $_SESSION = null;
        $this->client = new Client();
    }

    public function testLogin()
    {
        $response = $this->runApp('GET', '/login');

        $this->assertSame(302, $response->getStatusCode());
        $this->assertContains('eveonline.com/oauth/authorize', $response->getHeader('location')[0]);

        $sess = new SessionData();
        $this->assertSame('/#login', $sess->get('auth_redirect'));
        $this->assertSame(12, strlen($sess->get('auth_state')));
    }

    public function testLoginManagedForbidden()
    {
        $response = $this->runApp('GET', '/login-managed');

        $this->assertSame(403, $response->getStatusCode());
    }

    public function testLoginManaged()
    {
        // activate login "managed"
        $helper = new Helper();
        $helper->emptyDb();
        $setting = new SystemVariable(SystemVariable::ALLOW_LOGIN_MANAGED);
        $setting->setValue('1');
        $helper->getEm()->persist($setting);
        $helper->getEm()->flush();

        $response = $this->runApp('GET', '/login-managed');

        $this->assertSame(302, $response->getStatusCode());
        $this->assertContains('eveonline.com/oauth/authorize', $response->getHeader('location')[0]);

        $sess = new SessionData();
        $this->assertSame('/#login', $sess->get('auth_redirect'));
        $this->assertSame(14, strlen($sess->get('auth_state')));
        $this->assertStringStartsWith(AuthController::STATE_PREFIX_STATUS_MANAGED, $sess->get('auth_state'));
    }

    public function testLoginAlt()
    {
        $response = $this->runApp('GET', '/login-alt');

        $this->assertSame(302, $response->getStatusCode());
        $this->assertContains('eveonline.com/oauth/authorize', $response->getHeader('location')[0]);

        $sess = new SessionData();
        $this->assertSame('/#login-alt', $sess->get('auth_redirect'));
        $this->assertSame(14, strlen($sess->get('auth_state')));
        $this->assertStringStartsWith(AuthController::STATE_PREFIX_ALT, $sess->get('auth_state'));
    }

    public function testLoginMail()
    {
        $response = $this->runApp('GET', '/login-mail');

        $this->assertSame(302, $response->getStatusCode());
        $this->assertContains('eveonline.com/oauth/authorize', $response->getHeader('location')[0]);

        $sess = new SessionData();
        $this->assertSame('/#login-mail', $sess->get('auth_redirect'));
        $this->assertSame(14, strlen($sess->get('auth_state')));
        $this->assertStringStartsWith(AuthController::STATE_PREFIX_MAIL, $sess->get('auth_state'));
    }

    public function testLoginDirector()
    {
        $response = $this->runApp('GET', '/login-director');

        $this->assertSame(302, $response->getStatusCode());
        $this->assertContains('eveonline.com/oauth/authorize', $response->getHeader('location')[0]);

        $sess = new SessionData();
        $this->assertSame('/#login-director', $sess->get('auth_redirect'));
        $this->assertSame(14, strlen($sess->get('auth_state')));
        $this->assertStringStartsWith(AuthController::STATE_PREFIX_DIRECTOR, $sess->get('auth_state'));
    }

    public function testCallbackException()
    {
        $state = '1jdHR64hSdYf';
        $_SESSION = ['auth_state' => $state];

        $response = $this->runApp('GET', '/login-callback?state=INVALID'); // fail early
        $this->assertSame(302, $response->getStatusCode());

        $this->assertfalse(isset($_SESSION['auth_state'])); // test that it was deleted
        $this->assertSame(
            ['success' => false, 'message' => 'OAuth state mismatch.'],
            $_SESSION['auth_result']
        );
    }

    public function testCallbackAuthError()
    {
        (new Helper())->emptyDb();

        $state = '1jdHR64hSdYf';
        $_SESSION = ['auth_state' => $state];

        $this->client->setResponse(
            new Response(200, [], '{"access_token": "t"}'), // for getAccessToken
            new Response(200, [], '{
                "CharacterID": 123,
                "CharacterName": "Na",
                "CharacterOwnerHash": "a",
                "Scopes": "read-this"
            }') // for getResourceOwner
        );

        $log = new Logger('ignore');
        $log->pushHandler(new TestHandler());

        $response = $this->runApp(
            'GET',
            '/login-callback?state='.$state,
            null,
            null,
            [
                GenericProvider::class => new OAuthProvider($this->client),
                LoggerInterface::class => $log,
                Config::class => new Config(['eve' => ['scopes' => 'read-this']]),
            ]
        );
        $this->assertSame(302, $response->getStatusCode());

        // fails because Role "user" is missing in database

        $this->assertSame(
            ['success' => false, 'message' => 'Failed to authenticate user.'],
            $_SESSION['auth_result']
        );
    }

    public function testCallbackSuccess()
    {
        $h = new Helper();
        $h->emptyDb();
        $h->addRoles([Role::USER]);

        $state = '1jdHR64hSdYf';
        $_SESSION = ['auth_state' => $state];

        $this->client->setResponse(
            new Response(200, [], '{"access_token": "t"}'), // for getAccessToken()
            new Response(200, [], '{
                "CharacterID": 123,
                "CharacterName": "Na",
                "CharacterOwnerHash": "a",
                "Scopes": "read-this and-this"
            }') // for getResourceOwner()
        );

        $response = $this->runApp(
            'GET',
            '/login-callback?state='.$state,
            null,
            null,
            [
                GenericProvider::class => new OAuthProvider($this->client),
                Config::class => new Config(['eve' => ['scopes' => 'read-this and-this']]),
            ]
        );
        $this->assertSame(302, $response->getStatusCode());

        $this->assertSame(['success' => true, 'message' => 'Login successful.'], $_SESSION['auth_result']);
    }

    public function testCallbackAltLoginError()
    {
        (new Helper())->emptyDb();

        $state = AuthController::STATE_PREFIX_ALT . '1jdHR64hSdYf';
        $_SESSION = ['auth_state' => $state];

        $this->client->setResponse(
            new Response(200, [], '{"access_token": "t"}'), // for getAccessToken
            new Response(200, [], '{
                "CharacterID": 123,
                "CharacterName": "Na",
                "CharacterOwnerHash": "a",
                "Scopes": "read-this"
            }') // for getResourceOwner
        );

        $log = new Logger('ignore');
        $log->pushHandler(new TestHandler());

        $response = $this->runApp(
            'GET',
            '/login-callback?state='.$state,
            null,
            null,
            [
                GenericProvider::class => new OAuthProvider($this->client),
                LoggerInterface::class => $log,
                Config::class => new Config(['eve' => ['scopes' => 'read-this']]),
            ]
        );
        $this->assertSame(302, $response->getStatusCode());

        // fails because Role "user" is missing in database

        $this->assertSame(
            ['success' => false, 'message' => 'Failed to add alt to account.'],
            $_SESSION['auth_result']
        );
    }

    public function testCallbackAltLogin()
    {
        $h = new Helper();
        $h->emptyDb();

        $h->addCharacterMain('User1', 654, [Role::USER], ['group1']);
        $this->loginUser(654);

        $state = AuthController::STATE_PREFIX_ALT . '1jdHR64hSdYf';
        $_SESSION['auth_state'] = $state;

        $this->client->setResponse(
            new Response(200, [], '{"access_token": "tk"}'), // for getAccessToken()
            new Response(200, [], '{
                "CharacterID": 3,
                "CharacterName": "N3",
                "CharacterOwnerHash": "hs",
                "Scopes": "read-this"
            }') // for getResourceOwner()
        );

        $response = $this->runApp(
            'GET',
            '/login-callback?state='.$state,
            null,
            null,
            [
                GenericProvider::class => new OAuthProvider($this->client),
                Config::class => new Config(['eve' => ['scopes' => 'read-this']]),
            ]
        );
        $this->assertSame(302, $response->getStatusCode());

        $this->assertSame(
            ['success' => true, 'message' => 'Character added to player account.'],
            $_SESSION['auth_result']
        );
    }

    public function testCallbackMailLoginNotAuthorized()
    {
        $h = new Helper();
        $h->emptyDb();

        $var1 = new SystemVariable(SystemVariable::MAIL_CHARACTER);
        $var2 = new SystemVariable(SystemVariable::MAIL_TOKEN);
        $h->getEm()->persist($var1);
        $h->getEm()->persist($var2);
        $h->getEm()->flush();

        $h->addCharacterMain('Test User', 123456, [Role::USER]);
        $this->loginUser(123456);

        $state = AuthController::STATE_PREFIX_MAIL . '1jdHR64hSdYf';
        $_SESSION['auth_state'] = $state;

        $this->client->setResponse(
            new Response(200, [], '{"access_token": "tk"}'), // for getAccessToken()
            new Response(200, [], '{
                "CharacterID": 3,
                "CharacterName": "N3",
                "CharacterOwnerHash": "hs",
                "Scopes": "esi-mail.send_mail.v1"
            }') // for getResourceOwner()
        );

        $response = $this->runApp(
            'GET',
            '/login-callback?state='.$state,
            null,
            null,
            [GenericProvider::class => new OAuthProvider($this->client)]
        );
        $this->assertSame(302, $response->getStatusCode());

        $this->assertSame(
            ['success' => false, 'message' => 'Failed to store character.'],
            $_SESSION['auth_result']
        );
    }

    public function testCallbackMailLogin()
    {
        $h = new Helper();
        $h->emptyDb();

        $var1 = new SystemVariable(SystemVariable::MAIL_CHARACTER);
        $var2 = new SystemVariable(SystemVariable::MAIL_TOKEN);
        $h->getEm()->persist($var1);
        $h->getEm()->persist($var2);
        $h->getEm()->flush();

        $h->addCharacterMain('Test User', 123456, [Role::USER, Role::SETTINGS]);
        $this->loginUser(123456);

        $state = AuthController::STATE_PREFIX_MAIL . '1jdHR64hSdYf';
        $_SESSION['auth_state'] = $state;

        $this->client->setResponse(
            new Response(200, [], '{"access_token": "tk"}'), // for getAccessToken()
            new Response(200, [], '{
                "CharacterID": 3,
                "CharacterName": "N3",
                "CharacterOwnerHash": "hs",
                "Scopes": "esi-mail.send_mail.v1"
            }') // for getResourceOwner()
        );

        $response = $this->runApp(
            'GET',
            '/login-callback?state='.$state,
            null,
            null,
            [GenericProvider::class => new OAuthProvider($this->client)]
        );
        $this->assertSame(302, $response->getStatusCode());

        $this->assertSame(
            ['success' => true, 'message' => 'Mail character authenticated.'],
            $_SESSION['auth_result']
        );
    }

    public function testCallbackDirectorLoginWrongScopes()
    {
        $state = AuthController::STATE_PREFIX_DIRECTOR . '1jdHR64hSdYf';
        $_SESSION['auth_state'] = $state;
        $this->client->setResponse(
            new Response(200, [], '{"access_token": "tk"}'), // for getAccessToken()

            // for getResourceOwner()
            new Response(200, [], '{
                "CharacterID": 3,
                "CharacterName": "N3",
                "CharacterOwnerHash": "hs",
                "Scopes": "esi-characters.read_corporation_roles.v1"
            }')
        );

        $response = $this->runApp('GET', '/login-callback?state='.$state, null, null, [
            GenericProvider::class => new OAuthProvider($this->client)
        ]);
        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame(
            ['success' => false, 'message' => 'Required scopes do not match.'],
            $_SESSION['auth_result']
        );
    }

    public function testCallbackDirectorLoginSuccess()
    {
        $h = new Helper();
        $h->emptyDb();
        $state = AuthController::STATE_PREFIX_DIRECTOR . '1jdHR64hSdYf';
        $_SESSION['auth_state'] = $state;
        $this->client->setResponse(
            // for getAccessToken()
            new Response(200, [], '{"access_token": "tk"}'),

            // for getResourceOwner()
            new Response(200, [], '{
                "CharacterID": 3,
                "CharacterName": "N3",
                "CharacterOwnerHash": "hs",
                "Scopes": "esi-characters.read_corporation_roles.v1 esi-corporations.track_members.v1"
            }'),

            // for getCharactersCharacterId
            new Response(200, [], '{"corporation_id": 123}'),

            // for getCharactersCharacterIdRoles
            new Response(200, [], '{"roles": ["Director"]}'),

            // for getCorporation
            new Response(200, [], '{"name": "c123", "ticker": "-c-"}')
        );

        $response = $this->runApp('GET', '/login-callback?state='.$state, null, null, [
            GenericProvider::class => new OAuthProvider($this->client),
            ClientInterface::class => $this->client
        ]);
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
