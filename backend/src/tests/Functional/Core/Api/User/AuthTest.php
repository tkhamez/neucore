<?php
namespace Tests\Functional\Core\Api\User;

use Brave\Core\Roles;
use Brave\Slim\Session\SessionData;
use League\OAuth2\Client\Provider\GenericProvider;
use League\OAuth2\Client\Token\AccessToken;
use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use Monolog\Logger;
use Monolog\Handler\TestHandler;
use Psr\Log\LoggerInterface;
use Tests\Functional\WebTestCase;
use Tests\Helper;

class AuthTest extends WebTestCase
{

    public function setUp()
    {
        $_SESSION = null;
    }

    public function testGetLogin200()
    {
        $redirect = '/index.html#auth';
        $response = $this->runApp('GET', '/api/user/auth/login-url?redirect='.urlencode($redirect));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['application/json;charset=utf-8'], $response->getHeader('Content-Type'));

        $body = $this->parseJsonBody($response);

        $this->assertContains('https://login.eveonline.com', $body);

        $sess = new SessionData();
        $this->assertSame($redirect, $sess->get('auth_redirect'));
        $this->assertSame(32, strlen($sess->get('auth_state')));
    }

    public function testGetLogin204()
    {
        $h = new Helper();
        $h->emptyDb();
        $h->addCharacterMain('U2', 2, [Roles::USER]);
        $this->loginUser(2);

        $response = $this->runApp('GET', '/api/user/auth/login-url');
        $this->assertSame(204, $response->getStatusCode());
        $this->assertSame('', $response->getBody()->__toString());
    }

    public function testGetLoginAlt200()
    {
        $h = new Helper();
        $h->emptyDb();
        $h->addCharacterMain('User 1', 456, [Roles::USER], ['group-1']);
        $this->loginUser(456);

        $redirect = '/index.html#auth-alt';
        $response = $this->runApp('GET', '/api/user/auth/login-alt-url?redirect='.urlencode($redirect));

        $this->assertSame(200, $response->getStatusCode());

        $body = $this->parseJsonBody($response);

        $this->assertSame(1, count($body));
        $this->assertContains('https://login.eveonline.com', $body);

        $sess = new SessionData();
        $this->assertSame($redirect, $sess->get('auth_redirect'));
        $this->assertSame('t', substr($sess->get('auth_state'), 0, 1));
        $this->assertSame(33, strlen($sess->get('auth_state')));
    }

    public function testGetLoginAlt403()
    {
        $response = $this->runApp('GET', '/api/user/auth/login-alt-url');
        $this->assertSame(403, $response->getStatusCode());
    }

    public function testGetCallbackStateError()
    {
        $state = 'd2c55ec4cfefe6224a500f4127bcee31';
        $_SESSION = ['auth_state' => $state];

        $response = $this->runApp('GET', '/api/user/auth/callback?state=INVALID'); // fail early
        $this->assertSame(302, $response->getStatusCode());

        $sess = new SessionData();
        $this->assertSame(null, $sess->get('auth_state')); // test that it was deleted
        $this->assertSame(['success' => false, 'message' => 'OAuth state mismatch'], $sess->get('auth_result'));
    }

    public function testGetCallbackAccessTokenException()
    {
        $state = 'd2c55ec4cfefe6224a500f4127bcee31';
        $_SESSION = ['auth_state' => $state];

        $sso = $this->createMock(GenericProvider::class);
        $sso->method('getAccessToken')->will($this->throwException(new \Exception));

        $log = new Logger('ignore');
        $log->pushHandler(new TestHandler());

        $response = $this->runApp('GET', '/api/user/auth/callback?state='.$state, null, null, [
            GenericProvider::class => $sso,
            LoggerInterface::class => $log
        ]);
        $this->assertSame(302, $response->getStatusCode());

        $sess = new SessionData();
        $this->assertSame(['success' => false, 'message' => 'request token error'], $sess->get('auth_result'));
    }

    public function testGetCallbackResourceOwnerException()
    {
        $state = 'd2c55ec4cfefe6224a500f4127bcee31';
        $_SESSION = ['auth_state' => $state];

        $sso = $this->createMock(GenericProvider::class);
        $sso->method('getAccessToken')->willReturn(new AccessToken(['access_token' => 't']));
        $sso->method('getResourceOwner')->will($this->throwException(new \Exception));

        $log = new Logger('ignore');
        $log->pushHandler(new TestHandler());

        $response = $this->runApp('GET', '/api/user/auth/callback?state='.$state, null, null, [
            GenericProvider::class => $sso,
            LoggerInterface::class => $log
        ]);
        $this->assertSame(302, $response->getStatusCode());

        $sess = new SessionData();
        $this->assertSame(['success' => false, 'message' => 'request verify error'], $sess->get('auth_result'));
    }

    public function testGetCallbackResourceOwnerError()
    {
        $state = 'd2c55ec4cfefe6224a500f4127bcee31';
        $_SESSION = ['auth_state' => $state];

        $sso = $this->createMock(GenericProvider::class);
        $sso->method('getAccessToken')->willReturn(new AccessToken(['access_token' => 't']));

        $ro = $this->createMock(ResourceOwnerInterface::class);
        $ro->method('toArray')->willReturn(['invalid']);
        $sso->method('getResourceOwner')->willReturn($ro);

        $response = $this->runApp('GET', '/api/user/auth/callback?state='.$state, null, null, [
            GenericProvider::class => $sso
        ]);
        $this->assertSame(302, $response->getStatusCode());

        $sess = new SessionData();
        $this->assertSame(['success' => false, 'message' => 'request verify error'], $sess->get('auth_result'));
    }

    public function testGetCallbackAuthError()
    {
        (new Helper())->emptyDb();

        $state = 'd2c55ec4cfefe6224a500f4127bcee31';
        $_SESSION = ['auth_state' => $state];

        $sso = $this->createMock(GenericProvider::class);
        $sso->method('getAccessToken')->willReturn(new AccessToken(['access_token' => 't']));

        $ro = $this->createMock(ResourceOwnerInterface::class);
        $ro->method('toArray')->willReturn(['CharacterID' => 123, 'CharacterName' => 'Na', 'CharacterOwnerHash' => 'a']);
        $sso->method('getResourceOwner')->willReturn($ro);

        $log = new Logger('ignore');
        $log->pushHandler(new TestHandler());

        $response = $this->runApp('GET', '/api/user/auth/callback?state='.$state, null, null, [
            GenericProvider::class => $sso,
            LoggerInterface::class => $log
        ]);
        $this->assertSame(302, $response->getStatusCode());

        // fails because Role "user" is missing in database

        $sess = new SessionData();
        $this->assertSame(['success' => false, 'message' => 'Could not authenticate user.'], $sess->get('auth_result'));
    }

    public function testGetCallback()
    {
        $h = new Helper();
        $h->emptyDb();
        $h->addRoles([Roles::USER]);

        $state = 'd2c55ec4cfefe6224a500f4127bcee31';
        $_SESSION = ['auth_state' => $state];

        $sso = $this->createMock(GenericProvider::class);
        $sso->method('getAccessToken')->willReturn(new AccessToken(['access_token' => 't']));

        $ro = $this->createMock(ResourceOwnerInterface::class);
        $ro->method('toArray')->willReturn(['CharacterID' => 123, 'CharacterName' => 'Na', 'CharacterOwnerHash' => 'a']);
        $sso->method('getResourceOwner')->willReturn($ro);

        $response = $this->runApp('GET', '/api/user/auth/callback?state='.$state, null, null, [
            GenericProvider::class => $sso
        ]);
        $this->assertSame(302, $response->getStatusCode());

        $sess = new SessionData();
        $this->assertSame(['success' => true, 'message' => 'Login successful.'], $sess->get('auth_result'));
    }

    public function testGetCallbackAltLogin()
    {
        $h = new Helper();
        $h->emptyDb();

        $h->addCharacterMain('User1', 654, [Roles::USER], ['group1']);
        $this->loginUser(654);

        $state = 'td2c55ec4cfefe6224a500f4127bcee31';
        $_SESSION['auth_state'] = $state;

        $sso = $this->createMock(GenericProvider::class);
        $sso->method('getAccessToken')->willReturn(new AccessToken(['access_token' => 'tk']));

        $ro = $this->createMock(ResourceOwnerInterface::class);
        $ro->method('toArray')->willReturn(['CharacterID' => 3, 'CharacterName' => 'N3', 'CharacterOwnerHash' => 'hs']);
        $sso->method('getResourceOwner')->willReturn($ro);

        $response = $this->runApp('GET', '/api/user/auth/callback?state='.$state, null, null, [
            GenericProvider::class => $sso
        ]);
        $this->assertSame(302, $response->getStatusCode());

        $sess = new SessionData();
        $this->assertSame(
            ['success' => true, 'message' => 'Character added to player account.'],
            $sess->get('auth_result')
        );
    }

    public function testGetResult()
    {
        $response = $this->runApp('GET', '/api/user/auth/result');
        $this->assertSame(200, $response->getStatusCode());

        $this->assertSame(
            ['success' => false, 'message' => 'No login attempt recorded.'],
            $this->parseJsonBody($response)
        );
    }

    public function testGetCharacter()
    {
        $h = new Helper();
        $h->emptyDb();
        $h->addCharacterMain('User1', 654, [Roles::USER], ['group1']);
        $this->loginUser(654);

        $response = $this->runApp('GET', '/api/user/auth/character');
        $this->assertSame(200, $response->getStatusCode());

        $this->assertSame(
            ['id' => 654, 'name' => 'User1', 'main' => true],
            $this->parseJsonBody($response)
        );
    }

    public function testGetCharacter403()
    {
        $response = $this->runApp('GET', '/api/user/auth/character');
        $this->assertSame(403, $response->getStatusCode());
    }

    public function testPlayer403()
    {
        $response = $this->runApp('GET', '/api/user/auth/player');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testPlayer200()
    {
        $h = new Helper();
        $h->emptyDb();
        $groups = $h->addGroups(['group1', 'another-group']);
        $char = $h->addCharacterMain('TUser', 123456, [Roles::USER, Roles::USER_ADMIN], ['group1', 'another-group']);
        $this->loginUser(123456);

        $response = $this->runApp('GET', '/api/user/auth/player');
        $this->assertEquals(200, $response->getStatusCode());

        $this->assertSame([
            'id' => $char->getPlayer()->getId(),
            'name' => 'TUser',
            'roles' => [Roles::USER, Roles::USER_ADMIN],
            'groups' => [
                ['id' => $groups[1]->getId(), 'name' => 'another-group'],
                ['id' => $groups[0]->getId(), 'name' => 'group1']
            ],
            'characters' => [
                ['id' => 123456, 'name' => 'TUser', 'main' => true],
            ],
            'managerGroups' => [],
            'managerApps' => [],
        ], $this->parseJsonBody($response));
    }

    public function testGetLogout403()
    {
        $response = $this->runApp('GET', '/api/user/auth/logout');
        $this->assertSame(403, $response->getStatusCode());
    }

    public function testGetLogout204()
    {
        $h = new Helper();
        $h->emptyDb();
        $h->addCharacterMain('Test User', 123456, [Roles::USER]);
        $this->loginUser(123456);

        $response = $this->runApp('GET', '/api/user/auth/logout');
        $this->assertSame(204, $response->getStatusCode());
    }
}
