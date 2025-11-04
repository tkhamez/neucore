<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use GuzzleHttp\Psr7\Response;
use Neucore\Entity\Character;
use Neucore\Entity\Corporation;
use Neucore\Entity\CorporationMember;
use Neucore\Entity\EsiLocation;
use Neucore\Entity\EsiToken;
use Neucore\Entity\EsiType;
use Neucore\Factory\EsiApiFactory;
use Neucore\Factory\RepositoryFactory;
use Neucore\Service\EntityManager;
use Neucore\Service\EsiData;
use Neucore\Service\EveMailToken;
use Neucore\Service\MemberTracking;
use Neucore\Service\OAuthToken;
use Neucore\Service\ObjectManager;
use PHPUnit\Framework\TestCase;
use Tests\Client;
use Tests\Helper;
use Tests\HttpClientFactory;
use Tests\Logger;
use Tkhamez\Eve\API\Model\CorporationsCorporationIdMembertrackingGetInner;

class MemberTrackingTest extends TestCase
{
    private Helper $helper;

    private \Doctrine\Persistence\ObjectManager $om;

    private Client $client;

    private RepositoryFactory $repositoryFactory;

    private MemberTracking $memberTracking;

    protected function setUp(): void
    {
        $this->helper = new Helper();
        $this->helper->emptyDb();
        $this->om = $this->helper->getObjectManager();
        $logger = new Logger();
        $this->client = new Client();
        $objectManager = new ObjectManager($this->om, $logger);
        $this->repositoryFactory = new RepositoryFactory($this->om);
        $config = Helper::getConfig();
        $authProvider = Helper::getAuthenticationProvider($this->client);
        $esiApiFactory = new EsiApiFactory(
            new HttpClientFactory($this->client),
            $config,
            new EveMailToken($this->repositoryFactory, $objectManager, $authProvider, $logger)
        );
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
            ),
            new OAuthToken($authProvider, $objectManager, $logger),
        );
    }

    public function testFetchDataCorpNotFound()
    {
        $this->client->setResponse(new Response(404, [], ''));
        $this->assertNull($this->memberTracking->fetchData('access-token', 10));
    }

    public function testFetchDataOK()
    {
        $this->client->setResponse(new Response(200, [], '[{"character_id": 100}, {"character_id": 101}]'));

        $actual = (array) $this->memberTracking->fetchData('access-token', 10);

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
            ]'), // postUniverseNames for types, system, stations
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

    public function testUpdateStructure_DirectorSuccess()
    {
        $data =  new CorporationsCorporationIdMembertrackingGetInner([
            'character_id' => 102,
            'location_id' => 1023100200300,
        ]);

        $this->client->setResponse(
            new Response(200, [], '{
                "name": "the structure name",
                "owner_id": 123,
                "solar_system_id": 456
            }'), // structure
        );

        $esiToken = new EsiToken();
        $esiToken->setValidToken(true);
        $esiToken->setAccessToken('at');
        $esiToken->setRefreshToken('rf');
        $esiToken->setExpires(time() + 600);

        $this->memberTracking->updateStructure($data, $esiToken);
        $this->om->flush();

        $resultLocations = $this->repositoryFactory->getEsiLocationRepository()->findBy([]);
        $this->assertSame(1, count($resultLocations));
        $this->assertSame(1023100200300, $resultLocations[0]->getId());
        $this->assertSame(EsiLocation::CATEGORY_STRUCTURE, $resultLocations[0]->getCategory());
        $this->assertSame('the structure name', $resultLocations[0]->getName());
        $this->assertSame(123, $resultLocations[0]->getOwnerId());
        $this->assertSame(456, $resultLocations[0]->getSystemId());
        $this->assertLessThanOrEqual(time(), $resultLocations[0]->getLastUpdate()->getTimestamp());
        $this->assertSame(0, $resultLocations[0]->getErrorCount());
    }

    public function testUpdateStructure_DoubleError()
    {
        $char = $this->helper->addCharacterMain('C1', 102204, [], [], false);
        $this->helper->createOrUpdateEsiToken($char, time() + 1000, 'at', true);

        $data =  new CorporationsCorporationIdMembertrackingGetInner([
            'character_id' => 102204,
            'location_id' => 1023100200300,
        ]);

        $this->client->setResponse(
            new Response(403), // structure
        );

        $this->memberTracking->updateStructure($data, null);
        $this->om->flush();

        $resultLocations = $this->repositoryFactory->getEsiLocationRepository()->findBy([]);
        $this->assertSame(1, count($resultLocations));
        $this->assertSame(1023100200300, $resultLocations[0]->getId());
        $this->assertSame(EsiLocation::CATEGORY_STRUCTURE, $resultLocations[0]->getCategory());
        $this->assertSame('', $resultLocations[0]->getName());
        $this->assertNull($resultLocations[0]->getOwnerId());
        $this->assertNull($resultLocations[0]->getSystemId());
        $this->assertLessThanOrEqual(time(), $resultLocations[0]->getLastUpdate()->getTimestamp());
        $this->assertSame(1, $resultLocations[0]->getErrorCount());
    }

    public function testFetchCharacterNames()
    {
        $this->client->setResponse(
            new Response(200, [], '[
                {"category": "character", "id": "101", "name": "char 1"},
                {"category": "character", "id": "102", "name": "char 2"},
                {"category": "character", "id": "103", "name": "char 3"}
            ]'), // postUniverseNames for char names
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
            new CorporationsCorporationIdMembertrackingGetInner([
                'character_id' => 101,
                'location_id' => 60008494,
                'logoff_date' => new \DateTime('2018-12-25 19:45:10'),
                'logon_date' => new \DateTime('2018-12-25 19:45:11'),
                'ship_type_id' => 670,
                'start_date' => new \DateTime('2018-12-25 19:45:12'),
            ]),
            new CorporationsCorporationIdMembertrackingGetInner([
                'character_id' => 102,
                'location_id' => 1023100200300,
            ]),
            new CorporationsCorporationIdMembertrackingGetInner([
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
