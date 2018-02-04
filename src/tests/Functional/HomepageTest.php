<?php

namespace Tests\Functional;

class HomepageTest extends BaseTestCase
{
    /**
     * Test that a route exists
     */
    public function testGetUserLogin()
    {
        $response = $this->runApp('GET', '/api/user/info');
        echo $response->getBody();
        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testLoginRedirect()
    {
        $response = $this->runApp('GET', '/api/user/auth/login');
        echo $response->getBody();
        $this->assertEquals(200, $response->getStatusCode());
    }


    /**
     * Test that a route won't accept a post request
     */
    public function testPostAPINotAllowed()
    {
        $response = $this->runApp('POST', '/api/user/auth/login');

        $this->assertEquals(405, $response->getStatusCode());
        $this->assertContains('Method not allowed', (string)$response->getBody());
    }
}
