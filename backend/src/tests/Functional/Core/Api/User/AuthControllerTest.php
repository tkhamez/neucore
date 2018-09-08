<?php declare(strict_types=1);

namespace Tests\Functional\Core\Api\User;

use Brave\Core\Config;
use Brave\Core\Roles;
use Brave\Slim\Session\SessionData;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use League\OAuth2\Client\Provider\GenericProvider;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Tests\Helper;
use Tests\Functional\WebTestCase;
use Tests\OAuthTestProvider;

class AuthControllerTest extends WebTestCase
{
    /**
     * @var \PHPUnit\Framework\MockObject\MockObject|ClientInterface
     */
    private $client;

    public function setUp()
    {
        $_SESSION = null;
        $this->client = $this->createMock(ClientInterface::class);
    }

    public function testLoginUrl200()
    {
        $redirect = '/index.html#auth';
        $response = $this->runApp('GET', '/api/user/auth/login-url?redirect='.urlencode($redirect));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['application/json;charset=utf-8'], $response->getHeader('Content-Type'));

        $body = $this->parseJsonBody($response);

        $this->assertContains('https://login.eveonline.com', $body);

        $sess = new SessionData();
        $this->assertSame($redirect, $sess->get('auth_redirect'));
        $this->assertSame(12, strlen($sess->get('auth_state')));
    }

    public function testLoginUrl204()
    {
        $h = new Helper();
        $h->emptyDb();
        $h->addCharacterMain('U2', 2, [Roles::USER]);
        $this->loginUser(2);

        $response = $this->runApp('GET', '/api/user/auth/login-url');
        $this->assertSame(204, $response->getStatusCode());
        $this->assertSame('', $response->getBody()->__toString());
    }

    public function testLoginAltUrl200()
    {
        $h = new Helper();
        $h->emptyDb();
        $h->addCharacterMain('User 1', 456, [Roles::USER], ['group-1']);
        $this->loginUser(456);

        $redirect = '/index.html#auth-alt';
        $response = $this->runApp('GET', '/api/user/auth/login-alt-url?redirect='.urlencode($redirect));

        $this->assertSame(200, $response->getStatusCode());

        $body = $this->parseJsonBody($response);

        $this->assertContains('https://login.eveonline.com', $body);

        $sess = new SessionData();
        $this->assertSame($redirect, $sess->get('auth_redirect'));
        $this->assertSame('*', substr($sess->get('auth_state'), 0, 1));
        $this->assertSame(13, strlen($sess->get('auth_state')));
    }

    public function testLoginAltUrl403()
    {
        $response = $this->runApp('GET', '/api/user/auth/login-alt-url');
        $this->assertSame(403, $response->getStatusCode());
    }

    public function testCallbackStateError()
    {
        $state = '1jdHR64hSdYf';
        $_SESSION = ['auth_state' => $state];

        $response = $this->runApp('GET', '/api/user/auth/callback?state=INVALID'); // fail early
        $this->assertSame(302, $response->getStatusCode());

        $sess = new SessionData();
        $this->assertSame(null, $sess->get('auth_state')); // test that it was deleted
        $this->assertSame(
            ['success' => false, 'message' => 'OAuth state mismatch.'],
            $sess->get('auth_result')
        );
    }

    public function testCallbackAccessTokenException()
    {
        $state = '1jdHR64hSdYf';
        $_SESSION = ['auth_state' => $state];

        $this->client->method('send')->willReturn(new Response(500)); // for getAccessToken

        $log = new Logger('ignore');
        $log->pushHandler(new TestHandler());

        $response = $this->runApp('GET', '/api/user/auth/callback?state='.$state, null, null, [
            GenericProvider::class => new OAuthTestProvider($this->client),
            LoggerInterface::class => $log
        ]);
        $this->assertSame(302, $response->getStatusCode());

        $sess = new SessionData();
        $this->assertSame(
            ['success' => false, 'message' => 'Error when requesting the token.'],
            $sess->get('auth_result')
        );
    }

    public function testCallbackResourceOwnerException()
    {
        $state = '1jdHR64hSdYf';
        $_SESSION = ['auth_state' => $state];

        $this->client->method('send')->willReturn(
            new Response(200, [], '{"access_token": "t"}'), // for getAccessToken
            new Response(500) // for getResourceOwner
        );

        $log = new Logger('ignore');
        $log->pushHandler(new TestHandler());

        $response = $this->runApp('GET', '/api/user/auth/callback?state='.$state, null, null, [
            GenericProvider::class => new OAuthTestProvider($this->client),
            LoggerInterface::class => $log
        ]);
        $this->assertSame(302, $response->getStatusCode());

        $sess = new SessionData();
        $this->assertSame(
            ['success' => false, 'message' => 'Error obtaining Character ID.'],
            $sess->get('auth_result')
        );
    }

    public function testCallbackResourceOwnerError()
    {
        $state = '1jdHR64hSdYf';
        $_SESSION = ['auth_state' => $state];

        $this->client->method('send')->willReturn(
            new Response(200, [], '{"access_token": "t"}'), // for getAccessToken
            new Response(200, [], 'invalid') // for getResourceOwner
        );

        $response = $this->runApp('GET', '/api/user/auth/callback?state='.$state, null, null, [
            GenericProvider::class => new OAuthTestProvider($this->client),
            LoggerInterface::class => (new Logger('Test'))->pushHandler(new TestHandler())
        ]);
        $this->assertSame(302, $response->getStatusCode());

        $sess = new SessionData();
        $this->assertSame(
            ['success' => false, 'message' => 'Error obtaining Character ID.'],
            $sess->get('auth_result')
        );
    }

    public function testCallbackScopesMismatch()
    {
        $state = '1jdHR64hSdYf';
        $sess = new SessionData();

        $this->client->method('send')->willReturn(
            new Response(200, [], '{"access_token": "t"}'), // for getAccessToken
            new Response(200, [], '{
                "CharacterID": 123,
                "CharacterName": "Na",
                "CharacterOwnerHash": "a",
                "Scopes": "have-this"
            }'), // for getResourceOwner
            new Response(200, [], '{"access_token": "t"}'), // for getAccessToken
            new Response(200, [], '{
                "CharacterID": 123,
                "CharacterName": "Na",
                "CharacterOwnerHash": "a",
                "Scopes": "have-this"
            }')
        );

        // missing scope
        $_SESSION = ['auth_state' => $state];
        $this->runApp('GET', '/api/user/auth/callback?state='.$state, null, null, [
            GenericProvider::class => new OAuthTestProvider($this->client),
            Config::class => new Config(['eve' => ['scopes' => 'do-not-have-this']]),
        ]);
        $this->assertSame(
            ['success' => false, 'message' => 'Required scopes do not match.'],
            $sess->get('auth_result')
        );

        // additional scope
        $_SESSION = ['auth_state' => $state];
        $this->runApp('GET', '/api/user/auth/callback?state='.$state, null, null, [
            GenericProvider::class => new OAuthTestProvider($this->client),
            Config::class => new Config(['eve' => ['scopes' => 'have-this and-this']]),
        ]);
        $this->assertSame(
            ['success' => false, 'message' => 'Required scopes do not match.'],
            $sess->get('auth_result')
        );
    }

    public function testCallbackAuthError()
    {
        (new Helper())->emptyDb();

        $state = '1jdHR64hSdYf';
        $_SESSION = ['auth_state' => $state];

        $this->client->method('send')->willReturn(
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

        $response = $this->runApp('GET', '/api/user/auth/callback?state='.$state, null, null, [
            GenericProvider::class => new OAuthTestProvider($this->client),
            LoggerInterface::class => $log,
            Config::class => new Config(['eve' => ['scopes' => 'read-this']]),
        ]);
        $this->assertSame(302, $response->getStatusCode());

        // fails because Role "user" is missing in database

        $sess = new SessionData();
        $this->assertSame(
            ['success' => false, 'message' => 'Could not authenticate user.'],
            $sess->get('auth_result')
        );
    }

    public function testCallbackSuccess()
    {
        $h = new Helper();
        $h->emptyDb();
        $h->addRoles([Roles::USER]);

        $state = '1jdHR64hSdYf';
        $_SESSION = ['auth_state' => $state];

        $this->client->method('send')->willReturn(
            new Response(200, [], '{"access_token": "t"}'), // for getAccessToken()
            new Response(200, [], '{
                "CharacterID": 123,
                "CharacterName": "Na",
                "CharacterOwnerHash": "a",
                "Scopes": "read-this and-this"
            }') // for getResourceOwner()
        );

        $response = $this->runApp('GET', '/api/user/auth/callback?state='.$state, null, null, [
            GenericProvider::class => new OAuthTestProvider($this->client),
            Config::class => new Config(['eve' => ['scopes' => 'read-this and-this']]),
        ]);
        $this->assertSame(302, $response->getStatusCode());

        $sess = new SessionData();
        $this->assertSame(['success' => true, 'message' => 'Login successful.'], $sess->get('auth_result'));
    }

    public function testCallbackAltLogin()
    {
        $h = new Helper();
        $h->emptyDb();

        $h->addCharacterMain('User1', 654, [Roles::USER], ['group1']);
        $this->loginUser(654);

        $state = '*1jdHR64hSdYf';
        $_SESSION['auth_state'] = $state;

        $this->client->method('send')->willReturn(
            new Response(200, [], '{"access_token": "tk"}'), // for getAccessToken()
            new Response(200, [], '{
                "CharacterID": 3,
                "CharacterName": "N3",
                "CharacterOwnerHash": "hs",
                "Scopes": "read-this"
            }') // for getResourceOwner()
        );

        $response = $this->runApp('GET', '/api/user/auth/callback?state='.$state, null, null, [
            GenericProvider::class => new OAuthTestProvider($this->client),
            Config::class => new Config(['eve' => ['scopes' => 'read-this']]),
        ]);
        $this->assertSame(302, $response->getStatusCode());

        $sess = new SessionData();
        $this->assertSame(
            ['success' => true, 'message' => 'Character added to player account.'],
            $sess->get('auth_result')
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
        $h->addCharacterMain('Test User', 123456, [Roles::USER]);
        $this->loginUser(123456);

        $response = $this->runApp('POST', '/api/user/auth/logout');
        $this->assertSame(204, $response->getStatusCode());
    }
}
