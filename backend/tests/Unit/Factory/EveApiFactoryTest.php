<?php

declare(strict_types=1);

namespace Tests\Unit\Factory;

use Neucore\Entity\SystemVariable;
use Neucore\Factory\EveApiFactory;
use Neucore\Factory\RepositoryFactory;
use Neucore\Service\EveMailToken;
use Neucore\Service\ObjectManager;
use PHPUnit\Framework\TestCase;
use Tests\Client;
use Tests\Helper;
use Tests\HttpClientFactory;
use Tests\Logger;

class EveApiFactoryTest extends TestCase
{
    private \Doctrine\Persistence\ObjectManager $om;

    private EveMailToken $eveMailToken;

    private Client $client;

    private EveApiFactory $eveApiFactory;

    protected function setUp(): void
    {
        $helper = new Helper();
        $logger = new Logger();
        $this->om = $helper->getObjectManager();
        $this->eveMailToken = new EveMailToken(
            new RepositoryFactory($this->om),
            new ObjectManager($this->om, $logger),
            Helper::getAuthenticationProvider(new Client()),
            $logger,
        );
        $this->client = new Client();
        $this->eveApiFactory = new EveApiFactory(
            new HttpClientFactory($this->client),
            Helper::getConfig('2025-09-30'),
            $this->eveMailToken,
        );
    }

    public function testGetAllianceApi(): void
    {
        $api = $this->eveApiFactory->getAllianceApi();
        self::assertSame('http://localhost', $api->getConfig()->getHost());
        self::assertSame(['X-Compatibility-Date' => '2025-09-30'], $this->client->getHeaders());
    }

    public function testGetCorporationApi(): void
    {
        $api1 = $this->eveApiFactory->getCorporationApi();
        $api2 = $this->eveApiFactory->getCorporationApi();
        $api3 = $this->eveApiFactory->getCorporationApi('access-token');

        self::assertSame($api1, $api2);
        self::assertNotSame($api1, $api3);
        self::assertSame($api1->getConfig(), $api2->getConfig());
        self::assertNotSame($api1->getConfig(), $api3->getConfig());
    }

    public function testGetCharacterApi(): void
    {
        $api1 = $this->eveApiFactory->getCharacterApi();
        $api2 = $this->eveApiFactory->getCharacterApi('access-token');

        self::assertNotSame($api1, $api2);
        self::assertNotSame($api1->getConfig(), $api2->getConfig());
    }

    public function testGetMailApi(): void
    {
        $api1 = $this->eveApiFactory->getMailApi('token');
        $api2 = $this->eveApiFactory->getMailApi('token');
        $api3 = $this->eveApiFactory->getMailApi('token2');

        self::assertSame($api1, $api2);
        self::assertNotSame($api1, $api3);

        self::assertSame($api1->getConfig(), $api2->getConfig());
        self::assertNotSame($api1->getConfig(), $api3->getConfig());
    }

    public function testGetUniverseApi_NoToken(): void
    {
        $api = $this->eveApiFactory->getUniverseApi();
        self::assertSame('', $api->getConfig()->getAccessToken());
        self::assertArrayNotHasKey('Authorization', $this->client->getHeaders());
    }

    public function testGetUniverseApi_WithToken(): void
    {
        $mailToken = (new SystemVariable(SystemVariable::MAIL_TOKEN))
            ->setValue((string) \json_encode([
                'id' => 123,
                'access' => 'access-token',
                'refresh' => 'refresh-token',
                'expires' => time() + 10000,
            ]));
        $this->om->persist($mailToken);

        $eveApiFactory = new EveApiFactory(
            new HttpClientFactory($this->client),
            Helper::getConfig('', '1'),
            $this->eveMailToken,
        );

        $api = $eveApiFactory->getUniverseApi();

        self::assertSame('', $api->getConfig()->getAccessToken());
        self::assertSame('Bearer access-token', $this->client->getHeaders()['Authorization']);
    }
}
