<?php declare(strict_types=1);

namespace Tests\Unit\Factory;

use Neucore\Factory\EsiApiFactory;
use Neucore\Service\Config;
use GuzzleHttp\Client;
use Swagger\Client\Eve\Api\AllianceApi;
use Swagger\Client\Eve\Api\CharacterApi;
use Swagger\Client\Eve\Api\CorporationApi;
use Swagger\Client\Eve\Api\MailApi;
use Swagger\Client\Eve\Api\UniverseApi;

class EsiApiFactoryTest extends \PHPUnit\Framework\TestCase
{
    public function testGetAllianceApi()
    {
        $factory = new EsiApiFactory(new Client(), new Config([]));
        $api = $factory->getAllianceApi();
        $this->assertInstanceOf(AllianceApi::class, $api);
    }

    public function testGetCorporationApi()
    {
        $factory = new EsiApiFactory(new Client(), new Config([]));
        $api1 = $factory->getCorporationApi();
        $api2 = $factory->getCorporationApi();
        $api3 = $factory->getCorporationApi('access-token');

        $this->assertInstanceOf(CorporationApi::class, $api1);
        $this->assertSame($api1, $api2);
        $this->assertNotSame($api1, $api3);
        $this->assertSame($api1->getConfig(), $api2->getConfig());
        $this->assertNotSame($api1->getConfig(), $api3->getConfig());
    }

    public function testGetCharacterApi()
    {
        $factory = new EsiApiFactory(new Client(), new Config([]));
        $api1 = $factory->getCharacterApi();
        $api2 = $factory->getCharacterApi('access-token');

        $this->assertInstanceOf(CharacterApi::class, $api1);
        $this->assertNotSame($api1, $api2);
        $this->assertNotSame($api1->getConfig(), $api2->getConfig());
    }

    public function testGetMailApi()
    {
        $factory = new EsiApiFactory(new Client(), new Config([]));
        $api1 = $factory->getMailApi('token');
        $api2 = $factory->getMailApi('token');
        $api3 = $factory->getMailApi('token2');

        $this->assertInstanceOf(MailApi::class, $api1);
        $this->assertInstanceOf(MailApi::class, $api2);
        $this->assertInstanceOf(MailApi::class, $api3);

        $this->assertSame($api1, $api2);
        $this->assertNotSame($api1, $api3);

        $this->assertSame($api1->getConfig(), $api2->getConfig());
        $this->assertNotSame($api1->getConfig(), $api3->getConfig());
    }

    public function testGetUniverseApi()
    {
        $factory = new EsiApiFactory(new Client(), new Config([]));
        $api = $factory->getUniverseApi();
        $this->assertInstanceOf(UniverseApi::class, $api);
    }
}
