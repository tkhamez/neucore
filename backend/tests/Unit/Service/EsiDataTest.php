<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Neucore\Entity\Alliance;
use Neucore\Entity\EsiLocation;
use Neucore\Exception\RuntimeException;
use Neucore\Factory\EsiApiFactory;
use Neucore\Entity\Corporation;
use Neucore\Factory\RepositoryFactory;
use Neucore\Service\Character;
use Neucore\Service\Config;
use Neucore\Service\EsiData;
use Neucore\Service\ObjectManager;
use GuzzleHttp\Psr7\Response;
use Monolog\Handler\TestHandler;
use PHPUnit\Framework\TestCase;
use Swagger\Client\Eve\Model\PostUniverseNames200Ok;
use Tests\Helper;
use Tests\Client;
use Tests\Logger;
use Tests\WriteErrorListener;

class EsiDataTest extends TestCase
{
    /**
     * @var WriteErrorListener
     */
    private static $writeErrorListener;

    /**
     * @var Helper
     */
    private $testHelper;

    /**
     * @var EntityManagerInterface
     */
    private $em;

    /**
     * @var Client
     */
    private $client;

    /**
     * @var RepositoryFactory
     */
    private $repoFactory;

    /**
     * @var EsiData
     */
    private $cs;

    /**
     * @var Logger
     */
    private $log;

    public static function setupBeforeClass(): void
    {
        self::$writeErrorListener = new WriteErrorListener();
    }

    protected function setUp(): void
    {
        $this->testHelper = new Helper();
        $this->em = $this->testHelper->getEm();

        $this->log = new Logger('Test');
        $this->log->pushHandler(new TestHandler());

        $config = new Config(['eve' => ['datasource' => '', 'esi_host' => '']]);
        $this->client = new Client();
        $this->repoFactory = new RepositoryFactory($this->em);

        $om = new ObjectManager($this->em, $this->log);
        $this->cs = new EsiData(
            $this->log,
            new EsiApiFactory($this->client, $config),
            $om,
            $this->repoFactory,
            new Character($om),
            $config
        );
    }

    public function tearDown(): void
    {
        $this->em->getEventManager()->removeEventListener(Events::onFlush, self::$writeErrorListener);
    }

    public function testFetchCharacterWithCorporationAndAllianceCharInvalid()
    {
        $this->client->setResponse(
            new Response(404)
        );

        $char = $this->cs->fetchCharacterWithCorporationAndAlliance(10);
        $this->assertNull($char);
    }

    public function testFetchCharacterWithCorporationAndAllianceCorpError()
    {
        $this->testHelper->emptyDb();
        $this->testHelper->addCharacterMain('newChar', 10, []);

        $this->client->setResponse(
            new Response(200, [], '{
                "name": "char name",
                "corporation_id": 20
            }'),
            new Response(200, [], '[{
                "character_id": 10,
                "corporation_id": 20
            }]'),
            new Response(404)
        );

        $char = $this->cs->fetchCharacterWithCorporationAndAlliance(10);
        $this->assertNull($char);
    }

    public function testFetchCharacterWithCorporationAndAllianceAlliError()
    {
        $this->testHelper->emptyDb();
        $this->testHelper->addCharacterMain('newChar', 10, []);

        $this->client->setResponse(
            new Response(200, [], '{
                "name": "char name",
                "corporation_id": 20
            }'),
            new Response(200, [], '[{
                "alliance_id": 30,
                "character_id": 10,
                "corporation_id": 20
            }]'),
            new Response(200, [], '{
                "name": "corp name",
                "ticker": "-cn-",
                "alliance_id": 30
            }'),
            new Response(404)
        );

        $char = $this->cs->fetchCharacterWithCorporationAndAlliance(10);
        $this->assertNull($char);
    }

    public function testFetchCharacterWithCorporationAndAlliance()
    {
        $this->testHelper->emptyDb();
        $this->testHelper->addCharacterMain('newChar', 10, []);

        $this->client->setResponse(
            new Response(200, [], '{
                "name": "char name",
                "corporation_id": 20
            }'),
            new Response(200, [], '[{
                "alliance_id": 30,
                "character_id": 10,
                "corporation_id": 20
            }]'),
            new Response(200, [], '{
                "name": "corp name",
                "ticker": "-cn-",
                "alliance_id": 30
            }'),
            new Response(200, [], '{
                "name": "alli name",
                "ticker": "-an-"
            }')
        );

        $char = $this->cs->fetchCharacterWithCorporationAndAlliance(10);
        $this->assertSame('char name', $char->getName());
        $this->assertSame('char name', $char->getPlayer()->getName());
        $this->assertSame('corp name', $char->getCorporation()->getName());
        $this->assertSame('alli name', $char->getCorporation()->getAlliance()->getName());
    }

    public function testFetchCharacterInvalidId()
    {
        $char = $this->cs->fetchCharacter(-1);
        $this->assertNull($char);
    }

    public function testFetchCharacterNotInDB()
    {
        $this->testHelper->emptyDb();

        $char = $this->cs->fetchCharacter(123);
        $this->assertNull($char);
    }

    public function testFetchCharacterNotFound()
    {
        $this->testHelper->emptyDb();
        $this->testHelper->addCharacterMain('newChar', 123, []);

        $this->client->setResponse(new Response(404));

        $char = $this->cs->fetchCharacter(123);
        $this->assertNull($char);
        $this->assertStringStartsWith('[404] Error ', $this->log->getHandler()->getRecords()[0]['message']);
    }

    /**
     * @throws \Exception
     */
    public function testFetchCharacterNoFlush()
    {
        $this->testHelper->emptyDb();
        $char = $this->testHelper->addCharacterMain('newChar', 123, []);
        $char->setLastUpdate(new \DateTime('2018-03-26 17:24:30'));
        $this->em->flush();

        $this->client->setResponse(
            new Response(200, [], '{
                "name": "new corp",
                "corporation_id": 234
            }'),
            new Response(200, [], '[{
                "character_id": 10,
                "corporation_id": 234
            }]')
        );

        $char = $this->cs->fetchCharacter(123, false);
        $this->assertSame(123, $char->getId());
        $this->assertSame('new corp', $char->getName());
        $this->assertSame(234, $char->getCorporation()->getId());
        $this->assertNull($char->getCorporation()->getName());

        $this->em->clear();
        $charDb = $this->repoFactory->getCharacterRepository()->find(123);
        $this->assertNull($charDb->getCorporation());
    }

    /**
     * @throws \Exception
     */
    public function testFetchCharacter()
    {
        $this->testHelper->emptyDb();
        $char = $this->testHelper->addCharacterMain('old char name', 123, []);
        $char->setLastUpdate(new \DateTime('2018-03-26 17:24:30'));
        $this->em->flush();

        $this->client->setResponse(
            new Response(200, [], '{
                "name": "new char name",
                "corporation_id": 234
            }'),
            new Response(200, [], '[{
                "character_id": 10,
                "corporation_id": 234
            }]')
        );

        $char = $this->cs->fetchCharacter(123);
        $this->assertSame(123, $char->getId());
        $this->assertSame('new char name', $char->getName());
        $this->assertSame('old char name', $char->getCharacterNameChanges()[0]->getOldName());
        $this->assertSame(234, $char->getCorporation()->getId());
        $this->assertNull($char->getCorporation()->getName());

        $this->em->clear();
        $charDb = $this->repoFactory->getCharacterRepository()->find(123);
        $this->assertSame(234, $charDb->getCorporation()->getId());
        $this->assertSame('UTC', $charDb->getLastUpdate()->getTimezone()->getName());
        $this->assertGreaterThan('2018-03-26 17:24:30', $charDb->getLastUpdate()->format('Y-m-d H:i:s'));
        $this->assertSame('new char name', $charDb->getName());
        $this->assertSame(1, count($charDb->getCharacterNameChanges()));
        $this->assertSame('old char name', $charDb->getCharacterNameChanges()[0]->getOldName());
    }

    public function testFetchCharactersAffiliation()
    {
        $this->client->setResponse(new Response(200, [], '[{
            "alliance_id": 11,
            "character_id": 1001,
            "corporation_id": 101
          }, {
            "character_id": 1002,
            "corporation_id": 102
        }]'));

        $actual = $this->cs->fetchCharactersAffiliation([1001, 1002]);

        $this->assertSame(2, count($actual));
        $this->assertSame(1001, $actual[0]->getCharacterId());
        $this->assertSame(1002, $actual[1]->getCharacterId());
        $this->assertSame(101, $actual[0]->getCorporationId());
        $this->assertSame(102, $actual[1]->getCorporationId());
        $this->assertSame(11, $actual[0]->getAllianceId());
        $this->assertSame(null, $actual[1]->getAllianceId());
    }

    public function testFetchCorporationInvalidId()
    {
        $corp = $this->cs->fetchCorporation(-1);
        $this->assertNull($corp);
    }

    public function testFetchCorporationError500()
    {
        $this->client->setResponse(new Response(500));

        $corp = $this->cs->fetchCorporation(123);
        $this->assertNull($corp);
        $this->assertStringStartsWith('[500] Error ', $this->log->getHandler()->getRecords()[0]['message']);
    }

    public function testFetchCorporationNoFlushNoAlliance()
    {
        $this->testHelper->emptyDb();

        $this->client->setResponse(new Response(200, [], '{
            "name": "The Corp.",
            "ticker": "-HAT-",
            "alliance_id": null
        }'));

        $corp = $this->cs->fetchCorporation(234, false);
        $this->assertSame(234, $corp->getId());
        $this->assertSame('The Corp.', $corp->getName());
        $this->assertSame('-HAT-', $corp->getTicker());
        $this->assertNull($corp->getAlliance());

        $this->em->clear();
        $corpDb = $this->repoFactory->getCorporationRepository()->find(234);
        $this->assertNull($corpDb->getName());
    }

    public function testFetchCorporation()
    {
        $this->testHelper->emptyDb();

        $this->client->setResponse(
            new Response(200, [], '{
                "name": "The Corp.",
                "ticker": "-HAT-",
                "alliance_id": 345
            }'),
            new Response(200, [], '{
                "name": "The A.",
                "ticker": "-A-"
            }')
        );

        $corp = $this->cs->fetchCorporation(234);
        $this->assertSame(234, $corp->getId());
        $this->assertSame('The Corp.', $corp->getName());
        $this->assertSame('-HAT-', $corp->getTicker());
        $this->assertSame(345, $corp->getAlliance()->getId());
        $this->assertNull($corp->getAlliance()->getName());
        $this->assertNull($corp->getAlliance()->getTicker());
        $this->assertSame('UTC', $corp->getLastUpdate()->getTimezone()->getName());
        $this->assertGreaterThan('2018-07-29 16:30:30', $corp->getLastUpdate()->format('Y-m-d H:i:s'));

        $this->em->clear();
        $corpDb = $this->repoFactory->getCorporationRepository()->find(234);
        $this->assertSame(234, $corpDb->getId());
        $this->assertSame(345, $corpDb->getAlliance()->getId());
    }

    public function testFetchCorporationNoAllianceRemovesAlliance()
    {
        $this->testHelper->emptyDb();
        $alli = (new Alliance())->setId(100)->setName('A')->setTicker('a');
        $corp = (new Corporation())->setId(200)->setName('C')->setTicker('c')->setAlliance($alli);
        $this->em->persist($alli);
        $this->em->persist($corp);
        $this->em->flush();
        $this->em->clear();

        $this->client->setResponse(new Response(200, [], '{
            "name": "C",
            "ticker": "c",
            "alliance_id": null
        }'));

        $corpResult = $this->cs->fetchCorporation(200);
        $this->assertNull($corpResult->getAlliance());
        $this->em->clear();

        // load from DB
        $corporation = $this->repoFactory->getCorporationRepository()->find(200);
        $this->assertNull($corporation->getAlliance());
        $alliance = $this->repoFactory->getAllianceRepository()->find(100);
        $this->assertSame([], $alliance->getCorporations());
    }

    public function testFetchAllianceInvalidId()
    {
        $alli = $this->cs->fetchAlliance(-1);
        $this->assertNull($alli);
    }

    public function testFetchAllianceError500()
    {
        $this->client->setResponse(new Response(500));

        $alli = $this->cs->fetchAlliance(123);
        $this->assertNull($alli);
        $this->assertStringStartsWith('[500] Error ', $this->log->getHandler()->getRecords()[0]['message']);
    }

    public function testFetchAllianceNoFlush()
    {
        $this->testHelper->emptyDb();

        $this->client->setResponse(new Response(200, [], '{
            "name": "The A.",
            "ticker": "-A-"
        }'));

        $alli = $this->cs->fetchAlliance(345, false);
        $this->assertSame(345, $alli->getId());
        $this->assertSame('The A.', $alli->getName());
        $this->assertSame('-A-', $alli->getTicker());

        $this->em->clear();
        $alliDb = $this->repoFactory->getAllianceRepository()->find(345);
        $this->assertNull($alliDb->getName());
    }

    public function testFetchAlliance()
    {
        $this->testHelper->emptyDb();

        $this->client->setResponse(new Response(200, [], '{
            "name": "The A.",
            "ticker": "-A-"
        }'));

        $alli = $this->cs->fetchAlliance(345);
        $this->assertSame(345, $alli->getId());
        $this->assertSame('The A.', $alli->getName());
        $this->assertSame('-A-', $alli->getTicker());
        $this->assertSame('UTC', $alli->getLastUpdate()->getTimezone()->getName());
        $this->assertGreaterThan('2018-07-29 16:30:30', $alli->getLastUpdate()->format('Y-m-d H:i:s'));

        $this->em->clear();
        $alliDb = $this->repoFactory->getAllianceRepository()->find(345);
        $this->assertSame(345, $alliDb->getId());
    }

    public function testFetchAllianceCreateFlushError()
    {
        $this->em->getEventManager()->addEventListener(Events::onFlush, self::$writeErrorListener);

        $this->testHelper->emptyDb();

        $this->client->setResponse(new Response(200, [], '{
            "name": "The A.",
            "ticker": "-A-"
        }'));

        $alli = $this->cs->fetchAlliance(345, true);
        $this->assertNull($alli);
    }

    public function testFetchUniverseNames()
    {
        $this->client->setResponse(new Response(200, [], '[{
            "id": 123,
            "name": "The Name",
            "category": "character"
        }, {
            "id": 124,
            "name": "Another Name",
            "category": "inventory_type"
        }]'));

        $names = $this->cs->fetchUniverseNames([123, 124]);

        $this->assertSame(2, count($names));
        $this->assertSame(123, $names[0]->getId());
        $this->assertSame(124, $names[1]->getId());
        $this->assertSame('The Name', $names[0]->getName());
        $this->assertSame('Another Name', $names[1]->getName());
        $this->assertSame(PostUniverseNames200Ok::CATEGORY_CHARACTER, $names[0]->getCategory());
        $this->assertSame(PostUniverseNames200Ok::CATEGORY_INVENTORY_TYPE, $names[1]->getCategory());
    }

    public function testFetchStructure()
    {
        $this->testHelper->emptyDb();

        $this->client->setResponse(new Response(200, [], '{
            "name": "V-3YG7 VI - The Capital",
            "owner_id": 109299958,
            "solar_system_id": 30000142
        }'));

        $location = $this->cs->fetchStructure(1023100200300, 'access-token');

        $this->assertSame(1023100200300, $location->getId());
        $this->assertSame('V-3YG7 VI - The Capital', $location->getName());
        $this->assertSame(EsiLocation::CATEGORY_STRUCTURE, $location->getCategory());
        $this->assertSame(109299958, $location->getOwnerId());
        $this->assertSame(30000142, $location->getSystemId());
        $this->assertGreaterThan('2019-11-18T19:34:14+00:00', $location->getLastUpdate()->format(\DateTime::ATOM));

        $this->em->clear();

        $locationDb = $this->repoFactory->getEsiLocationRepository()->find(1023100200300);
        $this->assertSame(1023100200300, $locationDb->getId());
        $this->assertSame('V-3YG7 VI - The Capital', $locationDb->getName());
        $this->assertSame(EsiLocation::CATEGORY_STRUCTURE, $locationDb->getCategory());
        $this->assertSame(109299958, $locationDb->getOwnerId());
        $this->assertSame(30000142, $locationDb->getSystemId());
    }

    public function testFetchCorporationMembersNoToken()
    {
        $this->assertSame([], $this->cs->fetchCorporationMembers(100200300, ''));
    }

    public function testFetchCorporationMembersEsiError()
    {
        $this->client->setMiddleware(function () {
            throw new RuntimeException("", 520);
        });
        $this->client->setResponse(new Response(200, [], '[100, 200]'));

        $this->assertSame([], $this->cs->fetchCorporationMembers(100200300, 'access-token'));
    }

    public function testFetchCorporationMembers()
    {
        $this->client->setResponse(new Response(200, [], '[100, 200]'));

        $this->assertSame([100, 200], $this->cs->fetchCorporationMembers(100200300, 'access-token'));
    }

    public function testGetCorporationEntity()
    {
        $this->testHelper->emptyDb();

        $result = $this->cs->getCorporationEntity(100);
        $this->assertSame(100, $result->getId());

        $this->em->clear();

        $corp = $this->repoFactory->getCorporationRepository()->find(100);
        $this->assertInstanceOf(Corporation::class, $corp);
    }
}
