<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use GuzzleHttp\Psr7\Response;
use League\OAuth2\Client\Token\AccessTokenInterface;
use Monolog\Logger;
use Neucore\Entity\SystemVariable;
use Neucore\Exception\Exception;
use Neucore\Factory\RepositoryFactory;
use Neucore\Service\EveMailToken;
use Neucore\Service\ObjectManager;
use PHPUnit\Framework\TestCase;
use Tests\Client;
use Tests\Helper;

class EveMailTokenTest extends TestCase
{
    private EveMailToken $eveMailToken;

    private \Doctrine\Persistence\ObjectManager $om;

    private RepositoryFactory $repoFactory;

    private Client $client;

    protected function setUp(): void
    {
        $helper = new Helper();
        $helper->emptyDb();

        $this->om = $helper->getObjectManager();
        $this->repoFactory = new RepositoryFactory($this->om);
        $this->client = new Client();

        $logger = new Logger('test');
        $objManager = new ObjectManager($this->om, $logger);

        $this->eveMailToken = new EveMailToken(
            $this->repoFactory,
            $objManager,
            Helper::getAuthenticationProvider($this->client),
            $logger,
        );
    }

    public function testDeleteToken(): void
    {
        $varToken = new SystemVariable(SystemVariable::MAIL_TOKEN);
        $varToken->setValue((string) \json_encode([
            'id' => 123,
            'access' => 'access-token',
            'refresh' => 'refresh-token',
            'expires' => 1542546430,
        ]));
        $this->om->persist($varToken);
        $this->om->flush();

        $this->eveMailToken->deleteToken();
        $this->om->clear();

        $tokenActual = $this->repoFactory->getSystemVariableRepository()
            ->find(SystemVariable::MAIL_TOKEN);
        self::assertSame('', $tokenActual?->getValue());
    }

    /**
     * @throws Exception
     */
    public function testGetStoredToken(): void
    {
        $varToken = new SystemVariable(SystemVariable::MAIL_TOKEN);
        $varToken->setValue((string) \json_encode([
            'id' => 123,
            'access' => 'access-token',
            'refresh' => 'refresh-token',
            'expires' => 1542546430,
        ]));
        $this->om->persist($varToken);
        $this->om->flush();

        self::assertSame(
            [
                'id' => 123,
                'access' => 'access-token',
                'refresh' => 'refresh-token',
                'expires' => 1542546430,
            ],
            $this->eveMailToken->getStoredToken()
        );
    }

    /**
     * @throws Exception
     */
    public function testGetValidToken(): void
    {
        $this->client->setResponse(
            new Response( // for getAccessToken() (refresh)
                200,
                [],
                '{"access_token": "new-token",
                "refresh_token": "",
                "expires": 1519933900}', // 03/01/2018 @ 7:51pm (UTC)
            ),
            new Response(200, [], '373515628'), // for postCharactersCharacterIdMail()
        );

        $storedToken = [
            'id' => 123,
            'access' => 'access-token',
            'refresh' => 'refresh-token',
            'expires' => 1542546430,
        ];

        self::assertInstanceOf(
            AccessTokenInterface::class,
            $this->eveMailToken->getValidToken($storedToken)
        );
    }
}
