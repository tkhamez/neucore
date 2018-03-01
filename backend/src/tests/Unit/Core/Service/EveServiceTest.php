<?php
namespace Tests\Unit\Core\Service;

use Brave\Core\Entity\CharacterRepository;
use Brave\Core\Entity\RoleRepository;
use Brave\Core\Service\EveService;
use Brave\Core\Service\UserAuthService;
use Brave\Slim\Session\SessionData;
use Monolog\Logger;
use League\OAuth2\Client\Provider\GenericProvider;
use Tests\Helper;
use Monolog\Handler\TestHandler;
use League\OAuth2\Client\Token\AccessToken;

class EveServiceTest extends \PHPUnit\Framework\TestCase
{

    private $log;

    private $uas;

    private $oauth;

    private $es;

    public static function setUpBeforeClass()
    {
        $h = new Helper();
        $h->emptyDb();
        $h->addRoles(['user']);
    }

    public function setUp()
    {
        $h = new Helper();
        $em = $h->getEm();

        #$h->resetSessionData();
        $_SESSION = [];
        $ses = new SessionData();
        $ses->setReadOnly(false);

        $cr = new CharacterRepository($em);
        $rr = new RoleRepository($em);

        $this->log = new Logger('Test');
        $this->log->pushHandler(new TestHandler());

        $this->uas = new UserAuthService($ses, $cr, $rr, $em, $this->log);
        $this->oauth = $this->createMock(GenericProvider::class);
        $this->es = new EveService($this->oauth, $this->uas, $this->log);
    }

    public function testGetTokenNoUser()
    {
        $this->assertSame("", $this->es->getToken());
    }

    public function testGetTokenExistingException()
    {
        $this->uas->authenticate(111, 'one', 'coh', '');
        $this->es->getToken();

        $this->assertSame(
            'Required option not passed: "access_token"',
            $this->log->getHandlers()[0]->getRecords()[0]['message']
        );
    }

    public function testGetTokenNewException()
    {
        $this->oauth->method('getAccessToken')->will($this->throwException(new \Exception('test e')));

        $this->uas->authenticate(111, 'one', 'coh', 'at', 1349067601); // 2012-10-01 + 1
        $this->es->getToken();

        $this->assertSame('test e', $this->log->getHandlers()[0]->getRecords()[0]['message']);
    }

    public function testGetTokenWithRefresh()
    {
        $this->oauth->method('getAccessToken')->willReturn(new AccessToken([
            'access_token' => 'new-token',
            'refresh_token' => '',
            'expires' => 1519933900, // 03/01/2018 @ 7:51pm (UTC)
        ]));

        $this->uas->authenticate(111, 'one', 'coh', 'old-token', 1519933545); // 03/01/2018 @ 7:45pm (UTC)
        $this->es->getToken();

        $this->assertSame('new-token', $this->es->getToken());
    }

    public function testGetTokenNoRefresh()
    {
        $this->uas->authenticate(111, 'one', 'coh', 'old-token', time() + 10000);
        $this->assertSame('old-token', $this->es->getToken());
    }

    public function testGetConfiguration()
    {
        $this->uas->authenticate(111, 'one', 'coh', 'old-token', time() + 10000);

        $conf = $this->es->getConfiguration();

        $this->assertSame($this->es->getToken(), $conf->getAccessToken());
    }
}
