<?php declare(strict_types=1);

namespace Tests\Functional\Core\Api;

use Brave\Core\Entity\Role;
use Brave\Core\Entity\SystemVariable;
use Brave\Core\Factory\RepositoryFactory;
use Brave\Core\Entity\Group;
use Tests\WebTestCase;
use Tests\Helper;
use Brave\Core\Entity\Corporation;
use Brave\Core\Entity\Alliance;

class ApplicationControllerTest extends WebTestCase
{
    /**
     * @var Helper
     */
    private $helper;

    /**
     * @var RepositoryFactory
     */
    private $repoFactory;

    private $appId;

    private $group0Id;

    private $group1Id;

    private $group4Id;

    public function setUp()
    {
        $this->helper = new Helper();
        $this->repoFactory = new RepositoryFactory($this->helper->getEm());
    }

    public function testShowV1403()
    {
        $response = $this->runApp('GET', '/api/app/v1/show');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testShowV1200()
    {
        $this->helper->emptyDb();
        $aid = $this->helper->addApp('Test App', 'boring-test-secret', ['app'])->getId();

        $headers = ['Authorization' => 'Bearer '.base64_encode($aid.':boring-test-secret')];
        $response = $this->runApp('GET', '/api/app/v1/show', null, $headers);
        $this->assertEquals(200, $response->getStatusCode());

        $this->assertSame([
            'id' => $aid,
            'name' => 'Test App',
        ], $this->parseJsonBody($response));
    }

    public function testGroupsV1403()
    {
        $response = $this->runApp('GET', '/api/app/v1/groups/123');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testGroupsV1404()
    {
        $this->helper->emptyDb();
        $aid = $this->helper->addApp('A1', 's1', ['app'])->getId();

        $headers = ['Authorization' => 'Bearer '.base64_encode($aid.':s1')];
        $response = $this->runApp('GET', '/api/app/v1/groups/123', null, $headers);
        $this->assertEquals(404, $response->getStatusCode());
        $this->assertEquals('Not Found', $response->getReasonPhrase());
    }

    public function testGroupsV2404()
    {
        $this->helper->emptyDb();
        $aid = $this->helper->addApp('A1', 's1', ['app'])->getId();

        $headers = ['Authorization' => 'Bearer '.base64_encode($aid.':s1')];
        $response = $this->runApp('GET', '/api/app/v2/groups/123', null, $headers);
        $this->assertEquals(404, $response->getStatusCode());
        $this->assertEquals('Character not found.', $response->getReasonPhrase());
    }

    public function testGroupsV1200()
    {
        $this->setUpDb();

        $headers = ['Authorization' => 'Bearer '.base64_encode($this->appId.':s1')];
        $response1 = $this->runApp('GET', '/api/app/v1/groups/123', null, $headers);
        $response2 = $this->runApp('GET', '/api/app/v1/groups/456', null, $headers);

        $this->assertEquals(200, $response1->getStatusCode());
        $this->assertEquals(200, $response2->getStatusCode());

        $body1 = $this->parseJsonBody($response1);
        $body2 = $this->parseJsonBody($response2);

        $this->assertSame($body1, $body2);

        $this->assertSame([
            ['id' => $this->group1Id, 'name' => 'g1', 'visibility' => Group::VISIBILITY_PRIVATE]
        ], $body1);
    }

    public function testGroupsV1200DeactivatedInvalidTokenButActive()
    {
        // feature "deactivated accounts" is active, account has invalid token but but only for a short time

        $this->setUpDb(12);

        // activate "deactivated accounts"
        $setting = new SystemVariable(SystemVariable::GROUPS_REQUIRE_VALID_TOKEN);
        $delay = new SystemVariable(SystemVariable::ACCOUNT_DEACTIVATION_DELAY);
        $setting->setValue('1');
        $delay->setValue('24');
        $this->helper->getEm()->persist($setting);
        $this->helper->getEm()->persist($delay);
        $this->helper->getEm()->flush();

        $headers = ['Authorization' => 'Bearer '.base64_encode($this->appId.':s1')];
        $response = $this->runApp('GET', '/api/app/v1/groups/789', null, $headers);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertSame([
            ['id' => $this->group0Id, 'name' => 'g0', 'visibility' => Group::VISIBILITY_PRIVATE],
            ['id' => $this->group1Id, 'name' => 'g1', 'visibility' => Group::VISIBILITY_PRIVATE],
        ], $this->parseJsonBody($response));
    }

    public function testGroupsV1200DeactivatedInvalidToken()
    {
        $this->setUpDb(36);

        // activate "deactivated accounts"
        $setting = new SystemVariable(SystemVariable::GROUPS_REQUIRE_VALID_TOKEN);
        $setting->setValue('1');
        $this->helper->getEm()->persist($setting);
        $this->helper->getEm()->flush();

        $headers = ['Authorization' => 'Bearer '.base64_encode($this->appId.':s1')];
        $response = $this->runApp('GET', '/api/app/v1/groups/789', null, $headers);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertSame([], $this->parseJsonBody($response));
    }

    public function testGroupsV1200DeactivatedValidToken()
    {
        $this->setUpDb();

        // activate "deactivated accounts"
        $setting = new SystemVariable(SystemVariable::GROUPS_REQUIRE_VALID_TOKEN);
        $setting->setValue('1');
        $this->helper->getEm()->persist($setting);
        $this->helper->getEm()->flush();

        $headers = ['Authorization' => 'Bearer '.base64_encode($this->appId.':s1')];
        $response = $this->runApp('GET', '/api/app/v1/groups/123', null, $headers);

        $this->assertEquals(200, $response->getStatusCode());

        $body = $this->parseJsonBody($response);

        $this->assertSame([
            ['id' => $this->group1Id, 'name' => 'g1', 'visibility' => Group::VISIBILITY_PRIVATE]
        ], $body);
    }

    public function testGroupsBulkV1403()
    {
        $response = $this->runApp('POST', '/api/app/v1/groups');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testGroupsBulkV1400()
    {
        $this->setUpDb();

        $headers = ['Authorization' => 'Bearer '.base64_encode($this->appId.':s1')];
        $response = $this->runApp('POST', '/api/app/v1/groups', new \stdClass(), $headers);

        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testGroupsBulkV1200()
    {
        $this->setUpDb();

        $headers = ['Authorization' => 'Bearer '.base64_encode($this->appId.':s1')];
        $response = $this->runApp('POST', '/api/app/v1/groups', [123, 789, 789, 12], $headers);

        $this->assertEquals(200, $response->getStatusCode());

        $body = $this->parseJsonBody($response);

        $expected = [[
            'character' => ['id' => 123, 'name' => 'C1', 'corporation' => null],
            'groups' => [
                ['id' => $this->group1Id, 'name' => 'g1', 'visibility' => Group::VISIBILITY_PRIVATE]
            ],
        ], [
            'character' => ['id' => 789, 'name' => 'C3', 'corporation' => [
                'id' => 500, 'name' => 'five', 'ticker' => '-5-', 'alliance' => [
                    'id' => 100, 'name' => 'one', 'ticker' => '-1-'
                ]
            ]],
            'groups' => [
                ['id' => $this->group0Id, 'name' => 'g0', 'visibility' => Group::VISIBILITY_PRIVATE],
                ['id' => $this->group1Id, 'name' => 'g1', 'visibility' => Group::VISIBILITY_PRIVATE],
            ],
        ]];
        $this->assertSame($expected, $body);
    }

    public function testGroupsBulkV1200Deactivated()
    {
        $this->setUpDb(48);

        // activate "deactivated accounts"
        $active = new SystemVariable(SystemVariable::GROUPS_REQUIRE_VALID_TOKEN);
        $active->setValue('1');
        $this->helper->getEm()->persist($active);
        $this->helper->getEm()->flush();

        $headers = ['Authorization' => 'Bearer '.base64_encode($this->appId.':s1')];
        $response = $this->runApp('POST', '/api/app/v1/groups', [123, 789], $headers);

        $this->assertEquals(200, $response->getStatusCode());

        $body = $this->parseJsonBody($response);

        $expected = [[
            'character' => ['id' => 123, 'name' => 'C1', 'corporation' => null],
            'groups' => [
                ['id' => $this->group1Id, 'name' => 'g1', 'visibility' => Group::VISIBILITY_PRIVATE]
            ],
        ], [
            'character' => ['id' => 789, 'name' => 'C3', 'corporation' => [
                'id' => 500, 'name' => 'five', 'ticker' => '-5-', 'alliance' => [
                    'id' => 100, 'name' => 'one', 'ticker' => '-1-'
                ]
            ]],
            'groups' => [],
        ]];
        $this->assertSame($expected, $body);
    }

    public function testCorpGroupsV1403()
    {
        $response = $this->runApp('GET', '/api/app/v1/corp-groups/123');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testCorpGroupsV1404()
    {
        $this->helper->emptyDb();
        $aid = $this->helper->addApp('A1', 's1', ['app'])->getId();

        $headers = ['Authorization' => 'Bearer '.base64_encode($aid.':s1')];
        $response = $this->runApp('GET', '/api/app/v1/corp-groups/123', null, $headers);
        $this->assertEquals(404, $response->getStatusCode());
        $this->assertEquals('Not Found', $response->getReasonPhrase());
    }

    public function testCorpGroupsV2404()
    {
        $this->helper->emptyDb();
        $aid = $this->helper->addApp('A1', 's1', ['app'])->getId();

        $headers = ['Authorization' => 'Bearer '.base64_encode($aid.':s1')];
        $response = $this->runApp('GET', '/api/app/v2/corp-groups/123', null, $headers);
        $this->assertEquals(404, $response->getStatusCode());
        $this->assertEquals('Corporation not found.', $response->getReasonPhrase());
    }

    public function testCorpGroupsV1200()
    {
        $this->setUpDb();

        $headers = ['Authorization' => 'Bearer '.base64_encode($this->appId.':s1')];
        $response = $this->runApp('GET', '/api/app/v1/corp-groups/500', null, $headers);

        $this->assertEquals(200, $response->getStatusCode());

        $this->assertSame([
            ['id' => $this->group1Id, 'name' => 'g1', 'visibility' => Group::VISIBILITY_PRIVATE]
        ], $this->parseJsonBody($response));
    }

    public function testCorpGroupsBulkV1403()
    {
        $response = $this->runApp('POST', '/api/app/v1/corp-groups');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testCorpGroupsBulkV1400()
    {
        $this->setUpDb();

        $headers = ['Authorization' => 'Bearer '.base64_encode($this->appId.':s1')];
        $response = $this->runApp('POST', '/api/app/v1/corp-groups', new \stdClass(), $headers);

        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testCorpGroupsBulkV1200()
    {
        $this->setUpDb();

        $headers = ['Authorization' => 'Bearer '.base64_encode($this->appId.':s1')];
        $response = $this->runApp('POST', '/api/app/v1/corp-groups', [500, 500, 789, 501], $headers);

        $this->assertEquals(200, $response->getStatusCode());

        $body = $this->parseJsonBody($response);

        $expected = [[
            'id' => 500, 'name' => 'five', 'ticker' => '-5-', 'groups' => [
                ['id' => $this->group1Id, 'name' => 'g1', 'visibility' => Group::VISIBILITY_PRIVATE],
            ]
        ], [
            'id' => 501, 'name' => 'f1', 'ticker' => '-51-', 'groups' => [
                ['id' => $this->group0Id, 'name' => 'g0', 'visibility' => Group::VISIBILITY_PRIVATE],
                ['id' => $this->group1Id, 'name' => 'g1', 'visibility' => Group::VISIBILITY_PRIVATE],
            ]
        ]];
        $this->assertSame($expected, $body);
    }

    public function testAllianceGroupsV1403()
    {
        $response = $this->runApp('GET', '/api/app/v1/alliance-groups/123');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testAllianceGroupsV1404()
    {
        $this->helper->emptyDb();
        $aid = $this->helper->addApp('A1', 's1', ['app'])->getId();

        $headers = ['Authorization' => 'Bearer '.base64_encode($aid.':s1')];
        $response = $this->runApp('GET', '/api/app/v1/alliance-groups/123', null, $headers);
        $this->assertEquals(404, $response->getStatusCode());
        $this->assertEquals('Not Found', $response->getReasonPhrase());
    }

    public function testAllianceGroupsV2404()
    {
        $this->helper->emptyDb();
        $aid = $this->helper->addApp('A1', 's1', ['app'])->getId();

        $headers = ['Authorization' => 'Bearer '.base64_encode($aid.':s1')];
        $response = $this->runApp('GET', '/api/app/v2/alliance-groups/123', null, $headers);
        $this->assertEquals(404, $response->getStatusCode());
        $this->assertEquals('Alliance not found.', $response->getReasonPhrase());
    }

    public function testAllianceGroupsV1200()
    {
        $this->setUpDb();

        $headers = ['Authorization' => 'Bearer '.base64_encode($this->appId.':s1')];
        $response = $this->runApp('GET', '/api/app/v1/alliance-groups/100', null, $headers);

        $this->assertEquals(200, $response->getStatusCode());

        $this->assertSame([
            ['id' => $this->group4Id, 'name' => 'g4', 'visibility' => Group::VISIBILITY_PRIVATE]
        ], $this->parseJsonBody($response));
    }

    public function testAllianceGroupsBulkV1403()
    {
        $response = $this->runApp('POST', '/api/app/v1/alliance-groups');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testAllianceGroupsBulkV1400()
    {
        $this->setUpDb();

        $headers = ['Authorization' => 'Bearer '.base64_encode($this->appId.':s1')];
        $response = $this->runApp('POST', '/api/app/v1/alliance-groups', new \stdClass(), $headers);

        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testAllianceGroupsBulkV1200()
    {
        $this->setUpDb();

        $headers = ['Authorization' => 'Bearer '.base64_encode($this->appId.':s1')];
        $response = $this->runApp('POST', '/api/app/v1/alliance-groups', [100, 100, 789, 101], $headers);

        $this->assertEquals(200, $response->getStatusCode());

        $body = $this->parseJsonBody($response);

        $expected = [[
            'id' => 100, 'name' => 'one', 'ticker' => '-1-', 'groups' => [
                ['id' => $this->group4Id, 'name' => 'g4', 'visibility' => Group::VISIBILITY_PRIVATE],
            ]
        ], [
            'id' => 101, 'name' => 'o1', 'ticker' => '-11-', 'groups' => [
                ['id' => $this->group0Id, 'name' => 'g0', 'visibility' => Group::VISIBILITY_PRIVATE],
                ['id' => $this->group4Id, 'name' => 'g4', 'visibility' => Group::VISIBILITY_PRIVATE],
            ]
        ]];
        $this->assertSame($expected, $body);
    }

    public function testMainV1403()
    {
        $response = $this->runApp('GET', '/api/app/v1/main/123');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testMainV1404()
    {
        $this->helper->emptyDb();
        $aid = $this->helper->addApp('A1', 's1', ['app'])->getId();

        $headers = ['Authorization' => 'Bearer '.base64_encode($aid.':s1')];
        $response = $this->runApp('GET', '/api/app/v1/main/123', null, $headers);

        $this->assertEquals(404, $response->getStatusCode());
        $this->assertEquals('Not Found', $response->getReasonPhrase());
    }

    public function testMainV2404()
    {
        $this->helper->emptyDb();
        $aid = $this->helper->addApp('A1', 's1', ['app'])->getId();

        $headers = ['Authorization' => 'Bearer '.base64_encode($aid.':s1')];
        $response = $this->runApp('GET', '/api/app/v2/main/123', null, $headers);

        $this->assertEquals(404, $response->getStatusCode());
        $this->assertEquals('Character not found.', $response->getReasonPhrase());
    }

    public function testMainV1204()
    {
        $this->helper->emptyDb();
        $aid = $this->helper->addApp('A1', 's1', ['app'])->getId();

        $char = $this->helper->addCharacterMain('C1', 123, [Role::USER]);
        $char->setMain(false);
        $this->helper->getEm()->flush();

        $headers = ['Authorization' => 'Bearer '.base64_encode($aid.':s1')];
        $response = $this->runApp('GET', '/api/app/v1/main/123', null, $headers);

        $this->assertEquals(204, $response->getStatusCode());
    }

    public function testMainV1200()
    {
        $this->helper->emptyDb();
        $aid = $this->helper->addApp('A1', 's1', ['app'])->getId();
        $char = $this->helper->addCharacterMain('C1', 123, [Role::USER]);
        $this->helper->addCharacterToPlayer('C2', 456, $char->getPlayer());

        $headers = ['Authorization' => 'Bearer '.base64_encode($aid.':s1')];
        $response1 = $this->runApp('GET', '/api/app/v1/main/123', null, $headers);
        $response2 = $this->runApp('GET', '/api/app/v1/main/456', null, $headers);

        $this->assertEquals(200, $response1->getStatusCode());
        $this->assertEquals(200, $response2->getStatusCode());

        $body1 = $this->parseJsonBody($response1);
        $body2 = $this->parseJsonBody($response2);

        $this->assertSame($body1, $body2);
        $this->assertSame(
            [
                'id' => 123,
                'name' => 'C1',
                'main' => true,
                'lastUpdate' => null,
                'validToken' => false,
                'corporation' => null
            ],
            $body1
        );
    }

    public function testCharactersV1403()
    {
        $response = $this->runApp('GET', '/api/app/v1/characters/123');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testCharactersV1404()
    {
        $this->helper->emptyDb();
        $aid = $this->helper->addApp('A1', 's1', ['app'])->getId();

        $headers = ['Authorization' => 'Bearer '.base64_encode($aid.':s1')];
        $response = $this->runApp('GET', '/api/app/v1/characters/123', null, $headers);

        $this->assertEquals(404, $response->getStatusCode());
        $this->assertEquals('Character not found.', $response->getReasonPhrase());
    }

    public function testCharactersV1200()
    {
        $this->helper->emptyDb();
        $aid = $this->helper->addApp('A1', 's1', ['app'])->getId();
        $char = $this->helper->addCharacterMain('C1', 123, [Role::USER]);
        $this->helper->addCharacterToPlayer('C2', 456, $char->getPlayer());

        $headers = ['Authorization' => 'Bearer '.base64_encode($aid.':s1')];
        $response1 = $this->runApp('GET', '/api/app/v1/characters/123', null, $headers);
        $response2 = $this->runApp('GET', '/api/app/v1/characters/456', null, $headers);

        $this->assertEquals(200, $response1->getStatusCode());
        $this->assertEquals(200, $response2->getStatusCode());

        $body1 = $this->parseJsonBody($response1);
        $body2 = $this->parseJsonBody($response2);

        $this->assertSame($body1, $body2);
        $this->assertSame([[
                'id' => 123,
                'name' => 'C1',
                'main' => true,
                'lastUpdate' => null,
                'validToken' => false,
                'corporation' => null
            ],[
                'id' => 456,
                'name' => 'C2',
                'main' => false,
                'lastUpdate' => null,
                'validToken' => false,
                'corporation' => null
            ]],
            $body1
        );
    }

    private function setUpDb($invalidHours = 0)
    {
        $this->helper->emptyDb();

        $groups = $this->helper->addGroups(['g0', 'g1', 'g2', 'g3', 'g4']);
        $this->group0Id = $groups[0]->getId();
        $this->group1Id = $groups[1]->getId();
        $this->group4Id = $groups[4]->getId();

        $app = $this->helper->addApp('A1', 's1', ['app']);
        $app->addGroup($groups[0]);
        $app->addGroup($groups[1]);
        $app->addGroup($groups[4]);
        $this->appId = $app->getId();

        $char1 = $this->helper->addCharacterMain('C1', 123, [Role::USER]);
        $char1->setValidToken(true);
        $char2 = $this->helper->addCharacterToPlayer('C2', 456, $char1->getPlayer());
        $char2->setValidToken(true);

        $char1->getPlayer()->addGroup($groups[1]);
        $char2->getPlayer()->addGroup($groups[2]);

        $alli = (new Alliance())->setId(100)->setName('one')->setTicker('-1-');
        $alli->addGroup($groups[2]);
        $alli->addGroup($groups[4]);

        $alli2 = (new Alliance())->setId(101)->setName('o1')->setTicker('-11-');
        $alli2->addGroup($groups[0]);
        $alli2->addGroup($groups[4]);

        $corp = (new Corporation())->setId(500)->setName('five')->setTicker('-5-');
        $corp->addGroup($groups[2]);
        $corp->addGroup($groups[1]);
        $corp->setAlliance($alli);

        $corp2 = (new Corporation())->setId(501)->setName('f1')->setTicker('-51-');
        $corp2->addGroup($groups[0]);
        $corp2->addGroup($groups[1]);

        $this->helper->getEm()->persist($alli);
        $this->helper->getEm()->persist($alli2);
        $this->helper->getEm()->persist($corp);
        $this->helper->getEm()->persist($corp2);

        $char3 = $this->helper->addCharacterMain('C3', 789); // no roles
        $char3->setCorporation($corp);
        $char3->getPlayer()->addGroup($groups[0]);
        $char3->getPlayer()->addGroup($groups[1]);
        try {
            $char3->setValidTokenTime(new \DateTime("now -{$invalidHours} hours"));
        } catch (\Exception $e) {
            echo $e->getMessage();
        }

        $this->helper->getEm()->flush();
        $this->helper->getEm()->clear();
    }
}
