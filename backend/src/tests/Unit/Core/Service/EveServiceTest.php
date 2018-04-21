<?php declare(strict_types=1);

namespace Tests\Unit\Core\Service;

use Brave\Core\Entity\Character;
use Brave\Core\Entity\CharacterRepository;
use Brave\Core\Roles;
use Brave\Core\Service\EveService;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use League\OAuth2\Client\Provider\GenericProvider;
use League\OAuth2\Client\Token\AccessToken;
use Tests\Helper;

class EveServiceTest extends \PHPUnit\Framework\TestCase
{
    private $em;

    private $log;

    private $oauth;

    private $es;

    public static function setUpBeforeClass()
    {
        $h = new Helper();
        $h->emptyDb();
        $h->addRoles([Roles::USER]);
    }

    public function setUp()
    {
        $this->em = (new Helper())->getEm();

        $this->log = new Logger('Test');
        $this->log->pushHandler(new TestHandler());

        $this->oauth = $this->createMock(GenericProvider::class);
        $this->es = new EveService($this->oauth, $this->em, $this->log);
    }

    public function testGetTokenNoUser()
    {
        $this->assertSame("", $this->es->getToken());

        $this->assertSame(
            'EveService::getToken: Character not set.',
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
        // don't save to DB to be able to test that getToken() will save it.

        $this->es->setCharacter($c);

        $this->es->getToken();
        $this->em->flush();

        $this->assertSame('new-token', $this->es->getToken());
        $this->assertSame('new-token', $c->getAccessToken());

        $charFromDB = (new CharacterRepository($this->em))->find(123);
        $this->assertSame('new-token', $charFromDB->getAccessToken());

        $this->assertSame(0, count($this->log->getHandlers()[0]->getRecords()));
    }

    public function testGetTokenNoRefresh()
    {
        $c = new Character();
        $c->setAccessToken('old-token');
        $c->setExpires(time() + 10000);
        $this->es->setCharacter($c);

        $this->assertSame('old-token', $this->es->getToken());
    }

    public function testGetConfiguration()
    {
        $c = new Character();
        $c->setAccessToken('old-token');
        $c->setExpires(time() + 10000);
        $this->es->setCharacter($c);

        $conf = $this->es->getConfiguration();

        $this->assertSame($this->es->getToken(), $conf->getAccessToken());
    }
}
