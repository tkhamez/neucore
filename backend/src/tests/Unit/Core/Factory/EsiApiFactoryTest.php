<?php declare(strict_types=1);

namespace Tests\Unit\Core\Factory;

use Brave\Core\Factory\EsiApiFactory;
use GuzzleHttp\Client;
use Swagger\Client\Eve\Api\AllianceApi;
use Swagger\Client\Eve\Api\CharacterApi;
use Swagger\Client\Eve\Api\CorporationApi;
use Swagger\Client\Eve\Api\MailApi;

class EsiApiFactoryTest extends \PHPUnit\Framework\TestCase
{
    public function testSetClient()
    {
        $factory = new EsiApiFactory();
        $client = new Client();
        $this->assertInstanceOf(EsiApiFactory::class, $factory->setClient($client));
    }

    public function testGetAllianceApi()
    {
        $factory = new EsiApiFactory();
        $api1 = $factory->getAllianceApi();
        $api2 = $factory->getAllianceApi();

        $this->assertInstanceOf(AllianceApi::class, $api1);
        $this->assertSame($api1, $api2);
        $this->assertSame($api1->getConfig(), $api2->getConfig());
    }

    public function testGetCorporationApi()
    {
        $factory = new EsiApiFactory();
        $api1 = $factory->getCorporationApi();
        $api2 = $factory->getCorporationApi();

        $this->assertInstanceOf(CorporationApi::class, $api1);
        $this->assertSame($api1, $api2);
        $this->assertSame($api1->getConfig(), $api2->getConfig());
    }

    public function testGetCharacterApi()
    {
        $factory = new EsiApiFactory();
        $api1 = $factory->getCharacterApi();
        $api2 = $factory->getCharacterApi();

        $this->assertInstanceOf(CharacterApi::class, $api1);
        $this->assertSame($api1, $api2);
        $this->assertSame($api1->getConfig(), $api2->getConfig());
    }

    public function testGetMailApi()
    {
        $factory = new EsiApiFactory();
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
}
