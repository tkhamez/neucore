<?php declare(strict_types=1);

namespace Tests\Unit\Core\Service;

use Brave\Core\Entity\Alliance;
use Brave\Core\Factory\EsiApiFactory;
use Brave\Core\Repository\AllianceRepository;
use Brave\Core\Repository\CharacterRepository;
use Brave\Core\Entity\Corporation;
use Brave\Core\Repository\CorporationRepository;
use Brave\Core\Factory\RepositoryFactory;
use Brave\Core\Service\EsiData;
use Brave\Core\Service\EsiApi;
use Brave\Core\Service\ObjectManager;
use GuzzleHttp\Psr7\Response;
use Monolog\Logger;
use Monolog\Handler\TestHandler;
use Tests\Helper;
use Tests\TestClient;
use Tests\WriteErrorListener;

class EsiDataTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var Helper
     */
    private $testHelper;

    /**
     * @var \Doctrine\ORM\EntityManagerInterface
     */
    private $em;

    /**
     * @var TestClient
     */
    private $client;

    /**
     * @var AllianceRepository
     */
    private $alliRepo;

    /**
     * @var CorporationRepository
     */
    private $corpRepo;

    /**
     * @var CharacterRepository
     */
    private $charRepo;

    /**
     * @var EsiData
     */
    private $cs;

    /**
     * @var EsiData
     */
    private $csError;

    public function setUp()
    {
        $this->testHelper = new Helper();
        $this->em = $this->testHelper->getEm();

        $log = new Logger('Test');
        $log->pushHandler(new TestHandler());

        $this->client = new TestClient();
        $esi = new EsiApi($log, (new EsiApiFactory())->setClient($this->client));

        $repositoryFactory = new RepositoryFactory($this->em);
        $this->alliRepo = $repositoryFactory->getAllianceRepository();
        $this->corpRepo = $repositoryFactory->getCorporationRepository();
        $this->charRepo = $repositoryFactory->getCharacterRepository();

        $this->cs = new EsiData($esi, new ObjectManager($this->em, $log), $repositoryFactory);

        // a second EsiData instance with another entity manager that throws an exception on flush.
        $em = (new Helper())->getEm(true);
        $em->getEventManager()->addEventListener(\Doctrine\ORM\Events::onFlush, new WriteErrorListener());
        $this->csError = new EsiData($esi, new ObjectManager($em, $log), $repositoryFactory);
    }

    public function testGetEsiApi()
    {
        $this->assertInstanceOf(EsiApi::class, $this->cs->getEsiApi());
    }

    public function testFetchCharacterWithCorporationAndAllianceCharInvalid()
    {
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
    }

    public function testFetchCharacterNoFlush()
    {
        $this->testHelper->emptyDb();
        $char = $this->testHelper->addCharacterMain('newChar', 123, []);
        $char->setLastUpdate(new \DateTime('2018-03-26 17:24:30'));
        $this->em->flush();

        $this->client->setResponse(new Response(200, [], '{
            "name": "new corp",
            "corporation_id": 234
        }'));

        $char = $this->cs->fetchCharacter(123, false);
        $this->assertSame(123, $char->getId());
        $this->assertSame('new corp', $char->getName());
        $this->assertSame(234, $char->getCorporation()->getId());
        $this->assertNull($char->getCorporation()->getName());

        $this->em->clear();
        $charDb = $this->charRepo->find(123);
        $this->assertNull($charDb->getCorporation());
    }

    public function testFetchCharacter()
    {
        $this->testHelper->emptyDb();
        $char = $this->testHelper->addCharacterMain('newChar', 123, []);
        $char->setLastUpdate(new \DateTime('2018-03-26 17:24:30'));
        $this->em->flush();

        $this->client->setResponse(new Response(200, [], '{
            "name": "new corp",
            "corporation_id": 234
        }'));

        $char = $this->cs->fetchCharacter(123);
        $this->assertSame(123, $char->getId());
        $this->assertSame('new corp', $char->getName());
        $this->assertSame(234, $char->getCorporation()->getId());
        $this->assertNull($char->getCorporation()->getName());

        $this->em->clear();
        $charDb = $this->charRepo->find(123);
        $this->assertSame(234, $charDb->getCorporation()->getId());
        $this->assertSame('UTC', $charDb->getLastUpdate()->getTimezone()->getName());
        $this->assertGreaterThan('2018-03-26 17:24:30', $charDb->getLastUpdate()->format('Y-m-d H:i:s'));
    }

    public function testFetchCorporationInvalidId()
    {
        $corp = $this->cs->fetchCorporation(-1);
        $this->assertNull($corp);
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
        $corpDb = $this->corpRepo->find(234);
        $this->assertNull($corpDb);
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
        $corpDb = $this->corpRepo->find(234);
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
        $corporation = $this->corpRepo->find(200);
        $this->assertNull($corporation->getAlliance());
        $alliance = $this->alliRepo->find(100);
        $this->assertSame([], $alliance->getCorporations());
    }

    public function testFetchAllianceInvalidId()
    {
        $alli = $this->cs->fetchAlliance(-1);
        $this->assertNull($alli);
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
        $alliDb = $this->alliRepo->find(345);
        $this->assertNull($alliDb);
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
        $alliDb = $this->alliRepo->find(345);
        $this->assertSame(345, $alliDb->getId());
    }

    public function testFetchAllianceCreateFlushError()
    {
        $this->testHelper->emptyDb();

        $this->client->setResponse(new Response(200, [], '{
            "name": "The A.",
            "ticker": "-A-"
        }'));

        $alli = $this->csError->fetchAlliance(345, true);
        $this->assertNull($alli);
    }
}
