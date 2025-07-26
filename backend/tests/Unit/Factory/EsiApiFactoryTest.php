<?php

declare(strict_types=1);

namespace Tests\Unit\Factory;

use Neucore\Factory\EsiApiFactory;
use Neucore\Service\Config;
use PHPUnit\Framework\TestCase;
use Swagger\Client\Eve\Api\AllianceApi;
use Swagger\Client\Eve\Api\CharacterApi;
use Swagger\Client\Eve\Api\MailApi;
use Swagger\Client\Eve\Api\UniverseApi;
use Tests\Client;
use Tests\HttpClientFactory;
use Tkhamez\Eve\API\Api\CorporationApi;

class EsiApiFactoryTest extends TestCase
{
    private Config $config;

    protected function setUp(): void
    {
        $this->config = new Config(['eve' => [
            'esi_host' => '',
            'esi_compatibility_date' => '',
        ]]);
    }

    public function testGetAllianceApi(): void
    {
        $factory = new EsiApiFactory(new HttpClientFactory(new Client()), $this->config);
        $api = $factory->getAllianceApi();
        /** @noinspection PhpConditionAlreadyCheckedInspection */
        $this->assertInstanceOf(AllianceApi::class, $api);
    }

    public function testGetCorporationApi(): void
    {
        $factory = new EsiApiFactory(new HttpClientFactory(new Client()), $this->config);
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
        $factory = new EsiApiFactory(new HttpClientFactory(new Client()), $this->config);
        $api1 = $factory->getCharacterApi();
        $api2 = $factory->getCharacterApi('access-token');

        /** @noinspection PhpConditionAlreadyCheckedInspection */
        $this->assertInstanceOf(CharacterApi::class, $api1);
        $this->assertNotSame($api1, $api2);
        $this->assertNotSame($api1->getConfig(), $api2->getConfig());
    }

    public function testGetMailApi(): void
    {
        $factory = new EsiApiFactory(new HttpClientFactory(new Client()), $this->config);
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
        $factory = new EsiApiFactory(new HttpClientFactory(new Client()), $this->config);
        $api = $factory->getUniverseApi();
        /** @noinspection PhpConditionAlreadyCheckedInspection */
        $this->assertInstanceOf(UniverseApi::class, $api);
    }
}
