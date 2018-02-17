<?php
namespace Tests\Functional\CoreApiUser;

use Tests\Functional\BaseTestCase;
use Brave\Slim\Session\SessionData;
use Tests\Helper;

class AuthTest extends BaseTestCase
{

    public function setUp()
    {
        $_SESSION = null;
    }

    public function testGetLogin()
    {
        $redirect = '/index.html#auth';
        $response = $this->runApp('GET', '/api/user/auth/login?redirect_url='.urlencode($redirect));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['application/json;charset=utf-8'], $response->getHeader('Content-Type'));

        $body = $this->parseJsonBody($response);

        $this->assertSame(1, count($body));
        $this->assertContains('https://login.eveonline.com', $body['oauth_url']);

        $sess = new SessionData();
        $this->assertSame($redirect, $sess->get('auth_redirect_url'));
        $this->assertSame(32, strlen($sess->get('auth_state')));
    }

    public function testGetCallback()
    {
        $state = 'd2c55ec4cfefe6224a500f4127bcee31';
        $_SESSION = ['auth_state' => $state];

        $response = $this->runApp('GET', '/api/user/auth/callback?state='.$state);
        $this->assertSame(302, $response->getStatusCode());

        $sess = new SessionData();
        $this->assertSame(null, $sess->get('auth_state')); // test that it was deleted
        $this->assertSame(['success' => false, 'message' => 'request token error'], $sess->get('auth_result'));
    }

    public function testGetResult()
    {
        $response = $this->runApp('GET', '/api/user/auth/result');
        $this->assertSame(200, $response->getStatusCode());

        $this->assertNull($this->parseJsonBody($response));
    }

    public function testGetLogout401()
    {
        $response = $this->runApp('GET', '/api/user/auth/logout');
        $this->assertSame(401, $response->getStatusCode());
    }

    public function testGetLogout200()
    {
        $h = new Helper();
        $h->emptyDb();
        $uid = $h->addStandardUser();
        $this->loginUser($uid);

        $response = $this->runApp('GET', '/api/user/auth/logout');
        $this->assertSame(200, $response->getStatusCode());
    }
}
