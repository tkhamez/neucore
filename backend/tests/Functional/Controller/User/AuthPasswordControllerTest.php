<?php

declare(strict_types=1);

namespace Tests\Functional\Controller\User;

use Neucore\Controller\User\AuthController;
use Neucore\Entity\Role;
use Neucore\Factory\RepositoryFactory;
use Neucore\Middleware\Psr15\CSRFToken;
use Neucore\Repository\PlayerRepository;
use Tests\Functional\WebTestCase;
use Tests\Helper;

class AuthPasswordControllerTest extends WebTestCase
{
    private Helper $helper;

    private PlayerRepository $playerRepo;

    protected function setUp(): void
    {
        $_SESSION = null;
        $this->helper = new Helper();

        $this->helper->emptyDb();
        $repositoryFactory = new RepositoryFactory($this->helper->getEm());
        $this->playerRepo = $repositoryFactory->getPlayerRepository();

        $this->helper->addRoles([Role::USER]);
    }

    public function testGeneratePassword403()
    {
        $response = $this->runApp('POST', '/api/user/auth/password-generate');
        $this->assertSame(403, $response->getStatusCode());
    }

    public function testGeneratePassword200()
    {
        $player = $this->helper->addCharacterMain('User 8', 8, [Role::USER])->getPlayer();
        $this->loginUser(8);
        $this->helper->getEm()->clear();

        $response = $this->runApp('POST', '/api/user/auth/password-generate');
        $body = $this->parseJsonBody($response);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertIsString($body);
        $this->assertGreaterThan(0, strlen($body));

        $player = $this->playerRepo->find($player->getId());
        $this->assertTrue(password_verify($body, $player->getPassword()));
    }

    public function testLogin400()
    {
        $response = $this->runApp(
            'POST',
            '/api/user/auth/password-login',
            ['playerId' => 8, 'password' => '123456'],
            ['Content-Type' => 'application/x-www-form-urlencoded']
        );

        $this->assertSame(400, $response->getStatusCode());
    }

    public function testLogin401()
    {
        $player = $this->helper->addCharacterMain('User 8', 8)->getPlayer();

        $response = $this->runApp(
            'POST',
            '/api/user/auth/password-login',
            ['playerId' => $player->getId(), 'password' => '123456'],
            ['Content-Type' => 'application/x-www-form-urlencoded']
        );

        $this->assertSame(401, $response->getStatusCode());
    }

    public function testLogin204()
    {
        $player = $this->helper->addCharacterMain('User 8', 8)->getPlayer();
        $player->setPassword(password_hash('123456', PASSWORD_BCRYPT));
        $this->helper->getEm()->flush();

        $csrfResponse1 = $this->runApp('GET', '/api/user/auth/csrf-token');
        $token1 = $this->parseJsonBody($csrfResponse1);
        $this->assertSame(39, strlen($token1));

        $response = $this->runApp(
            'POST',
            '/api/user/auth/password-login',
            ['playerId' => $player->getId(), 'password' => '123456'],
            [
                'Content-Type' => 'application/x-www-form-urlencoded',
                CSRFToken::CSRF_HEADER_NAME => $token1,
            ]
        );
        $this->assertSame(204, $response->getStatusCode());
        $this->assertSame('1', $response->getHeaderLine(AuthController::HEADER_LOGIN));

        $csrfResponse2 = $this->runApp('GET', '/api/user/auth/csrf-token');
        $token2 = $this->parseJsonBody($csrfResponse2);
        $this->assertSame(39, strlen($token2));
        $this->assertNotEquals($token1, $token2);
    }
}
