<?php
/** @noinspection DuplicatedCode */

declare(strict_types=1);

namespace Tests\Functional\Controller\App;

use Neucore\Entity\Player;
use Neucore\Entity\Role;
use Neucore\Entity\SystemVariable;
use Neucore\Entity\Group;
use Tests\Functional\WebTestCase;
use Tests\Helper;
use Neucore\Entity\Corporation;
use Neucore\Entity\Alliance;

class GroupControllerTest extends WebTestCase
{
    /**
     * @var Helper
     */
    private $helper;

    private $appId;

    private $group0Id;

    private $group1Id;

    private $group2Id;

    private $group4Id;

    private $group5Id;

    protected function setUp(): void
    {
        $this->helper = new Helper();
    }

    public function testGroupsV1403()
    {
        $response = $this->runApp('GET', '/api/app/v1/groups/123');
        $this->assertEquals(403, $response->getStatusCode());

        $this->helper->emptyDb();
        $a0id = $this->helper->addApp('A0', 's0', [Role::APP])->getId();
        $headers = ['Authorization' => 'Bearer '.base64_encode($a0id.':s0')];
        $response2 = $this->runApp('GET', '/api/app/v1/groups/123', null, $headers);
        $this->assertEquals(403, $response2->getStatusCode());
    }

    public function testGroupsV1404()
    {
        $this->helper->emptyDb();
        $aid = $this->helper->addApp('A1', 's1', [Role::APP, Role::APP_GROUPS])->getId();

        $headers = ['Authorization' => 'Bearer '.base64_encode($aid.':s1')];
        $response = $this->runApp('GET', '/api/app/v1/groups/123', null, $headers);
        $this->assertEquals(404, $response->getStatusCode());
        $this->assertEquals('Not Found', $response->getReasonPhrase());
    }

    public function testGroupsV2403()
    {
        $response = $this->runApp('GET', '/api/app/v2/groups/123');
        $this->assertEquals(403, $response->getStatusCode());

        $this->helper->emptyDb();
        $a0id = $this->helper->addApp('A0', 's0', [Role::APP])->getId();
        $headers = ['Authorization' => 'Bearer '.base64_encode($a0id.':s0')];
        $response2 = $this->runApp('GET', '/api/app/v2/groups/123', null, $headers);
        $this->assertEquals(403, $response2->getStatusCode());
    }

    public function testGroupsV2404()
    {
        $this->helper->emptyDb();
        $aid = $this->helper->addApp('A1', 's1', [Role::APP, Role::APP_GROUPS])->getId();

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
            ['id' => $this->group1Id, 'name' => 'g1', 'description' => null,
                'visibility' => Group::VISIBILITY_PRIVATE, 'autoAccept' => false]
        ], $body1);
    }

    public function testGroupsV1200Deactivated()
    {
        $this->setUpDb(36);

        // activate "deactivated groups"
        $setting1 = (new SystemVariable(SystemVariable::GROUPS_REQUIRE_VALID_TOKEN))->setValue('1');
        $setting2 = (new SystemVariable(SystemVariable::ACCOUNT_DEACTIVATION_ALLIANCES))->setValue('100');
        $setting3 = (new SystemVariable(SystemVariable::ACCOUNT_DEACTIVATION_CORPORATIONS))->setValue('500');
        $this->helper->getObjectManager()->persist($setting1);
        $this->helper->getObjectManager()->persist($setting2);
        $this->helper->getObjectManager()->persist($setting3);
        $this->helper->getObjectManager()->flush();

        $headers = ['Authorization' => 'Bearer '.base64_encode($this->appId.':s1')];
        $response = $this->runApp('GET', '/api/app/v1/groups/789', null, $headers);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertSame([], $this->parseJsonBody($response));
    }

    public function testGroupsV1200DeactivatedManaged()
    {
        $this->setUpDb(36);

        // activate "deactivated groups"
        $setting1 = (new SystemVariable(SystemVariable::GROUPS_REQUIRE_VALID_TOKEN))->setValue('1');
        $this->helper->getObjectManager()->persist($setting1);
        $this->helper->getObjectManager()->flush();

        $headers = ['Authorization' => 'Bearer '.base64_encode($this->appId.':s1')];
        $response = $this->runApp('GET', '/api/app/v1/groups/780', null, $headers);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertSame([
            ['id' => $this->group0Id, 'name' => 'g0', 'description' => null,
                'visibility' => Group::VISIBILITY_PRIVATE, 'autoAccept' => false],
        ], $this->parseJsonBody($response));
    }

    public function testGroupsBulkV1403()
    {
        $response = $this->runApp('POST', '/api/app/v1/groups');
        $this->assertEquals(403, $response->getStatusCode());

        $this->helper->emptyDb();
        $a0id = $this->helper->addApp('A0', 's0', [Role::APP])->getId();
        $headers = ['Authorization' => 'Bearer '.base64_encode($a0id.':s0')];
        $response2 = $this->runApp('POST', '/api/app/v1/groups', null, $headers);
        $this->assertEquals(403, $response2->getStatusCode());
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

        $headers = [
            'Authorization' => 'Bearer '.base64_encode($this->appId.':s1'),
            'Content-Type' => 'application/json'
        ];
        $response = $this->runApp('POST', '/api/app/v1/groups', [123, 789, 789, 12], $headers);

        $this->assertEquals(200, $response->getStatusCode());

        $body = $this->parseJsonBody($response);

        $expected = [[
            'character' => ['id' => 123, 'name' => 'C1', 'corporation' => null],
            'groups' => [
                ['id' => $this->group1Id, 'name' => 'g1', 'description' => null,
                    'visibility' => Group::VISIBILITY_PRIVATE, 'autoAccept' => false]
            ],
        ], [
            'character' => ['id' => 789, 'name' => 'C3', 'corporation' => [
                'id' => 500, 'name' => 'five', 'ticker' => '-5-', 'alliance' => [
                    'id' => 100, 'name' => 'one', 'ticker' => '-1-'
                ]
            ]],
            'groups' => [
                ['id' => $this->group0Id, 'name' => 'g0', 'description' => null,
                    'visibility' => Group::VISIBILITY_PRIVATE, 'autoAccept' => false],
                ['id' => $this->group1Id, 'name' => 'g1', 'description' => null,
                    'visibility' => Group::VISIBILITY_PRIVATE, 'autoAccept' => false],
            ],
        ]];
        $this->assertSame($expected, $body);
    }

    public function testGroupsBulkV1200Deactivated()
    {
        $this->setUpDb(48);

        // activate "deactivated groups"
        $setting1 = (new SystemVariable(SystemVariable::GROUPS_REQUIRE_VALID_TOKEN))->setValue('1');
        $setting2 = (new SystemVariable(SystemVariable::ACCOUNT_DEACTIVATION_ALLIANCES))->setValue('100');
        $setting3 = (new SystemVariable(SystemVariable::ACCOUNT_DEACTIVATION_CORPORATIONS))->setValue('500');
        $this->helper->getObjectManager()->persist($setting1);
        $this->helper->getObjectManager()->persist($setting2);
        $this->helper->getObjectManager()->persist($setting3);
        $this->helper->getObjectManager()->flush();

        $headers = [
            'Authorization' => 'Bearer '.base64_encode($this->appId.':s1'),
            'Content-Type' => 'application/json'
        ];
        $response = $this->runApp('POST', '/api/app/v1/groups', [123, 789], $headers);

        $this->assertEquals(200, $response->getStatusCode());

        $body = $this->parseJsonBody($response);

        $expected = [[
            'character' => ['id' => 123, 'name' => 'C1', 'corporation' => null],
            'groups' => [
                ['id' => $this->group1Id, 'name' => 'g1', 'description' => null,
                    'visibility' => Group::VISIBILITY_PRIVATE, 'autoAccept' => false]
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

        $this->helper->emptyDb();
        $a0id = $this->helper->addApp('A0', 's0', [Role::APP])->getId();
        $headers = ['Authorization' => 'Bearer '.base64_encode($a0id.':s0')];
        $response = $this->runApp('GET', '/api/app/v1/corp-groups/123', null, $headers);
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testCorpGroupsV1404()
    {
        $this->helper->emptyDb();
        $aid = $this->helper->addApp('A1', 's1', [Role::APP, Role::APP_GROUPS])->getId();

        $headers = ['Authorization' => 'Bearer '.base64_encode($aid.':s1')];
        $response = $this->runApp('GET', '/api/app/v1/corp-groups/123', null, $headers);
        $this->assertEquals(404, $response->getStatusCode());
        $this->assertEquals('Not Found', $response->getReasonPhrase());
    }

    public function testCorpGroupsV2403()
    {
        $response = $this->runApp('GET', '/api/app/v2/corp-groups/123');
        $this->assertEquals(403, $response->getStatusCode());

        $this->helper->emptyDb();
        $a0id = $this->helper->addApp('A0', 's0', [Role::APP])->getId();
        $headers = ['Authorization' => 'Bearer '.base64_encode($a0id.':s0')];
        $response = $this->runApp('GET', '/api/app/v2/corp-groups/123', null, $headers);
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testCorpGroupsV2404()
    {
        $this->helper->emptyDb();
        $aid = $this->helper->addApp('A1', 's1', [Role::APP, Role::APP_GROUPS])->getId();

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
            ['id' => $this->group1Id, 'name' => 'g1', 'description' => null,
                'visibility' => Group::VISIBILITY_PRIVATE, 'autoAccept' => false],
            ['id' => $this->group4Id, 'name' => 'g4', 'description' => null,
                'visibility' => Group::VISIBILITY_PRIVATE, 'autoAccept' => false],
        ], $this->parseJsonBody($response));
    }

    public function testCorpGroupsBulkV1403()
    {
        $response = $this->runApp('POST', '/api/app/v1/corp-groups');
        $this->assertEquals(403, $response->getStatusCode());

        $this->helper->emptyDb();
        $a0id = $this->helper->addApp('A0', 's0', [Role::APP])->getId();
        $headers = ['Authorization' => 'Bearer '.base64_encode($a0id.':s0')];
        $response = $this->runApp('POST', '/api/app/v1/corp-groups', null, $headers);
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

        $headers = [
            'Authorization' => 'Bearer '.base64_encode($this->appId.':s1'),
            'Content-Type' => 'application/json'
        ];
        $response = $this->runApp('POST', '/api/app/v1/corp-groups', [500, 500, 789, 501], $headers);

        $this->assertEquals(200, $response->getStatusCode());

        $body = $this->parseJsonBody($response);

        $expected = [[
            'id' => 500, 'name' => 'five', 'ticker' => '-5-', 'groups' => [
                ['id' => $this->group1Id, 'name' => 'g1', 'description' => null,
                    'visibility' => Group::VISIBILITY_PRIVATE, 'autoAccept' => false],
                ['id' => $this->group4Id, 'name' => 'g4', 'description' => null,
                    'visibility' => Group::VISIBILITY_PRIVATE, 'autoAccept' => false],
            ]
        ], [
            'id' => 501, 'name' => 'f1', 'ticker' => '-51-', 'groups' => [
                ['id' => $this->group0Id, 'name' => 'g0', 'description' => null,
                    'visibility' => Group::VISIBILITY_PRIVATE, 'autoAccept' => false],
                ['id' => $this->group1Id, 'name' => 'g1', 'description' => null,
                    'visibility' => Group::VISIBILITY_PRIVATE, 'autoAccept' => false],
            ]
        ]];
        $this->assertSame($expected, $body);
    }

    public function testAllianceGroupsV1403()
    {
        $response = $this->runApp('GET', '/api/app/v1/alliance-groups/123');
        $this->assertEquals(403, $response->getStatusCode());

        $this->helper->emptyDb();
        $a0id = $this->helper->addApp('A0', 's0', [Role::APP])->getId();
        $headers = ['Authorization' => 'Bearer '.base64_encode($a0id.':s0')];
        $response = $this->runApp('GET', '/api/app/v1/alliance-groups/123', null, $headers);
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testAllianceGroupsV1404()
    {
        $this->helper->emptyDb();
        $aid = $this->helper->addApp('A1', 's1', [Role::APP, Role::APP_GROUPS])->getId();

        $headers = ['Authorization' => 'Bearer '.base64_encode($aid.':s1')];
        $response = $this->runApp('GET', '/api/app/v1/alliance-groups/123', null, $headers);
        $this->assertEquals(404, $response->getStatusCode());
        $this->assertEquals('Not Found', $response->getReasonPhrase());
    }

    public function testAllianceGroupsV2403()
    {
        $response = $this->runApp('GET', '/api/app/v2/alliance-groups/123');
        $this->assertEquals(403, $response->getStatusCode());

        $this->helper->emptyDb();
        $a0id = $this->helper->addApp('A0', 's0', [Role::APP])->getId();
        $headers = ['Authorization' => 'Bearer '.base64_encode($a0id.':s0')];
        $response = $this->runApp('GET', '/api/app/v2/alliance-groups/123', null, $headers);
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testAllianceGroupsV2404()
    {
        $this->helper->emptyDb();
        $aid = $this->helper->addApp('A1', 's1', [Role::APP, Role::APP_GROUPS])->getId();

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
            ['id' => $this->group4Id, 'name' => 'g4', 'description' => null,
                'visibility' => Group::VISIBILITY_PRIVATE, 'autoAccept' => false],
            ['id' => $this->group5Id, 'name' => 'g5', 'description' => null,
                'visibility' => Group::VISIBILITY_PRIVATE, 'autoAccept' => false],
        ], $this->parseJsonBody($response));
    }

    public function testAllianceGroupsBulkV1403()
    {
        $response = $this->runApp('POST', '/api/app/v1/alliance-groups');
        $this->assertEquals(403, $response->getStatusCode());

        $this->helper->emptyDb();
        $a0id = $this->helper->addApp('A0', 's0', [Role::APP])->getId();
        $headers = ['Authorization' => 'Bearer '.base64_encode($a0id.':s0')];
        $response = $this->runApp('POST', '/api/app/v1/alliance-groups', null, $headers);
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

        $headers = [
            'Authorization' => 'Bearer '.base64_encode($this->appId.':s1'),
            'Content-Type' => 'application/json'
        ];
        $response = $this->runApp('POST', '/api/app/v1/alliance-groups', [100, 100, 789, 101], $headers);

        $this->assertEquals(200, $response->getStatusCode());

        $body = $this->parseJsonBody($response);

        $expected = [[
            'id' => 100, 'name' => 'one', 'ticker' => '-1-', 'groups' => [
                ['id' => $this->group4Id, 'name' => 'g4', 'description' => null,
                    'visibility' => Group::VISIBILITY_PRIVATE, 'autoAccept' => false],
                ['id' => $this->group5Id, 'name' => 'g5', 'description' => null,
                    'visibility' => Group::VISIBILITY_PRIVATE, 'autoAccept' => false],
            ]
        ], [
            'id' => 101, 'name' => 'o1', 'ticker' => '-11-', 'groups' => [
                ['id' => $this->group0Id, 'name' => 'g0', 'description' => null,
                    'visibility' => Group::VISIBILITY_PRIVATE, 'autoAccept' => false],
                ['id' => $this->group4Id, 'name' => 'g4', 'description' => null,
                    'visibility' => Group::VISIBILITY_PRIVATE, 'autoAccept' => false],
            ]
        ]];
        $this->assertSame($expected, $body);
    }

    public function testGroupsWithFallbackV1403()
    {
        $response = $this->runApp('GET', '/api/app/v1/groups-with-fallback');
        $this->assertEquals(403, $response->getStatusCode());

        $this->helper->emptyDb();
        $a0id = $this->helper->addApp('A0', 's0', [Role::APP])->getId();
        $headers = ['Authorization' => 'Bearer '.base64_encode($a0id.':s0')];
        $response = $this->runApp('GET', '/api/app/v1/groups-with-fallback', null, $headers);
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testGroupsWithFallbackV1200FromCharacter()
    {
        $this->setUpDb();

        $headers = ['Authorization' => 'Bearer '.base64_encode($this->appId.':s1')];
        $response = $this->runApp(
            'GET',
            '/api/app/v1/groups-with-fallback?character=123&corporation=500&alliance=100',
            null,
            $headers
        );

        # app: g0, g1, g4
        # char 123: g1, g2,

        $this->assertEquals(200, $response->getStatusCode());

        $body = $this->parseJsonBody($response);
        $this->assertSame([
            ['id' => $this->group1Id, 'name' => 'g1', 'description' => null,
                'visibility' => Group::VISIBILITY_PRIVATE, 'autoAccept' => false],
        ], $body);
    }

    public function testGroupsWithFallbackV1200FromCorpAndAlliance()
    {
        $this->setUpDb();

        $headers = ['Authorization' => 'Bearer '.base64_encode($this->appId.':s1')];
        $response = $this->runApp(
            'GET',
            '/api/app/v1/groups-with-fallback?character=987&corporation=500&alliance=100',
            null,
            $headers
        );

        # app: g0, g1, g4, g5
        # corp 500: g1, g2, g4
        # alli 100: g2, g4, g5

        $this->assertEquals(200, $response->getStatusCode());

        $body = $this->parseJsonBody($response);
        $this->assertSame([
            ['id' => $this->group1Id, 'name' => 'g1', 'description' => null,
                'visibility' => Group::VISIBILITY_PRIVATE, 'autoAccept' => false],
            ['id' => $this->group4Id, 'name' => 'g4', 'description' => null,
                'visibility' => Group::VISIBILITY_PRIVATE, 'autoAccept' => false],
            ['id' => $this->group5Id, 'name' => 'g5', 'description' => null,
                'visibility' => Group::VISIBILITY_PRIVATE, 'autoAccept' => false],
        ], $body);
    }

    public function testGroupsWithFallbackV1200CharacterWithoutGroupsDoesNotReturnCorpGroups()
    {
        $this->setUpDb();

        $headers = ['Authorization' => 'Bearer '.base64_encode($this->appId.':s1')];
        $response = $this->runApp(
            'GET',
            '/api/app/v1/groups-with-fallback?character=1010&corporation=500&alliance=100',
            null,
            $headers
        );

        $this->assertEquals(200, $response->getStatusCode());

        $body = $this->parseJsonBody($response);
        $this->assertSame([], $body);
    }

    public function testMembers403()
    {
        $this->setUpDb();

        $response = $this->runApp('GET', '/api/app/v1/group-members/'.$this->group1Id);

        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testMembers404()
    {
        $this->setUpDb();

        $headers = ['Authorization' => 'Bearer '.base64_encode($this->appId.':s1')];

        $response1 = $this->runApp('GET', '/api/app/v1/group-members/'.($this->group1Id+100), null, $headers);
        $this->assertEquals(404, $response1->getStatusCode());
        $this->assertEquals('Group not found.', $response1->getReasonPhrase());

        $response2 = $this->runApp('GET', '/api/app/v1/group-members/'.$this->group2Id, null, $headers);
        $this->assertEquals(404, $response2->getStatusCode());
    }

    public function testMembers200()
    {
        $this->setUpDb();

        $headers = ['Authorization' => 'Bearer '.base64_encode($this->appId.':s1')];
        $response = $this->runApp('GET', '/api/app/v1/group-members/'.$this->group1Id, null, $headers);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertSame([123, 789], $this->parseJsonBody($response));
    }

    private function setUpDb(int $invalidHours = 0): void
    {
        $this->helper->emptyDb();

        $groups = $this->helper->addGroups(['g0', 'g1', 'g2', 'g3', 'g4', 'g5']);
        $this->group0Id = $groups[0]->getId();
        $this->group1Id = $groups[1]->getId();
        $this->group2Id = $groups[2]->getId();
        $this->group4Id = $groups[4]->getId();
        $this->group5Id = $groups[5]->getId();

        $app = $this->helper->addApp('A1', 's1', [Role::APP, Role::APP_GROUPS]);
        $app->addGroup($groups[0]);
        $app->addGroup($groups[1]);
        $app->addGroup($groups[4]);
        $app->addGroup($groups[5]);
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
        $alli->addGroup($groups[5]);

        $alli2 = (new Alliance())->setId(101)->setName('o1')->setTicker('-11-');
        $alli2->addGroup($groups[0]);
        $alli2->addGroup($groups[4]);

        $corp = (new Corporation())->setId(500)->setName('five')->setTicker('-5-');
        $corp->addGroup($groups[2]);
        $corp->addGroup($groups[1]);
        $corp->addGroup($groups[4]);
        $corp->setAlliance($alli);

        $corp2 = (new Corporation())->setId(501)->setName('f1')->setTicker('-51-');
        $corp2->addGroup($groups[0]);
        $corp2->addGroup($groups[1]);

        $this->helper->getObjectManager()->persist($alli);
        $this->helper->getObjectManager()->persist($alli2);
        $this->helper->getObjectManager()->persist($corp);
        $this->helper->getObjectManager()->persist($corp2);

        $char3 = $this->helper->addCharacterMain('C3', 789); // no roles
        $char3->setValidToken(false)->setCorporation($corp);
        $char3->getPlayer()->addGroup($groups[0]);
        $char3->getPlayer()->addGroup($groups[1]);
        $char3->setValidTokenTime(new \DateTime("now -{$invalidHours} hours"));

        $char4 = $this->helper->addCharacterMain('C3', 780); // managed account
        $char4->setValidToken(false)->setCorporation($corp);
        $char4->getPlayer()->setStatus(Player::STATUS_MANAGED);
        $char4->getPlayer()->addGroup($groups[0]);
        $char4->setValidTokenTime(new \DateTime("now -{$invalidHours} hours"));

        $this->helper->addCharacterMain('C4', 1010, [Role::USER]); // no groups

        $this->helper->getObjectManager()->flush();
        $this->helper->getObjectManager()->clear();
    }
}
