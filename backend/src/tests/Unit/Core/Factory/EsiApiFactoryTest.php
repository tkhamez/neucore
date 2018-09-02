<?php declare(strict_types=1);

namespace Tests\Unit\Core\Factory;

use Brave\Core\Factory\EsiApiFactory;
use GuzzleHttp\Client;
use Swagger\Client\Eve\Api\AllianceApi;
use Swagger\Client\Eve\Api\CharacterApi;
use Swagger\Client\Eve\Api\CorporationApi;

class EsiApiFactoryTest extends \PHPUnit\Framework\TestCase
{
    public function testSetClient()
    {
        $factory = new EsiApiFactory();
        $client = new Client();

        $this->assertInstanceOf(EsiApiFactory::class, $factory->setClient($client));
    }

    public function testSetToken()
    {
        $factory = new EsiApiFactory();

        $this->assertInstanceOf(EsiApiFactory::class, $factory->setToken('token'));
    }

    public function testGetAllianceApi()
    {
        $factory = new EsiApiFactory();
        $api = $factory->getAllianceApi();

        $this->assertInstanceOf(AllianceApi::class, $api);
    }

    public function testGetCorporationApi()
    {
        $factory = new EsiApiFactory();
        $api = $factory->getCorporationApi();

        $this->assertInstanceOf(CorporationApi::class, $api);
    }

    public function testGetCharacterApi()
    {
        $factory = new EsiApiFactory();
        $api = $factory->getCharacterApi();

        $this->assertInstanceOf(CharacterApi::class, $api);
    }
}
