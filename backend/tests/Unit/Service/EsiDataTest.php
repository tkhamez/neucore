<?php

/** @noinspection DuplicatedCode */

declare(strict_types=1);

namespace Tests\Unit\Service;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Neucore\Entity\Alliance;
use Neucore\Entity\EsiLocation;
use Neucore\Entity\SystemVariable;
use Neucore\Exception\Exception;
use Neucore\Exception\RuntimeException;
use Neucore\Factory\EveApiFactory;
use Neucore\Entity\Corporation;
use Neucore\Factory\RepositoryFactory;
use Neucore\Service\Character;
use Neucore\Service\Config;
use Neucore\Service\EsiData;
use Neucore\Service\EveMailToken;
use Neucore\Service\ObjectManager;
use GuzzleHttp\Psr7\Response;
use Monolog\Handler\TestHandler;
use PHPUnit\Framework\TestCase;
use Tests\Helper;
use Tests\Client;
use Tests\HttpClientFactory;
use Tests\Logger;
use Tests\WriteErrorListener;
use Tkhamez\Eve\API\ApiException;
use Tkhamez\Eve\API\Model\UniverseNamesPostInner;

class EsiDataTest extends TestCase
{
    private static WriteErrorListener $writeErrorListener;

    private Helper $testHelper;

    private EntityManagerInterface $em;

    private Client $client;

    private RepositoryFactory $repoFactory;

    private EsiData $esiData;

    private Logger $log;

    private Config $config;

    private ObjectManager $om;

    public static function setupBeforeClass(): void
    {
        self::$writeErrorListener = new WriteErrorListener();
    }

    protected function setUp(): void
    {
        $this->testHelper = new Helper();
        $this->em = $this->testHelper->getEm();

        $this->log = new Logger();
        $this->log->pushHandler(new TestHandler());

        $this->config = Helper::getConfig();
        $this->client = new Client();
        $this->repoFactory = new RepositoryFactory($this->em);

        $this->om = new ObjectManager($this->em, $this->log);
        $this->esiData = $this->createESIData();
    }

    public function tearDown(): void
    {
        $this->em->getEventManager()->removeEventListener(Events::onFlush, self::$writeErrorListener);
    }

    /**
     * @throws Exception
     */
    public function testFetchCharacter_NotFound1(): void
    {
        $response = new Response(404, [], '{"error": "Character not found"}');
        $this->client->setMiddleware(function () use ($response) {
            throw new ApiException(
                code: $response->getStatusCode(),
                responseBody: $response->getBody()->getContents(),
            );
        });
        $this->client->setResponse($response);

        $this->expectExceptionCode(404);
        $this->expectExceptionMessage('Character not found (exception)');
        $this->esiData->fetchCharacter(10);
    }

    /**
     * @throws Exception
     */
    public function testFetchCharacter_NotFound2(): void
    {
        $this->client->setResponse(new Response(404, [], '{"error": "Character not found"}'));

        $this->expectExceptionCode(404);
        $this->expectExceptionMessage('Character not found (error object)');
        $this->esiData->fetchCharacter(10);
    }

    /**
     * @throws Exception
     */
    public function testFetchCharacter_Deleted1(): void
    {
        $response = new Response(404, [], '{"error": "Character has been deleted"}');
        $this->client->setMiddleware(function () use ($response) {
            throw new ApiException(
                code: $response->getStatusCode(),
                responseBody: $response->getBody()->getContents(),
            );
        });
        $this->client->setResponse($response);

        $this->expectExceptionCode(410);
        $this->expectExceptionMessage('Character has been deleted (exception)');
        $this->esiData->fetchCharacter(10);
    }

    /**
     * @throws Exception
     */
    public function testFetchCharacter_Deleted2(): void
    {
        $this->client->setResponse(new Response(410, [], '{"error": "Character has been deleted"}'));

        $this->expectExceptionCode(410);
        $this->expectExceptionMessage('Character has been deleted (error object)');
        $this->esiData->fetchCharacter(10);
    }

    /**
     * @throws Exception
     */
    public function testFetchCharacter_500EsiError1(): void
    {
        $this->client->setResponse(new Response(400, body: 'body'));

        $this->expectExceptionMessage('body');
        $this->esiData->fetchCharacter(456789);

        $this->assertStringStartsWith(
            'Error JSON decoding server response',
            $this->log->getMessages()[0],
        );
    }

    /**
     * @throws Exception
     */
    public function testFetchCharacter_500EsiError2(): void
    {
        $this->expectExceptionMessage('ESI error.');
        $this->esiData->fetchCharacter(0);

        $this->assertStringContainsString(
            '0 does not meet minimum of 1',
            $this->log->getMessages()[0],
        );
    }

    /**
     * @throws Exception
     */
    public function testFetchCharacter_Found(): void
    {
        $this->client->setResponse(new Response(200, [], '{"name": "Char 456789"}'));

        $char = $this->esiData->fetchCharacter(456789);

        $this->assertSame('Char 456789', $char->getName());
    }

    public function testFetchCharacterWithCorporationAndAlliance_CharInvalid(): void
    {
        $this->client->setResponse(
            new Response(404),
        );

        $char = $this->esiData->fetchCharacterWithCorporationAndAlliance(10);
        $this->assertNull($char);
    }

    public function testFetchCharacterWithCorporationAndAlliance_CorpError(): void
    {
        $this->testHelper->emptyDb();
        $this->testHelper->addCharacterMain('newChar', 10);

        $this->client->setResponse(
            new Response(200, [], '{
                "name": "char name",
                "corporation_id": 20
            }'),
            new Response(200, [], '[{
                "character_id": 10,
                "corporation_id": 20
            }]'),
            new Response(404),
        );

        $char = $this->esiData->fetchCharacterWithCorporationAndAlliance(10);
        $this->assertNull($char);
    }

    public function testFetchCharacterWithCorporationAndAlliance_AlliError(): void
    {
        $this->testHelper->emptyDb();
        $this->testHelper->addCharacterMain('newChar', 10);

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
            new Response(404),
        );

        $char = $this->esiData->fetchCharacterWithCorporationAndAlliance(10);
        $this->assertNull($char);
    }

    public function testFetchCharacterWithCorporationAndAlliance_Ok(): void
    {
        $this->testHelper->emptyDb();
        $this->testHelper->addCharacterMain('newChar', 10);

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
            }'),
        );

        $char = $this->esiData->fetchCharacterWithCorporationAndAlliance(10);
        $this->assertSame('char name', $char?->getName());
        $this->assertSame('char name', $char->getPlayer()->getName());
        $this->assertSame('corp name', $char->getCorporation()?->getName());
        $this->assertSame('alli name', $char->getCorporation()->getAlliance()?->getName());
    }

    public function testUpdateCharacter_InvalidId(): void
    {
        $char = $this->esiData->updateCharacter(-1);
        $this->assertNull($char);
    }

    public function testUpdateCharacter_NotInDB(): void
    {
        $this->testHelper->emptyDb();

        $char = $this->esiData->updateCharacter(123);
        $this->assertNull($char);
    }

    public function testUpdateCharacter_404NotFound(): void
    {
        $this->testHelper->emptyDb();
        $this->testHelper->addCharacterMain('newChar', 123);

        $this->client->setResponse(new Response(404, body: 'Character not found'));
        $char = $this->esiData->updateCharacter(123);

        $this->assertNull($char);
        $this->assertSame(404, $this->esiData->getLastErrorCode());
    }

    public function testUpdateCharacter_404Deleted(): void
    {
        $this->testHelper->emptyDb();
        $char = $this->testHelper->addCharacterMain('old char name', 123);
        $char->setLastUpdate(new \DateTime('2018-03-26 17:24:30'));
        $this->em->flush();

        $this->client->setResponse(
            new Response(404, [], '{"error":"Character has been deleted!"}'),
        );
        $char = $this->esiData->updateCharacter(123);

        $this->assertFalse(isset($this->log->getMessages()[0]));

        $this->assertSame(123, $char?->getId());
        $this->assertSame('old char name', $char->getName());
        $this->assertSame(EsiData::CORPORATION_DOOMHEIM_ID, $char->getCorporation()?->getId());
        $this->assertNull($char->getCorporation()->getName());

        $this->em->clear();
        $charDb = $this->repoFactory->getCharacterRepository()->find(123);
        $this->assertSame(EsiData::CORPORATION_DOOMHEIM_ID, $charDb?->getCorporation()?->getId());
        $this->assertSame('UTC', $charDb->getLastUpdate()?->getTimezone()->getName());
        $this->assertGreaterThan('2021-11-15 14:29:31', $charDb->getLastUpdate()->format('Y-m-d H:i:s'));
        $this->assertSame('old char name', $charDb->getName());
    }

    public function testUpdateCharacter_AffiliationDeleted(): void
    {
        $this->testHelper->emptyDb();
        $char = $this->testHelper->addCharacterMain('old char name', 123);
        $char->setLastUpdate(new \DateTime('2018-03-26 17:24:30'));
        $this->em->flush();

        $this->client->setResponse(
            new Response(200, [], '{
                "name": "new char name",
                "corporation_id": 234
            }'),
            new Response(200, [], '[{
                "character_id": 123,
                "corporation_id": ' . EsiData::CORPORATION_DOOMHEIM_ID . '
            }]'),
        );
        $char = $this->esiData->updateCharacter(123);
        $this->em->flush();

        $this->assertFalse(isset($this->log->getHandler()?->getRecords()[0]));

        $this->assertSame(123, $char?->getId());
        $this->assertSame('new char name', $char->getName());
        $this->assertSame(EsiData::CORPORATION_DOOMHEIM_ID, $char->getCorporation()?->getId());
    }

    /**
     * @throws \Exception
     */
    public function testUpdateCharacter_NoFlush(): void
    {
        $this->testHelper->emptyDb();
        $char = $this->testHelper->addCharacterMain('newChar', 123);
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
            }]'),
        );

        $char = $this->esiData->updateCharacter(123, false);
        $this->assertSame(123, $char?->getId());
        $this->assertSame('new corp', $char->getName());
        $this->assertSame(234, $char->getCorporation()?->getId());
        $this->assertNull($char->getCorporation()->getName());

        $this->em->clear();
        $charDb = $this->repoFactory->getCharacterRepository()->find(123);
        $this->assertNull($charDb?->getCorporation());
    }

    /**
     * @throws \Exception
     */
    public function testUpdateCharacter_Ok(): void
    {
        $this->testHelper->emptyDb();
        $char = $this->testHelper->addCharacterMain('old char name', 123);
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
            }]'),
        );

        $char = $this->esiData->updateCharacter(123);
        $this->assertSame(123, $char?->getId());
        $this->assertSame('new char name', $char->getName());
        $this->assertSame('old char name', $char->getCharacterNameChanges()[0]->getOldName());
        $this->assertSame(234, $char->getCorporation()?->getId());
        $this->assertNull($char->getCorporation()->getName());

        $this->em->clear();
        $charDb = $this->repoFactory->getCharacterRepository()->find(123);
        $this->assertSame(234, $charDb?->getCorporation()?->getId());
        $this->assertSame('UTC', $charDb->getLastUpdate()?->getTimezone()->getName());
        $this->assertGreaterThan('2018-03-26 17:24:30', $charDb->getLastUpdate()->format('Y-m-d H:i:s'));
        $this->assertSame('new char name', $charDb->getName());
        $this->assertSame(1, count($charDb->getCharacterNameChanges()));
        $this->assertSame('old char name', $charDb->getCharacterNameChanges()[0]->getOldName());
    }

    public function testFetchCharacters_Affiliation(): void
    {
        $this->client->setResponse(new Response(200, [], '[{
            "alliance_id": 11,
            "character_id": 1001,
            "corporation_id": 101
          }, {
            "character_id": 1002,
            "corporation_id": 102
        }]'));

        $actual = $this->esiData->fetchCharactersAffiliation([1001, 1002]);

        $this->assertSame(2, count($actual));
        $this->assertSame(1001, $actual[0]->getCharacterId());
        $this->assertSame(1002, $actual[1]->getCharacterId());
        $this->assertSame(101, $actual[0]->getCorporationId());
        $this->assertSame(102, $actual[1]->getCorporationId());
        $this->assertSame(11, $actual[0]->getAllianceId());
        $this->assertSame(null, $actual[1]->getAllianceId());
    }

    public function testFetchCorporationInvalidId(): void
    {
        $corp = $this->esiData->fetchCorporation(-1);
        $this->assertNull($corp);
    }

    public function testFetchCorporationError500(): void
    {
        $this->client->setResponse(new Response(500));

        $corp = $this->esiData->fetchCorporation(123);
        $this->assertNull($corp);
        $this->assertStringStartsWith('Error JSON decoding server response', $this->log->getMessages()[0]);
    }

    public function testFetchCorporationNoFlushNoAlliance(): void
    {
        $this->testHelper->emptyDb();

        $this->client->setResponse(new Response(200, [], '{
            "name": "The Corp.",
            "ticker": "-HAT-",
            "alliance_id": null
        }'));

        $corp = $this->esiData->fetchCorporation(234, false);
        $this->assertSame(234, $corp?->getId());
        $this->assertSame('The Corp.', $corp->getName());
        $this->assertSame('-HAT-', $corp->getTicker());
        $this->assertNull($corp->getAlliance());

        $this->em->clear();
        $corpDb = $this->repoFactory->getCorporationRepository()->find(234);
        $this->assertNull($corpDb?->getName());
    }

    public function testFetchCorporation(): void
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
            }'),
        );

        $corp = $this->esiData->fetchCorporation(234);
        $this->assertSame(234, $corp?->getId());
        $this->assertSame('The Corp.', $corp->getName());
        $this->assertSame('-HAT-', $corp->getTicker());
        $this->assertSame(345, $corp->getAlliance()?->getId());
        $this->assertNull($corp->getAlliance()->getName());
        $this->assertNull($corp->getAlliance()->getTicker());
        $this->assertSame('UTC', $corp->getLastUpdate()?->getTimezone()->getName());
        $this->assertGreaterThan('2018-07-29 16:30:30', $corp->getLastUpdate()->format('Y-m-d H:i:s'));

        $this->em->clear();
        $corpDb = $this->repoFactory->getCorporationRepository()->find(234);
        $this->assertSame(234, $corpDb?->getId());
        $this->assertSame(345, $corpDb->getAlliance()?->getId());
    }

    public function testFetchCorporationNoAllianceRemovesAlliance(): void
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

        $corpResult = $this->esiData->fetchCorporation(200);
        $this->assertNull($corpResult?->getAlliance());
        $this->em->clear();

        // load from DB
        $corporation = $this->repoFactory->getCorporationRepository()->find(200);
        $this->assertNull($corporation?->getAlliance());
        $alliance = $this->repoFactory->getAllianceRepository()->find(100);
        $this->assertSame([], $alliance?->getCorporations());
    }

    public function testFetchAllianceInvalidId(): void
    {
        $alli = $this->esiData->fetchAlliance(-1);
        $this->assertNull($alli);
    }

    public function testFetchAllianceError500(): void
    {
        $this->client->setResponse(new Response(500));

        $alli = $this->esiData->fetchAlliance(123);
        $this->assertNull($alli);
        $this->assertStringStartsWith('Error JSON decoding server response', $this->log->getMessages()[0]);
    }

    public function testFetchAllianceNoFlush(): void
    {
        $this->testHelper->emptyDb();

        $this->client->setResponse(new Response(200, [], '{
            "name": "The A.",
            "ticker": "-A-"
        }'));

        $alli = $this->esiData->fetchAlliance(345, false);
        $this->assertSame(345, $alli?->getId());
        $this->assertSame('The A.', $alli->getName());
        $this->assertSame('-A-', $alli->getTicker());

        $this->em->clear();
        $alliDb = $this->repoFactory->getAllianceRepository()->find(345);
        $this->assertNull($alliDb?->getName());
    }

    public function testFetchAlliance(): void
    {
        $this->testHelper->emptyDb();

        $this->client->setResponse(new Response(200, [], '{
            "name": "The A.",
            "ticker": "-A-"
        }'));

        $alli = $this->esiData->fetchAlliance(345);
        $this->assertSame(345, $alli?->getId());
        $this->assertSame('The A.', $alli->getName());
        $this->assertSame('-A-', $alli->getTicker());
        $this->assertSame('UTC', $alli->getLastUpdate()?->getTimezone()->getName());
        $this->assertGreaterThan('2018-07-29 16:30:30', $alli->getLastUpdate()->format('Y-m-d H:i:s'));

        $this->em->clear();
        $alliDb = $this->repoFactory->getAllianceRepository()->find(345);
        $this->assertSame(345, $alliDb?->getId());
    }

    public function testFetchAllianceCreateFlushError(): void
    {
        $this->em->getEventManager()->addEventListener(Events::onFlush, self::$writeErrorListener);

        $this->testHelper->emptyDb();

        $this->client->setResponse(new Response(200, [], '{
            "name": "The A.",
            "ticker": "-A-"
        }'));

        $alli = $this->esiData->fetchAlliance(345);
        $this->assertNull($alli);
    }

    public function testFetchUniverseNames(): void
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

        $names = $this->esiData->fetchUniverseNames([123, 124]);

        $this->assertSame(2, count($names));
        $this->assertSame(123, $names[0]->getId());
        $this->assertSame(124, $names[1]->getId());
        $this->assertSame('The Name', $names[0]->getName());
        $this->assertSame('Another Name', $names[1]->getName());
        $this->assertSame(UniverseNamesPostInner::CATEGORY_CHARACTER, $names[0]->getCategory());
        $this->assertSame(UniverseNamesPostInner::CATEGORY_INVENTORY_TYPE, $names[1]->getCategory());
    }

    public function testFetchUniverseNames_Exceptions(): void
    {
        $this->client->setMiddleware(function () {
            throw new \Exception('message');
        });
        $this->client->setResponse(new Response());

        $names = $this->esiData->fetchUniverseNames([1, 2]);

        $this->assertSame(0, count($names));
        $this->assertSame(['message'], $this->log->getMessages());
    }

    public function testFetchUniverseNames_InvalidIds(): void
    {
        $this->client->setMiddleware(function () {
            static $requestNumber = 0;
            $requestNumber++;
            if (in_array($requestNumber, [1, 2, 3, 6])) {
                $msg = '... {\"error\":\"Ensure all IDs are valid before resolving.\"}';
                throw new ApiException($msg, 404, [], $msg);
            }
            return function () {};
        });
        $this->client->setResponse(
            new Response(404), // r1 - 1-1000
            new Response(404), // r2 - 1-100
            new Response(404), // r3 - 1-10
            new Response(200, [], '[{"id": 1, "name": "N 1", "category": "character"}]'), // r4 - 1
            new Response(200, [], '[]'), // r5 - 2
            new Response(404), // r6 - 3,
            new Response(200, [], '[{"id": 4, "name": "N 4", "category": "character"}]'), // r7 - 4
            new Response(200, [], '[]'), // r8 - 5
            new Response(200, [], '[]'), // r9 - 6
            new Response(200, [], '[]'), // r10 - 7
            new Response(200, [], '[]'), // r11 - 8
            new Response(200, [], '[]'), // r12 - 9
            new Response(200, [], '[{"id": 10, "name": "N 10", "category": "character"}]'), // r13 - 10
            new Response(200, [], '[{"id": 11, "name": "N 11", "category": "character"}]'), // r14 - 11-20
            new Response(200, [], '[]'), // r15 - 21-30
            new Response(200, [], '[]'), // r16 - 31-40
            new Response(200, [], '[]'), // r17 - 41-50
            new Response(200, [], '[]'), // r18 - 51-60
            new Response(200, [], '[]'), // r19 - 61-70
            new Response(200, [], '[]'), // r20 - 71-80
            new Response(200, [], '[]'), // r21 - 81-90
            new Response(200, [], '[{"id": 100, "name": "N 100", "category": "character"}]'), // r22 - 91-100
            new Response(200, [], '[{"id": 200, "name": "N 200", "category": "character"}]'), // r23 - 101-200
            new Response(200, [], '[]'), // r24 - 201-300
            new Response(200, [], '[]'), // r25 - 301-400
            new Response(200, [], '[]'), // r26 - 401-500
            new Response(200, [], '[]'), // r27 - 501-600
            new Response(200, [], '[]'), // r28 - 601-700
            new Response(200, [], '[]'), // r29 - 701-800
            new Response(200, [], '[]'), // r30 - 801-900
            new Response(200, [], '[{"id": 1000, "name": "N 1000", "category": "character"}]'), // r31 - 901-1000
            new Response(200, [], '[{"id": 1500, "name": "N 1500", "category": "character"}]'), // r32 - 1001 - 1500
        );

        $names = $this->esiData->fetchUniverseNames(range(1, 1500));

        $this->assertSame(8, count($names));
        $this->assertSame(1, $names[0]->getId());
        $this->assertSame(4, $names[1]->getId());
        $this->assertSame(10, $names[2]->getId());
        $this->assertSame(11, $names[3]->getId());
        $this->assertSame(100, $names[4]->getId());
        $this->assertSame(200, $names[5]->getId());
        $this->assertSame(1000, $names[6]->getId());
        $this->assertSame(1500, $names[7]->getId());
        $this->assertSame('N 1', $names[0]->getName());
        $this->assertSame(UniverseNamesPostInner::CATEGORY_CHARACTER, $names[0]->getCategory());

        $records = (array) $this->log->getHandler()?->getRecords();
        #print_r($this->log->getMessages());
        $this->assertSame(4, count($records));
        $this->assertSame(
            'fetchUniverseNames: Invalid ID(s) in request, trying again with max. 100 IDs.',
            $records[0]['message'],
        );
        // @phpstan-ignore-next-line
        $this->assertNull($records[0]['context']['IDs'] ?? null);
        $this->assertSame(
            'fetchUniverseNames: Invalid ID(s) in request, trying again with max. 10 IDs.',
            $records[1]['message'],
        );
        // @phpstan-ignore-next-line
        $this->assertNull($records[1]['context']['IDs'] ?? null);
        $this->assertSame(
            'fetchUniverseNames: Invalid ID(s) in request, trying again with max. 1 IDs.',
            $records[2]['message'],
        );
        // @phpstan-ignore-next-line
        $this->assertNull($records[2]['context']['IDs'] ?? null);
        $this->assertSame(
            '... {\"error\":\"Ensure all IDs are valid before resolving.\"}',
            $records[3]['message'],
        );
        // @phpstan-ignore-next-line
        $this->assertSame([3], $records[3]['context']['IDs'] ?? []);
    }

    public function testFetchStructure_NoToken(): void
    {
        $this->testHelper->emptyDb();

        $location = $this->esiData->fetchStructure(1023100200300, '');

        $this->assertSame(1023100200300, $location->getId());
        $this->assertSame(EsiLocation::CATEGORY_STRUCTURE, $location->getCategory());
        $this->assertSame('', $location->getName());
        $this->assertNull($location->getOwnerId());
        $this->assertNull($location->getSystemId());
        $this->assertLessThanOrEqual(time(), $location->getLastUpdate()?->getTimestamp());
        $this->assertSame(0, $location->getErrorCount());

        $this->em->clear();
        $resultLocations = $this->repoFactory->getEsiLocationRepository()->findBy([]);
        $this->assertSame(1, count($resultLocations));
    }

    public function testFetchStructure_ErrorConfiguration(): void
    {
        $this->testHelper->emptyDb();

        $setting = (new SystemVariable(SystemVariable::FETCH_STRUCTURE_NAME_ERROR_DAYS))->setValue('3=7,10=30');

        // 3 errors, updated 6 days ago
        $updated1 = new \DateTime('now -6 days');
        $location1 = new EsiLocation();
        $location1->setId(1023100200300);
        $location1->setCategory(EsiLocation::CATEGORY_STRUCTURE);
        $location1->setLastUpdate($updated1);
        $location1->setErrorCount(3);
        $this->em->persist($setting);
        $this->em->persist($location1);
        $this->em->flush();
        $this->em->clear();

        $this->createESIData()->fetchStructure(1023100200300, '2F65A4');
        $this->em->clear();

        $location2 = $this->repoFactory->getEsiLocationRepository()->find(1023100200300);
        $this->assertSame(3, $location2?->getErrorCount());
        $this->assertSame('', $location2->getName());
        $this->assertSame($updated1->getTimestamp(), $location2->getLastUpdate()?->getTimestamp()); // not updated

        // 3 errors, updated 7 days ago
        $updated2 = new \DateTime('now -7 days');
        $location2->setLastUpdate($updated2);
        $this->em->flush();
        $this->em->clear();

        $this->client->setResponse(new Response(200, [], '{"name": "update 1"}'));
        $this->createESIData()->fetchStructure(1023100200300, '2F65A4');
        $this->em->clear();

        $location3 = $this->repoFactory->getEsiLocationRepository()->find(1023100200300);
        $this->assertSame(0, $location3?->getErrorCount());
        $this->assertSame('update 1', $location3->getName());
        $this->assertGreaterThan($updated2->getTimestamp(), $location3->getLastUpdate()?->getTimestamp()); // updated

        // 10 errors, updated 7 days ago
        $updated3 = new \DateTime('now -7 day');
        $location3->setErrorCount(10);
        $location3->setLastUpdate($updated3);
        $this->em->flush();
        $this->em->clear();

        $this->createESIData()->fetchStructure(1023100200300, '2F65A4');
        $this->em->clear();

        $location4 = $this->repoFactory->getEsiLocationRepository()->find(1023100200300);
        $this->assertSame(10, $location4?->getErrorCount());
        $this->assertSame($updated3->getTimestamp(), $location4->getLastUpdate()?->getTimestamp()); // not updated
    }

    public function testFetchStructure_Success(): void
    {
        $this->testHelper->emptyDb();

        $this->client->setResponse(new Response(200, [], '{
            "name": "V-3YG7 VI - The Capital",
            "owner_id": 109299958,
            "solar_system_id": 30000142
        }'));

        $location = $this->esiData->fetchStructure(1023100200300, 'access-token');

        $this->assertSame(1023100200300, $location->getId());
        $this->assertSame('V-3YG7 VI - The Capital', $location->getName());
        $this->assertSame(EsiLocation::CATEGORY_STRUCTURE, $location->getCategory());
        $this->assertSame(109299958, $location->getOwnerId());
        $this->assertSame(30000142, $location->getSystemId());
        $this->assertLessThanOrEqual(time(), $location->getLastUpdate()?->getTimestamp());
        $this->assertSame(0, $location->getErrorCount());

        $this->em->clear();
        $locationDb = $this->repoFactory->getEsiLocationRepository()->find(1023100200300);
        $this->assertSame(1023100200300, $locationDb?->getId());
        $this->assertSame('V-3YG7 VI - The Capital', $locationDb->getName());
        $this->assertSame(EsiLocation::CATEGORY_STRUCTURE, $locationDb->getCategory());
        $this->assertSame(109299958, $locationDb->getOwnerId());
        $this->assertSame(30000142, $locationDb->getSystemId());
        $this->assertLessThanOrEqual(time(), $locationDb->getLastUpdate()?->getTimestamp());
        $this->assertSame(0, $locationDb->getErrorCount());
    }

    public function testFetchStructure_AlreadyUpdated(): void
    {
        $this->testHelper->emptyDb();

        $this->client->setResponse(
            new Response(200, [], '{ "name": "Name Update 1" }'),
            new Response(200, [], '{ "name": "Name Update 2" }'),
        );

        $location1 = $this->esiData->fetchStructure(1023100200300, 'access-token');
        $this->assertLessThanOrEqual(time(), $location1->getLastUpdate()?->getTimestamp());
        $this->assertSame('Name Update 1', $location1->getName());

        $location2 = $this->esiData->fetchStructure(1023100200300, 'access-token');
        $this->assertSame(
            $location1->getLastUpdate()?->getTimestamp(),
            $location2->getLastUpdate()?->getTimestamp(),
        );
        $this->assertSame('Name Update 1', $location1->getName());
    }

    public function testFetchStructure_AuthError(): void
    {
        $this->testHelper->emptyDb();

        $this->client->setResponse(new Response(403));

        $location = $this->esiData->fetchStructure(1023100200300, 'access-token', true, false);

        $this->em->clear();
        $this->assertNull($this->repoFactory->getEsiLocationRepository()->find(1023100200300));

        $this->assertSame(1023100200300, $location->getId());
        $this->assertSame('', $location->getName());
        $this->assertSame(EsiLocation::CATEGORY_STRUCTURE, $location->getCategory());
        $this->assertNull($location->getOwnerId());
        $this->assertNull($location->getSystemId());
        $this->assertSame(1, $location->getErrorCount());
    }

    public function testFetchCorporationMembersNoToken(): void
    {
        $this->assertSame([], $this->esiData->fetchCorporationMembers(100200300, ''));
    }

    public function testFetchCorporationMembersEsiError(): void
    {
        $this->client->setMiddleware(function () {
            throw new RuntimeException("", 520);
        });
        $this->client->setResponse(new Response(200, [], '[100, 200]'));

        $this->assertSame([], $this->esiData->fetchCorporationMembers(100200300, 'access-token'));
    }

    public function testVerifyRoles_NoRoleToVerify(): void
    {
        $this->assertTrue($this->esiData->verifyRoles([], 100, 'access-token'));
    }

    public function testVerifyRoles_Exception(): void
    {
        $this->client->setResponse(new Response(500));
        $this->assertFalse($this->esiData->verifyRoles(['Auditor'], 100, 'access-token'));
        $this->assertStringStartsWith('Error JSON decoding server response', $this->log->getMessages()[0]);
    }

    public function testVerifyRoles_CharacterNotFound(): void
    {
        $this->client->setResponse(new Response(404, [], ''));
        $this->assertFalse($this->esiData->verifyRoles(['Accountant'], 100, 'access-token'));
    }

    public function testVerifyRoles_NotDirector(): void
    {
        $this->client->setResponse(new Response(200, [], '{"roles": ["Auditor", "Accountant"]}'));
        $this->assertFalse($this->esiData->verifyRoles(['Accountant', 'Director'], 100, 'access-token'));
    }

    public function testVerifyRoles_OK(): void
    {
        $this->client->setResponse(
            new Response(200, [], '{"roles": ["Director", "Auditor", "Accountant"]}'),
            new Response(200, [], '{"roles": ["Director", "Auditor", "Accountant"]}'),
        );
        $this->assertTrue($this->esiData->verifyRoles(['Accountant', 'Director'], 100, 'access-token'));
        $this->assertTrue($this->esiData->verifyRoles(['Auditor'], 100, 'access-token'));
    }

    public function testFetchCorporationMembers(): void
    {
        $this->client->setResponse(new Response(200, [], '[100, 200]'));

        $this->assertSame([100, 200], $this->esiData->fetchCorporationMembers(100200300, 'access-token'));
    }

    public function testGetCorporationEntity(): void
    {
        $this->testHelper->emptyDb();

        $result = $this->esiData->getCorporationEntity(100);
        $this->assertSame(100, $result->getId());

        $this->em->clear();

        $corp = $this->repoFactory->getCorporationRepository()->find(100);
        $this->assertInstanceOf(Corporation::class, $corp);
    }

    private function createESIData(): EsiData
    {
        $eveMailToken = new EveMailToken(
            $this->repoFactory,
            $this->om,
            Helper::getAuthenticationProvider($this->client),
            $this->log,
        );
        return new EsiData(
            $this->log,
            new EveApiFactory(new HttpClientFactory($this->client), $this->config, $eveMailToken),
            $this->om,
            $this->repoFactory,
            new Character($this->om, $this->repoFactory),
        );
    }
}
