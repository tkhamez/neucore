<?php declare(strict_types=1);

namespace Tests\Unit\Core\Service;

use Brave\Core\Entity\Character;
use Brave\Core\Entity\Role;
use Brave\Core\Factory\RepositoryFactory;
use Brave\Core\Service\OAuthToken;
use Brave\Core\Service\ObjectManager;
use GuzzleHttp\Psr7\Response;
use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use League\OAuth2\Client\Token\AccessToken;
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

    public function testRefreshAccessTokenNewTokenException()
    {
        $this->client->setResponse(new Response(500));

        $token = new AccessToken([
            'access_token' => 'at',
            'refresh_token' => '',
            'expires' => 1349067601 // 2012-10-01 + 1
        ]);

        $tokenResult = $this->es->refreshAccessToken($token);

        $this->assertSame($token, $tokenResult);
        $this->assertStringStartsWith(
            'An OAuth server error ',
            $this->log->getHandler()->getRecords()[0]['message']
        );
    }

    public function testRefreshAccessTokenNoRefresh()
    {
        $token = new AccessToken([
            'access_token' => 'old-token',
            'refresh_token' => '',
            'expires' => time() + 10000
        ]);

        $this->assertSame('old-token', $this->es->refreshAccessToken($token)->getToken());
    }

    public function testRefreshAccessTokenNewToken()
    {
        $this->client->setResponse(new Response(200, [], '{
            "access_token": "new-token",
            "refresh_token": "",
            "expires": 1519933900}' // 03/01/2018 @ 7:51pm (UTC)
        ));

        $token = new AccessToken([
            'access_token' => 'old-token',
            'refresh_token' => '',
            'expires' => 1519933545 // 03/01/2018 @ 7:45pm (UTC)
        ]);

        $tokenResult = $this->es->refreshAccessToken($token);

        $this->assertNotSame($token, $tokenResult);
        $this->assertSame('new-token', $tokenResult->getToken());
    }

    public function testGetTokenNoExistingTokenException()
    {
        $token = $this->es->getToken(new Character());

        $this->assertSame('', $token);

        // from Exception in createAccessTokenFromCharacter()
        $this->assertSame(
            'Required option not passed: "access_token"',
            $this->log->getHandler()->getRecords()[0]['message']
        );
    }

    public function testGetTokenNewTokenUpdateDatabaseOk()
    {
        $char = new Character();
        $char->setId(123);
        $char->setName('n');
        $char->setMain(true);
        $char->setCharacterOwnerHash('coh');
        $char->setAccessToken('old-token');
        $char->setExpires(1519933545); // 03/01/2018 @ 7:45pm (UTC)
        $this->em->persist($char);
        $this->em->flush();

        $this->client->setResponse(new Response(200, [], '{
            "access_token": "new-token",
            "refresh_token": "",
            "expires": 1519933900}' // 03/01/2018 @ 7:51pm (UTC)
        ));

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
        $c = new Character();
        $c->setId(123);
        $c->setName('n');
        $c->setMain(true);
        $c->setCharacterOwnerHash('coh');
        $c->setAccessToken('old-token');
        $c->setExpires(1519933545); // 03/01/2018 @ 7:45pm (UTC)

        $this->client->setResponse(new Response(200, [], '{
            "access_token": "new-token",
            "refresh_token": "",
            "expires": 1519933900}' // 03/01/2018 @ 7:51pm (UTC)
        ));

        $token = $this->esError->getToken($c);

        $this->assertSame('', $token);
    }

    public function testVerify()
    {
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
}
