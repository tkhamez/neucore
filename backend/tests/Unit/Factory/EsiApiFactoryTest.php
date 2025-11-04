<?php

declare(strict_types=1);

namespace Tests\Unit\Factory;

use Neucore\Factory\EsiApiFactory;
use Neucore\Factory\RepositoryFactory;
use Neucore\Service\Config;
use Neucore\Service\EveMailToken;
use Neucore\Service\ObjectManager;
use PHPUnit\Framework\TestCase;
use Tests\Client;
use Tests\Helper;
use Tests\HttpClientFactory;
use Tests\Logger;
use Tkhamez\Eve\API\Api\AllianceApi;
use Tkhamez\Eve\API\Api\CharacterApi;
use Tkhamez\Eve\API\Api\CorporationApi;
use Tkhamez\Eve\API\Api\MailApi;
use Tkhamez\Eve\API\Api\UniverseApi;

class EsiApiFactoryTest extends TestCase
{
    private Config $config;

    private EveMailToken $eveMailToken;

    protected function setUp(): void
    {
        $this->config = Helper::getConfig();

        $helper = new Helper();
        $logger = new Logger();
        $this->eveMailToken = new EveMailToken(
            new RepositoryFactory($helper->getObjectManager()),
            new ObjectManager($helper->getObjectManager(), $logger),
            Helper::getAuthenticationProvider(new Client()),
            $logger,
        );
    }

    public function testGetAllianceApi(): void
    {
        $factory = new EsiApiFactory(
            new HttpClientFactory(new Client()), $this->config, $this->eveMailToken
        );
        $api = $factory->getAllianceApi();
        /** @noinspection PhpConditionAlreadyCheckedInspection */
        $this->assertInstanceOf(AllianceApi::class, $api);
    }

    public function testGetCorporationApi(): void
    {
        $factory = new EsiApiFactory(
            new HttpClientFactory(new Client()), $this->config, $this->eveMailToken
        );
        $api1 = $factory->getCorporationApi();
        $api2 = $factory->getCorporationApi();
        $api3 = $factory->getCorporationApi('access-token');

        /** @noinspection PhpConditionAlreadyCheckedInspection */
        $this->assertInstanceOf(CorporationApi::class, $api1);
        $this->assertSame($api1, $api2);
        $this->assertNotSame($api1, $api3);
        $this->assertSame($api1->getConfig(), $api2->getConfig());
        $this->assertNotSame($api1->getConfig(), $api3->getConfig());
    }

    public function testGetCharacterApi(): void
    {
        $factory = new EsiApiFactory(
            new HttpClientFactory(new Client()), $this->config, $this->eveMailToken
        );
        $api1 = $factory->getCharacterApi();
        $api2 = $factory->getCharacterApi('access-token');

        /** @noinspection PhpConditionAlreadyCheckedInspection */
        $this->assertInstanceOf(CharacterApi::class, $api1);
        $this->assertNotSame($api1, $api2);
        $this->assertNotSame($api1->getConfig(), $api2->getConfig());
    }

    public function testGetMailApi(): void
    {
        $factory = new EsiApiFactory(
            new HttpClientFactory(new Client()), $this->config, $this->eveMailToken
        );
        $api1 = $factory->getMailApi('token');
        $api2 = $factory->getMailApi('token');
        $api3 = $factory->getMailApi('token2');

        /** @noinspection PhpConditionAlreadyCheckedInspection */
        $this->assertInstanceOf(MailApi::class, $api1);
        /** @noinspection PhpConditionAlreadyCheckedInspection */
        $this->assertInstanceOf(MailApi::class, $api2);
        /** @noinspection PhpConditionAlreadyCheckedInspection */
        $this->assertInstanceOf(MailApi::class, $api3);

        $this->assertSame($api1, $api2);
        $this->assertNotSame($api1, $api3);

        $this->assertSame($api1->getConfig(), $api2->getConfig());
        $this->assertNotSame($api1->getConfig(), $api3->getConfig());
    }

    public function testGetUniverseApi(): void
    {
        $factory = new EsiApiFactory(
            new HttpClientFactory(new Client()), $this->config, $this->eveMailToken
        );
        $api = $factory->getUniverseApi();
        /** @noinspection PhpConditionAlreadyCheckedInspection */
        $this->assertInstanceOf(UniverseApi::class, $api);
    }
}
