<?php declare(strict_types=1);

namespace Tests\Unit\Core\Service;

use Brave\Core\Entity\Character;
use Brave\Core\Entity\Corporation;
use Brave\Core\Entity\CorporationMember;
use Brave\Core\Entity\SystemVariable;
use Brave\Core\Factory\EsiApiFactory;
use Brave\Core\Factory\RepositoryFactory;
use Brave\Core\Service\EsiApi;
use Brave\Core\Service\EsiData;
use Brave\Core\Service\MemberTracking;
use Brave\Core\Service\OAuthToken;
use Brave\Core\Service\ObjectManager;
use Brave\Sso\Basics\EveAuthentication;
use GuzzleHttp\Psr7\Response;
use League\OAuth2\Client\Token\AccessToken;
use Swagger\Client\Eve\Model\GetCorporationsCorporationIdMembertracking200Ok;
use Tests\Client;
use Tests\Helper;
use Tests\Logger;
use Tests\OAuthProvider;

class MemberTrackingTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var \Doctrine\ORM\EntityManagerInterface
     */
    private $em;

    /**
     * @var Logger|\Psr\Log\LoggerInterface
     */
    private $logger;

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

    public function setUp()
    {
        $helper = new Helper();
        $helper->emptyDb();
        $this->em = $helper->getEm();
        $this->logger = new Logger('test');
        $this->client = new Client();
        $objectManager = new ObjectManager($this->em, $this->logger);
        $this->repositoryFactory = new RepositoryFactory($this->em);
        $esiApiFactory = (new EsiApiFactory())->setClient($this->client);
        $this->memberTracking = new MemberTracking(
            $this->logger,
            $esiApiFactory,
            $this->repositoryFactory,
            $objectManager,
            new EsiData(new EsiApi($this->logger, $esiApiFactory), $objectManager, $this->repositoryFactory),
            new OAuthToken(new OAuthProvider($this->client), $objectManager, $this->logger)
        );
    }

    public function testVerifyAndStoreDirectorCharError()
    {
        $this->client->setResponse(new Response(404)); // getCharactersCharacterId

        $eveAuth = new EveAuthentication(100, 'cname', 'coh', new AccessToken(['access_token' => 'at']));
        $this->assertFalse($this->memberTracking->verifyAndStoreDirector($eveAuth));
    }

    public function testVerifyAndStoreDirectorRoleError()
    {
        $this->client->setResponse(
            new Response(200, [], '{"corporation_id": 10}'), // getCharactersCharacterId
            new Response(200, [], '{"roles": []}') // getCharactersCharacterIdRoles
        );

        $eveAuth = new EveAuthentication(100, 'cname', 'coh', new AccessToken(['access_token' => 'at']));
        $this->assertFalse($this->memberTracking->verifyAndStoreDirector($eveAuth));
    }

    public function testVerifyAndStoreDirectorCorpError()
    {
        $this->client->setResponse(
            new Response(200, [], '{"corporation_id": 10}'), // getCharactersCharacterId
            new Response(200, [], '{"roles": ["Director"]}'), // getCharactersCharacterIdRoles
            new Response(200, [], '') // getCorporation
        );

        $eveAuth = new EveAuthentication(100, 'cname', 'coh', new AccessToken(['access_token' => 'at']));
        $this->assertFalse($this->memberTracking->verifyAndStoreDirector($eveAuth));
    }

    public function testVerifyAndStoreDirectorSuccess()
    {
        $char = new SystemVariable(SystemVariable::DIRECTOR_CHAR . 1);
        $token = new SystemVariable(SystemVariable::DIRECTOR_TOKEN . 1);
        $this->em->persist($char);
        $this->em->persist($token);
        $this->em->flush();
        $this->client->setResponse(
            new Response(200, [], '{"corporation_id": 10}'), // getCharactersCharacterId
            new Response(200, [], '{"roles": ["Director"]}'), // getCharactersCharacterIdRoles
            new Response(200, [], '{"name": "ten", "ticker": "-10-"}') // getCorporation
        );

        $eveAuth = new EveAuthentication(100, 'cname', 'coh', new AccessToken(['access_token' => 'at']));
        $result = $this->memberTracking->verifyAndStoreDirector($eveAuth);

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
        ], \json_decode($sysVarRepo->find(SystemVariable::DIRECTOR_TOKEN . 2)->getValue(), true));
    }

    public function testRemoveDirector()
    {
        $char = new SystemVariable(SystemVariable::DIRECTOR_CHAR . 1);
        $token = new SystemVariable(SystemVariable::DIRECTOR_TOKEN . 1);
        $this->em->persist($char);
        $this->em->persist($token);
        $this->em->flush();

        $this->memberTracking->removeDirector($char);

        $sysVarRepo = $this->repositoryFactory->getSystemVariableRepository();
        $this->assertNull($sysVarRepo->find(SystemVariable::DIRECTOR_CHAR . 1));
        $this->assertNull($sysVarRepo->find(SystemVariable::DIRECTOR_TOKEN . 1));
    }

    public function testRefreshDirectorTokenNoData()
    {
        $this->assertNull($this->memberTracking->refreshDirectorToken(SystemVariable::DIRECTOR_CHAR . 1));
    }

    public function testRefreshDirectorTokenIdentityProviderException()
    {
        $char = (new SystemVariable(SystemVariable::DIRECTOR_CHAR . 1))->setValue('{"character_id": 100}');
        $token = (new SystemVariable(SystemVariable::DIRECTOR_TOKEN . 1))
            ->setValue('{"access": "at", "refresh": "rt", "expires": '.(time() - 1).'}');
        $this->em->persist($char);
        $this->em->persist($token);
        $this->em->flush();

        $this->client->setResponse(new Response(400, [], '{
            "error": "invalid_token",
            "error_description": "The refresh token is expired."
        }'));

        $this->assertNull($this->memberTracking->refreshDirectorToken(SystemVariable::DIRECTOR_CHAR . 1));
    }

    public function testRefreshDirectorTokenSuccess()
    {
        $char = (new SystemVariable(SystemVariable::DIRECTOR_CHAR . 1))->setValue('{"character_id": 100}');
        $token = (new SystemVariable(SystemVariable::DIRECTOR_TOKEN . 1))
            ->setValue('{"access": "at", "refresh": "rt", "expires": '.(time() + 60*20).'}');
        $this->em->persist($char);
        $this->em->persist($token);
        $this->em->flush();

        $result = $this->memberTracking->refreshDirectorToken(SystemVariable::DIRECTOR_CHAR . 1);
        $this->assertInstanceOf(AccessToken::class, $result);
    }

    public function testVerifyDirectorRoleCharacterNotFound()
    {
        $this->client->setResponse(new Response(404, [], ''));
        $this->assertFalse($this->memberTracking->verifyDirectorRole(100, 'access-token'));
    }

    public function testVerifyDirectorRoleNotDirector()
    {
        $this->client->setResponse(new Response(200, [], '{"roles": []}'));
        $this->assertFalse($this->memberTracking->verifyDirectorRole(100, 'access-token'));
    }

    public function testVerifyDirectorRoleOK()
    {
        $this->client->setResponse(new Response(200, [], '{"roles": ["Director"]}'));
        $this->assertTrue($this->memberTracking->verifyDirectorRole(100, 'access-token'));
    }

    public function testFetchDataCorpNotFound()
    {
        $this->client->setResponse(new Response(404, [], ''));
        $this->assertNull($this->memberTracking->fetchData('access-token', 10));
    }

    public function testFetchDataOK()
    {
        $this->client->setResponse(new Response(200, [], '[{"character_id": 100}, {"character_id": 101}]'));

        $actual = $this->memberTracking->fetchData('access-token', 10);

        $this->assertSame(2, count($actual));
        $this->assertSame(100, $actual[0]->getCharacterId());
        $this->assertSame(101, $actual[1]->getCharacterId());
    }

    /**
     * @throws \Exception
     */
    public function testProcessData()
    {
        $corp = (new Corporation())->setId(10)->setName('corp')->setTicker('C');
        $char = (new Character())->setId(100)->setName('char 1');
        $member = (new CorporationMember())->setId(100)->setName('char 1')->setCharacter($char);
        $this->em->persist($corp);
        $this->em->persist($char);
        $this->em->persist($member);
        $this->em->flush();
        $data = [
            new GetCorporationsCorporationIdMembertracking200Ok([
                'character_id' => 100,
                'location_id' => 200,
                'logoff_date' => new \DateTime('2018-12-25 19:45:10'),
                'logon_date' => new \DateTime('2018-12-25 19:45:11'),
                'ship_type_id' => 300,
                'start_date' => new \DateTime('2018-12-25 19:45:12'),
            ]),
            new GetCorporationsCorporationIdMembertracking200Ok(['character_id' => 101]),
        ];
        $this->client->setResponse(new Response(200, [], '[
            {"category": "character", "id": "100", "name": "char 1"},
            {"category": "character", "id": "101", "name": "char 2"}
        ]')); // postUniverseNames

        $this->assertTrue($this->memberTracking->processData($corp, $data));

        $data = $this->repositoryFactory->getCorporationMemberRepository()->findBy([]);
        $this->assertSame(2, count($data));
        $this->assertSame(100, $data[0]->getId());
        $this->assertSame(100, $data[0]->getCharacter()->getId());
        $this->assertSame(200, $data[0]->getLocationId());
        $this->assertSame('2018-12-25T19:45:10+00:00', $data[0]->getLogoffDate()->format(\DATE_ATOM));
        $this->assertSame('2018-12-25T19:45:11+00:00', $data[0]->getLogonDate()->format(\DATE_ATOM));
        $this->assertSame(300, $data[0]->getShipTypeId());
        $this->assertSame('2018-12-25T19:45:12+00:00', $data[0]->getStartDate()->format(\DATE_ATOM));
        $this->assertSame(101, $data[1]->getId());
        $this->assertNull($data[1]->getCharacter());
    }
}
