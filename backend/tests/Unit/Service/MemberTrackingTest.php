<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use Eve\Sso\EveAuthentication;
use GuzzleHttp\Psr7\Response;
use League\OAuth2\Client\Token\AccessToken;
use League\OAuth2\Client\Token\AccessTokenInterface;
use Neucore\Data\DirectorToken;
use Neucore\Entity\Character;
use Neucore\Entity\Corporation;
use Neucore\Entity\CorporationMember;
use Neucore\Entity\EsiLocation;
use Neucore\Entity\EsiType;
use Neucore\Entity\SystemVariable;
use Neucore\Factory\EsiApiFactory;
use Neucore\Factory\RepositoryFactory;
use Neucore\Service\Config;
use Neucore\Service\EntityManager;
use Neucore\Service\EsiData;
use Neucore\Service\MemberTracking;
use Neucore\Service\OAuthToken;
use Neucore\Service\ObjectManager;
use PHPUnit\Framework\TestCase;
use Swagger\Client\Eve\Model\GetCorporationsCorporationIdMembertracking200Ok;
use Tests\Client;
use Tests\Helper;
use Tests\Logger;

class MemberTrackingTest extends TestCase
{
    /**
     * @var Helper
     */
    private $helper;

    /**
     * @var \Doctrine\Persistence\ObjectManager
     */
    private $om;

    /**
     * @var Client
     */
    private $client;

    /**
     * @var RepositoryFactory
     */
    private $repositoryFactory;

    /**
     * @var MemberTracking
     */
    private $memberTracking;

    protected function setUp(): void
    {
        $this->helper = new Helper();
        $this->helper->emptyDb();
        $this->om = $this->helper->getObjectManager();
        $logger = new Logger('test');
        $this->client = new Client();
        $objectManager = new ObjectManager($this->om, $logger);
        $this->repositoryFactory = new RepositoryFactory($this->om);
        $config = new Config(['eve' => ['datasource' => '', 'esi_host' => '']]);
        $esiApiFactory = new EsiApiFactory($this->client, $config);
        $authProvider = $this->helper->getAuthenticationProvider($this->client);
        $this->memberTracking = new MemberTracking(
            $logger,
            $esiApiFactory,
            $this->repositoryFactory,
            new EntityManager($this->helper->getEm(), $logger),
            new EsiData(
                $logger,
                $esiApiFactory,
                $objectManager,
                $this->repositoryFactory,
                new \Neucore\Service\Character($objectManager, $this->repositoryFactory),
                $config
            ),
            new OAuthToken($authProvider, $objectManager, $logger),
            $authProvider,
            $config
        );
    }

    public function testFetchCharacterAndStoreDirector_CharError()
    {
        $this->client->setResponse(new Response(404));// getCharactersCharacterId

        $eveAuth = new EveAuthentication(100, 'cname', 'coh', new AccessToken(['access_token' => 'at']));
        $this->assertFalse($this->memberTracking->fetchCharacterAndStoreDirector($eveAuth));
    }

    public function testFetchCharacterAndStoreDirector_CorpError()
    {
        $this->client->setResponse(
            new Response(200, [], '{"corporation_id": 10}'), // getCharactersCharacterId
            new Response(404) // getCorporation
        );

        $eveAuth = new EveAuthentication(100, 'cname', 'coh', new AccessToken(['access_token' => 'at']));
        $this->assertFalse($this->memberTracking->fetchCharacterAndStoreDirector($eveAuth));
    }

    public function testFetchCharacterAndStoreDirector_Success()
    {
        $char = new SystemVariable(SystemVariable::DIRECTOR_CHAR . 1);
        $token = new SystemVariable(SystemVariable::DIRECTOR_TOKEN . 1);
        $this->om->persist($char);
        $this->om->persist($token);
        $this->om->flush();
        $this->client->setResponse(
            new Response(200, [], '{"corporation_id": 10}'), // getCharactersCharacterId
            new Response(200, [], '{"name": "ten", "ticker": "-10-"}') // getCorporation
        );

        $eveAuth = new EveAuthentication(100, 'cname', 'coh', new AccessToken(['access_token' => 'at']), ['s1']);
        $result = $this->memberTracking->fetchCharacterAndStoreDirector($eveAuth);

        $this->assertTrue($result);
        $this->assertSame('ten', $this->repositoryFactory->getCorporationRepository()->find(10)->getName());
        $sysVarRepo = $this->repositoryFactory->getSystemVariableRepository();
        $this->assertSame([
            'character_id' => 100,
            'character_name' => 'cname',
            'corporation_id' => 10,
            'corporation_name' => 'ten',
            'corporation_ticker' => '-10-',
        ], \json_decode($sysVarRepo->find(SystemVariable::DIRECTOR_CHAR . 2)->getValue(), true));
        $sysVarRepo = $this->repositoryFactory->getSystemVariableRepository();
        $this->assertSame([
            'access' => 'at',
            'refresh' => null,
            'expires' => null,
            'scopes' => ['s1'],
            'characterId' => null,
            'systemVariableName' => null,
        ], \json_decode($sysVarRepo->find(SystemVariable::DIRECTOR_TOKEN . 2)->getValue(), true));
    }

    public function testFetchCharacterAndStoreDirector_UpdateExistingDirector()
    {
        $char = (new SystemVariable(SystemVariable::DIRECTOR_CHAR . 1))->setValue((string) \json_encode([
            'character_id' => 100,
            'character_name' => 'cname',
            'corporation_id' => 10,
            'corporation_name' => 'ten',
            'corporation_ticker' => '-10-',
        ]));
        $token = new SystemVariable(SystemVariable::DIRECTOR_TOKEN . 1);
        $this->om->persist($char);
        $this->om->persist($token);
        $this->om->flush();
        $this->client->setResponse(
            new Response(200, [], '{"corporation_id": 11}'), // getCharactersCharacterId
            new Response(200, [], '{"name": "not ten", "ticker": "-11-"}') // getCorporation
        );

        $eveAuth = new EveAuthentication(100, 'cname', 'coh', new AccessToken(['access_token' => 'at']), ['s1', 's2']);
        $result = $this->memberTracking->fetchCharacterAndStoreDirector($eveAuth);

        $this->assertTrue($result);
        $sysVarRepo = $this->repositoryFactory->getSystemVariableRepository();
        $this->assertSame([
            'character_id' => 100,
            'character_name' => 'cname',
            'corporation_id' => 11,
            'corporation_name' => 'not ten',
            'corporation_ticker' => '-11-',
        ], \json_decode($sysVarRepo->find(SystemVariable::DIRECTOR_CHAR . 1)->getValue(), true));
        $sysVarRepo = $this->repositoryFactory->getSystemVariableRepository();
        $this->assertSame([
            'access' => 'at',
            'refresh' => null,
            'expires' => null,
            'scopes' => ['s1', 's2'],
            'characterId' => null,
            'systemVariableName' => null,
        ], \json_decode($sysVarRepo->find(SystemVariable::DIRECTOR_TOKEN . 1)->getValue(), true));
    }

    public function testRemoveDirector()
    {
        $char = new SystemVariable(SystemVariable::DIRECTOR_CHAR . 1);
        $token = new SystemVariable(SystemVariable::DIRECTOR_TOKEN . 1);
        $this->om->persist($char);
        $this->om->persist($token);
        $this->om->flush();

        $this->memberTracking->removeDirector($char);

        $sysVarRepo = $this->repositoryFactory->getSystemVariableRepository();
        $this->assertNull($sysVarRepo->find(SystemVariable::DIRECTOR_CHAR . 1));
        $this->assertNull($sysVarRepo->find(SystemVariable::DIRECTOR_TOKEN . 1));
    }

    public function testUpdateDirector()
    {
        $char = (new SystemVariable(SystemVariable::DIRECTOR_CHAR . 1))->setValue('{
            "character_id": 10, 
            "character_name": "char name", 
            "corporation_id": 101,
            "corporation_name": "corp name",
            "corporation_ticker": "-CT-"
        }');
        $this->om->persist($char);
        $this->om->flush();

        $this->client->setResponse(
            new Response(200, [], '{"name": "name char", "corporation_id": 102}'), // getCharactersCharacterId()
            new Response(200, [], '{"name": "name corp", "ticker": "-TC-"}') // getCorporationsCorporationId()
        );

        $this->assertTrue($this->memberTracking->updateDirector(SystemVariable::DIRECTOR_CHAR . 1));

        $charDb = $this->repositoryFactory->getSystemVariableRepository()->find(SystemVariable::DIRECTOR_CHAR . 1);
        $data = \json_decode($charDb->getValue(), true);
        $this->assertSame([
            'character_id' => 10,
            'character_name' => 'name char',
            'corporation_id' => 102,
            'corporation_name' => 'name corp',
            'corporation_ticker' => '-TC-',
        ], $data);
    }

    public function testGetDirectorTokenVariable()
    {
        $this->assertNull($this->memberTracking->getDirectorTokenVariableData(SystemVariable::DIRECTOR_CHAR . 1));

        $char = (new SystemVariable(SystemVariable::DIRECTOR_CHAR . 1))->setValue('{"character_id": 100}');
        $token = (new SystemVariable(SystemVariable::DIRECTOR_TOKEN . 1))
            ->setValue('{"access": "at", "refresh": "rt", "expires": 1568471332}');
        $this->om->persist($char);
        $this->om->persist($token);
        $this->om->flush();

        $tokenData = $this->memberTracking->getDirectorTokenVariableData(SystemVariable::DIRECTOR_CHAR . 1);

        $this->assertSame('at', $tokenData->access);
        $this->assertSame('rt', $tokenData->refresh);
        $this->assertSame(1568471332, $tokenData->expires);
        $this->assertSame(100, $tokenData->characterId);
    }

    public function testRefreshDirectorToken_IdentityProviderException()
    {
        $token = new DirectorToken();
        $token->access = 'at';
        $token->refresh = 'rt';
        $token->expires = 1568471332;
        $token->characterId = 100;
        $token->systemVariableName = SystemVariable::DIRECTOR_TOKEN . 1;

        $var = (new SystemVariable($token->systemVariableName))->setValue(json_encode($token));
        $this->om->persist($var);
        $this->om->flush();

        $this->client->setResponse(new Response(400, [], '{ "error": "invalid_grant" }'));

        $this->assertNull($this->memberTracking->refreshDirectorToken($token));

        $this->om->clear();
        $varLoaded = $this->repositoryFactory->getSystemVariableRepository()->find($token->systemVariableName);
        $varData = json_decode($varLoaded->getValue());

        $this->assertSame(null, $varData->access);
        $this->assertSame(null, $varData->refresh);
        $this->assertSame(null, $varData->expires);
    }

    public function testRefreshDirectorToken_Success()
    {
        $token = new DirectorToken();
        $token->access = 'at';
        $token->refresh = 'rt';
        $token->expires = time() - 300;
        $token->characterId = 100;
        $token->systemVariableName = SystemVariable::DIRECTOR_TOKEN . 1;

        $var = (new SystemVariable($token->systemVariableName))->setValue(json_encode($token));
        $this->om->persist($var);
        $this->om->flush();

        $newTokenTime = time() + 300;
        $this->client->setResponse(new Response(200, [], '{
            "access_token": "new_token", 
            "refresh_token": "rt2", 
            "expires": '.$newTokenTime.'
        }'));

        $result = $this->memberTracking->refreshDirectorToken($token);

        $this->assertInstanceOf(AccessTokenInterface::class, $result);

        $this->om->clear();
        $varLoaded = $this->repositoryFactory->getSystemVariableRepository()->find($token->systemVariableName);
        $varData = json_decode($varLoaded->getValue());

        $this->assertSame('new_token', $varData->access);
        $this->assertSame('rt2', $varData->refresh);
        $this->assertSame($newTokenTime, $varData->expires);
    }

    public function testFetchDataCorpNotFound()
    {
        $this->client->setResponse(new Response(404, [], ''));
        $this->assertNull($this->memberTracking->fetchData('access-token', 10));
    }

    public function testFetchDataOK()
    {
        $this->client->setResponse(new Response(200, [], '[{"character_id": 100}, {"character_id": 101}]'));

        $actual = (array)$this->memberTracking->fetchData('access-token', 10);

        $this->assertSame(2, count($actual));
        $this->assertSame(100, $actual[0]->getCharacterId());
        $this->assertSame(101, $actual[1]->getCharacterId());
    }

    public function testUpdateNames()
    {
        $this->client->setResponse(
            new Response(200, [], '[
                {"category": "station", "id": 60008494, "name": "Amarr VIII (Oris) - Emperor Family Academy"},
                {"category": "solar_system", "id": 30000142, "name": "Jita"},
                {"category": "inventory_type", "id": 670, "name": "Capsule"}
            ]') // postUniverseNames for types, system, stations
        );

        $this->memberTracking->updateNames([670], [30000142], [60008494]);

        $resultTypes = $this->repositoryFactory->getEsiTypeRepository()->findBy([]);
        $this->assertSame(1, count($resultTypes));
        $this->assertSame(670, $resultTypes[0]->getId());
        $this->assertSame('Capsule', $resultTypes[0]->getName());

        $resultLocations = $this->repositoryFactory->getEsiLocationRepository()->findBy([]);
        $this->assertSame(2, count($resultLocations));
        $this->assertSame(30000142, $resultLocations[0]->getId());
        $this->assertSame(EsiLocation::CATEGORY_SYSTEM, $resultLocations[0]->getCategory());
        $this->assertSame('Jita', $resultLocations[0]->getName());
        $this->assertLessThanOrEqual(time(), $resultLocations[0]->getLastUpdate()->getTimestamp());
        $this->assertSame(60008494, $resultLocations[1]->getId());
        $this->assertSame(EsiLocation::CATEGORY_STATION, $resultLocations[1]->getCategory());
        $this->assertSame('Amarr VIII (Oris) - Emperor Family Academy', $resultLocations[1]->getName());
        $this->assertLessThanOrEqual(time(), $resultLocations[1]->getLastUpdate()->getTimestamp());
    }

    public function testUpdateStructures()
    {
        $data =  new GetCorporationsCorporationIdMembertracking200Ok([
            'character_id' => 102,
            'location_id' => 1023100200300,
        ]);

        $this->client->setResponse(
            new Response(200, [], '{
                "name": "the structure name",
                "owner_id": 123,
                "solar_system_id": 456
            }') // structure
        );

        $tokenData = new DirectorToken();
        $tokenData->access = 'at';
        $tokenData->refresh = 'rf';
        $tokenData->expires = time() + 60;
        $tokenData->characterId = 1;
        $this->memberTracking->updateStructure($data, $tokenData);
        $this->om->flush();

        $resultLocations = $this->repositoryFactory->getEsiLocationRepository()->findBy([]);
        $this->assertSame(1, count($resultLocations));
        $this->assertSame(1023100200300, $resultLocations[0]->getId());
        $this->assertSame(EsiLocation::CATEGORY_STRUCTURE, $resultLocations[0]->getCategory());
        $this->assertSame('the structure name', $resultLocations[0]->getName());
        $this->assertSame(123, $resultLocations[0]->getOwnerId());
        $this->assertSame(456, $resultLocations[0]->getSystemId());
        $this->assertLessThanOrEqual(time(), $resultLocations[0]->getLastUpdate()->getTimestamp());
    }

    public function testFetchCharacterNames()
    {
        $this->client->setResponse(
            new Response(200, [], '[
                {"category": "character", "id": "101", "name": "char 1"},
                {"category": "character", "id": "102", "name": "char 2"},
                {"category": "character", "id": "103", "name": "char 3"}
            ]') // postUniverseNames for char names
        );

        $names = $this->memberTracking->fetchCharacterNames([101, 102, 103]);

        $this->assertSame([101 => 'char 1', 102 => 'char 2', 103 => 'char 3'], $names);
    }

    public function testStoreMemberData()
    {
        $corp = (new Corporation())->setId(10)->setName('corp')->setTicker('C');
        $char = (new Character())->setId(102)->setName('char 2');
        $member = (new CorporationMember())->setId(102)->setName('char 2')->setCorporation($corp)
            ->setMissingCharacterMailSentNumber(1);
        $type = (new EsiType())->setId(670);
        $location1 = (new EsiLocation())->setId(60008494)->setCategory(EsiLocation::CATEGORY_STATION);
        $location2 = (new EsiLocation())->setId(1023100200300)->setCategory(EsiLocation::CATEGORY_STRUCTURE);
        $location3 = (new EsiLocation())->setId(30000142)->setCategory(EsiLocation::CATEGORY_STATION);
        $this->om->persist($corp);
        $this->om->persist($member);
        $this->om->persist($type);
        $this->om->persist($location1);
        $this->om->persist($location2);
        $this->om->persist($location3);
        $this->helper->addNewPlayerToCharacterAndFlush($char);

        $data = [
            new GetCorporationsCorporationIdMembertracking200Ok([
                'character_id' => 101,
                'location_id' => 60008494,
                'logoff_date' => new \DateTime('2018-12-25 19:45:10'),
                'logon_date' => new \DateTime('2018-12-25 19:45:11'),
                'ship_type_id' => 670,
                'start_date' => new \DateTime('2018-12-25 19:45:12'),
            ]),
            new GetCorporationsCorporationIdMembertracking200Ok([
                'character_id' => 102,
                'location_id' => 1023100200300,
            ]),
            new GetCorporationsCorporationIdMembertracking200Ok([
                'character_id' => 103,
                'location_id' => 30000142,
            ]),
        ];
        $names = [101 => 'char 1', 102 => 'char 2', 103 => 'char 3'];

        $this->memberTracking->storeMemberData($corp->getId(), $data, $names);

        $this->om->clear();
        $result = $this->repositoryFactory->getCorporationMemberRepository()->findBy([], ['id' => 'ASC']);
        $this->assertSame(3, count($result));

        $this->assertSame(101, $result[0]->getId());
        $this->assertSame('char 1', $result[0]->getName());
        $this->assertSame(60008494, $result[0]->getLocation()->getId());
        $this->assertSame('2018-12-25T19:45:10+00:00', $result[0]->getLogoffDate()->format(\DATE_ATOM));
        $this->assertSame('2018-12-25T19:45:11+00:00', $result[0]->getLogonDate()->format(\DATE_ATOM));
        $this->assertSame(670, $result[0]->getShipType()->getId());
        $this->assertSame('2018-12-25T19:45:12+00:00', $result[0]->getStartDate()->format(\DATE_ATOM));

        $this->assertSame(102, $result[1]->getId());
        $this->assertSame('char 2', $result[1]->getName());
        $this->assertSame(0, $result[1]->getMissingCharacterMailSentNumber());
        $this->assertNull($result[1]->getCharacter()); // this character exists but is no longer mapped with Doctrine
        $this->assertSame(1023100200300, $result[1]->getLocation()->getId());

        $this->assertSame(103, $result[2]->getId());
        $this->assertSame('char 3', $result[2]->getName());
        $this->assertSame(30000142, $result[2]->getLocation()->getId());
    }
}
