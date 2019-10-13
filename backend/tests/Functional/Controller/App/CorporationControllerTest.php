<?php declare(strict_types=1);

namespace Tests\Functional\Controller\App;

use Neucore\Entity\Character;
use Neucore\Entity\CorporationMember;
use Neucore\Entity\Player;
use Neucore\Entity\Role;
use Neucore\Factory\RepositoryFactory;
use Tests\Functional\WebTestCase;
use Tests\Helper;
use Neucore\Entity\Corporation;

class CorporationControllerTest extends WebTestCase
{
    /**
     * @var Helper
     */
    private $helper;

    /**
     * @var RepositoryFactory
     */
    private $repoFactory;

    protected function setUp(): void
    {
        $this->helper = new Helper();
        $this->repoFactory = new RepositoryFactory($this->helper->getEm());
    }

    public function testMemberTrackingV1403()
    {
        $response1 = $this->runApp('GET', '/api/app/v1/corporation/10/member-tracking');
        $this->assertEquals(403, $response1->getStatusCode());

        $this->helper->emptyDb();
        $appId = $this->helper->addApp('A1', 's1', [Role::APP])->getId();
        $headers = ['Authorization' => 'Bearer '.base64_encode($appId.':s1')];

        $response2 = $this->runApp('GET', '/api/app/v1/corporation/10/member-tracking', null, $headers);
        $this->assertEquals(403, $response2->getStatusCode());
    }

    /**
     * @throws \Exception
     */
    public function testMemberTrackingV1200()
    {
        $this->helper->emptyDb();
        $appId = $this->helper->addApp('A1', 's1', [Role::APP, Role::APP_TRACKING])->getId();
        $corp = (new Corporation())->setId(10)->setTicker('t1')->setName('corp 1');
        $player = (new Player())->setName('p');
        $char = (new Character())->setId(1)->setName('c')->setPlayer($player);
        $member1 = (new CorporationMember())->setId(110)->setName('m1')->setCorporation($corp)
            ->setLogonDate(new \DateTime('now -5 days'));
        $member2 = (new CorporationMember())->setId(111)->setName('m2')->setCorporation($corp)
            ->setLogonDate(new \DateTime('now -10 days'))->setCharacter($char);
        $member3 = (new CorporationMember())->setId(112)->setName('m3')->setCorporation($corp)
            ->setLogonDate(new \DateTime('now -15 days'));
        $this->helper->getEm()->persist($player);
        $this->helper->getEm()->persist($char);
        $this->helper->getEm()->persist($corp);
        $this->helper->getEm()->persist($member1);
        $this->helper->getEm()->persist($member2);
        $this->helper->getEm()->persist($member3);
        $this->helper->getEm()->flush();

        $headers = ['Authorization' => 'Bearer '.base64_encode($appId.':s1')];

        $params = '?inactive=7&active=12&account=true';
        $response1 = $this->runApp('GET', '/api/app/v1/corporation/11/member-tracking'.$params, null, $headers);
        $this->assertEquals(200, $response1->getStatusCode());
        $this->assertSame([], $this->parseJsonBody($response1));

        $response2 = $this->runApp('GET', '/api/app/v1/corporation/10/member-tracking'.$params, null, $headers);
        $this->assertEquals(200, $response2->getStatusCode());
        $result2 = $this->parseJsonBody($response2);
        $this->assertSame(1, count($result2));
        $this->assertSame(111, $result2[0]['id']);
        $this->assertSame('m2', $result2[0]['name']);
        $this->assertSame(null, $result2[0]['location']);
        $this->assertSame(null, $result2[0]['logoffDate']);
        $this->assertStringStartsWith((new \DateTime('now -10 days'))->format('Y-m-d'), $result2[0]['logonDate']);
        $this->assertSame(null, $result2[0]['shipType']);
        $this->assertSame(null, $result2[0]['startDate']);
    }
}
