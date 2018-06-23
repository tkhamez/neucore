<?php declare(strict_types=1);

namespace Tests\Unit\Core\Service;

use Brave\Core\Entity\Character;
use Brave\Core\Entity\CharacterRepository;
use Brave\Core\Roles;
use Brave\Core\Service\OAuthToken;
use League\OAuth2\Client\Provider\GenericProvider;
use League\OAuth2\Client\Provider\GenericResourceOwner;
use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use League\OAuth2\Client\Token\AccessToken;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Tests\Helper;
use Tests\WriteErrorListener;

class OAuthTokenTest extends \PHPUnit\Framework\TestCase
{
    private $em;

    private $log;

    private $oauth;

    private $es;

    private $esError;

    public function setUp()
    {
        $h = new Helper();
        $h->emptyDb();
        $h->addRoles([Roles::USER]);

        $this->em = (new Helper())->getEm();

        $this->log = new Logger('Test');
        $this->log->pushHandler(new TestHandler());

        $this->oauth = $this->createMock(GenericProvider::class);
        $this->es = new OAuthToken($this->oauth, $this->em, $this->log);

        // a second OAuthToken instance with another EntityManager that throws an exception on flush.
        $em = (new Helper())->getEm(true);
        $em->getEventManager()->addEventListener(\Doctrine\ORM\Events::onFlush, new WriteErrorListener());
        $this->esError = new OAuthToken($this->oauth, $em, $this->log);
    }

    public function testSetCharacter()
    {
        $this->assertAttributeSame(null, 'character', $this->es);

        $c = new Character();
        $this->es->setCharacter($c);
        $this->assertAttributeSame($c, 'character', $this->es);
    }

    public function testGetTokenNoUser()
    {
        $this->assertSame("", $this->es->getToken());

        $this->assertSame(
            'OAuthToken::getToken: Character not set.',
            $this->log->getHandlers()[0]->getRecords()[0]['message']
        );
    }

    public function testGetTokenNoExistingTokenException()
    {
        $this->es->setCharacter(new Character());
        $this->es->getToken();

        $this->assertSame(
            'Required option not passed: "access_token"',
            $this->log->getHandlers()[0]->getRecords()[0]['message']
        );
    }

    public function testGetTokenNewTokenException()
    {
        $this->oauth->method('getAccessToken')->will($this->throwException(new \Exception('test e')));

        $c = new Character();
        $c->setAccessToken('at');
        $c->setExpires(1349067601); // 2012-10-01 + 1
        $this->es->setCharacter($c);

        $this->es->getToken();

        $this->assertSame('test e', $this->log->getHandlers()[0]->getRecords()[0]['message']);
    }

    public function testGetTokenNewTokenUpdateDatabase()
    {
        $char = $this->setUpData();

        $this->es->setCharacter($char);
        $token = $this->es->getToken();

        $this->assertSame('new-token', $token);
        $this->assertSame('new-token', $char->getAccessToken());

        $this->em->clear();
        $charFromDB = (new CharacterRepository($this->em))->find(123);
        $this->assertSame('new-token', $charFromDB->getAccessToken());

        $this->assertSame(0, count($this->log->getHandlers()[0]->getRecords()));
    }

    public function testGetTokenNewTokenUpdateDatabaseError()
    {
        $char = $this->setUpData();

        $this->esError->setCharacter($char);
        $token = $this->esError->getToken();

        $this->assertSame('', $token);
    }

    public function testGetTokenNoRefresh()
    {
        $c = new Character();
        $c->setAccessToken('old-token');
        $c->setExpires(time() + 10000);
        $this->es->setCharacter($c);

        $this->assertSame('old-token', $this->es->getToken());
    }

    public function testVerifyNoExistingToken()
    {
        $this->assertNull($this->es->verify());
    }

    public function testVerify()
    {
        // can't really test "getAccessToken()" method here,
        // but that is done above in testGetToken*()

        $this->oauth->method('getResourceOwner')->willReturn(new GenericResourceOwner([
            'CharacterID' => '123',
            'CharacterName' => 'char name',
            'ExpiresOn' => '2018-05-03T20:27:38.7999223',
            'CharacterOwnerHash' => 'coh',
        ], 'id'));

        $c = new Character();
        $c->setAccessToken('at');
        $c->setExpires(time() - 1800);
        $c->setRefreshToken('rt');

        $this->es->setCharacter($c);

        $owner = $this->es->verify();

        $this->assertInstanceOf(ResourceOwnerInterface::class, $owner);
        $this->assertSame([
            'CharacterID' => '123',
            'CharacterName' => 'char name',
            'ExpiresOn' => '2018-05-03T20:27:38.7999223',
            'CharacterOwnerHash' => 'coh',
        ], $owner->toArray());
    }

    private function setUpData(): Character
    {
        $this->oauth->method('getAccessToken')->willReturn(new AccessToken([
            'access_token' => 'new-token',
            'refresh_token' => '',
            'expires' => 1519933900, // 03/01/2018 @ 7:51pm (UTC)
        ]));

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
