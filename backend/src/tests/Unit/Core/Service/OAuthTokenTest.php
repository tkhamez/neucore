<?php declare(strict_types=1);

namespace Tests\Unit\Core\Service;

use Brave\Core\Entity\Character;
use Brave\Core\Entity\Role;
use Brave\Core\Factory\RepositoryFactory;
use Brave\Core\Service\OAuthToken;
use Brave\Core\Service\ObjectManager;
use GuzzleHttp\Psr7\Response;
use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use Tests\Helper;
use Tests\Logger;
use Tests\OAuthProvider;
use Tests\Client;
use Tests\WriteErrorListener;

class OAuthTokenTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var \Doctrine\ORM\EntityManagerInterface
     */
    private $em;

    /**
     * @var Logger
     */
    private $log;

    /**
     * @var Client
     */
    private $client;

    /**
     * @var OAuthToken
     */
    private $es;

    /**
     * @var OAuthToken
     */
    private $esError;

    public function setUp()
    {
        $h = new Helper();
        $h->emptyDb();
        $h->addRoles([Role::USER]);

        $this->em = $h->getEm();

        $this->log = new Logger('Test');

        $this->client = new Client();
        $oauth = new OAuthProvider($this->client);

        $this->es = new OAuthToken($oauth, new ObjectManager($this->em, $this->log), $this->log);

        // a second OAuthToken instance with another entity manager that throws an exception on flush.
        $em = $h->getEm(true);
        $em->getEventManager()->addEventListener(\Doctrine\ORM\Events::onFlush, new WriteErrorListener());
        $this->esError = new OAuthToken($oauth, new ObjectManager($em, $this->log), $this->log);
    }

    public function testGetTokenNoExistingTokenException()
    {
        $this->es->getToken(new Character());

        $this->assertSame(
            'Required option not passed: "access_token"',
            $this->log->getHandler()->getRecords()[0]['message']
        );
    }

    public function testGetTokenNewTokenException()
    {
        $this->client->setResponse(new Response(500));

        $c = new Character();
        $c->setAccessToken('at');
        $c->setExpires(1349067601); // 2012-10-01 + 1

        $this->es->getToken($c);

        $this->assertStringStartsWith(
            'An OAuth server error ',
            $this->log->getHandler()->getRecords()[0]['message']
        );
    }

    public function testGetTokenNewTokenUpdateDatabaseOk()
    {
        $char = $this->setUpData();

        $token = $this->es->getToken($char);

        $this->assertSame('new-token', $token);
        $this->assertSame('new-token', $char->getAccessToken());

        $this->em->clear();
        $charFromDB = (new RepositoryFactory($this->em))->getCharacterRepository()->find(123);
        $this->assertSame('new-token', $charFromDB->getAccessToken());

        $this->assertSame(0, count($this->log->getHandler()->getRecords()));
    }

    public function testGetTokenNewTokenUpdateDatabaseError()
    {
        $char = $this->setUpData();

        $token = $this->esError->getToken($char);

        $this->assertSame('', $token);
    }

    public function testGetTokenNoRefresh()
    {
        $c = new Character();
        $c->setAccessToken('old-token');
        $c->setExpires(time() + 10000);

        $this->assertSame('old-token', $this->es->getToken($c));
    }

    public function testVerify()
    {
        // can't really test "refreshAccessToken()" method here,
        // but that is done above in testGetToken*() tests

        $this->client->setResponse(
            // for refreshAccessToken()
            new Response(200, [], '{
                "access_token": "new-at",
                "refresh_token": "",
                "expires": '.(time() + 1800).'}'
            ),

            // for getResourceOwner()
            new Response(200, [], '{
                "CharacterID": "123",
                "CharacterName": "char name",
                "ExpiresOn": "2018-05-03T20:27:38.7999223",
                "CharacterOwnerHash": "coh"
            }')
        );

        $c = new Character();
        $c->setAccessToken('at');
        $c->setExpires(time() - 1800);
        $c->setRefreshToken('rt');

        $owner = $this->es->verify($c);

        $this->assertInstanceOf(ResourceOwnerInterface::class, $owner);
        $this->assertSame([
            'CharacterID' => '123',
            'CharacterName' => 'char name',
            'ExpiresOn' => '2018-05-03T20:27:38.7999223',
            'CharacterOwnerHash' => 'coh',
        ], $owner->toArray());

        // check that the new token is *not* updated on the character
        $this->assertSame('at', $c->getAccessToken());
    }

    private function setUpData(): Character
    {
        $this->client->setResponse(new Response(200, [], '{
            "access_token": "new-token",
            "refresh_token": "",
            "expires": 1519933900}' // 03/01/2018 @ 7:51pm (UTC)
        ));

        $c = new Character();
        $c->setId(123);
        $c->setName('n');
        $c->setMain(true);
        $c->setCharacterOwnerHash('coh');
        $c->setAccessToken('old-token');
        $c->setExpires(1519933545); // 03/01/2018 @ 7:45pm (UTC)

        $this->em->persist($c);
        $this->em->flush();

        return $c;
    }
}
