<?php

/** @noinspection DuplicatedCode */

declare(strict_types=1);

namespace Tests\Unit\Service;

use Doctrine\Persistence\ObjectManager;
use Neucore\Entity\Alliance;
use Neucore\Entity\Character;
use Neucore\Entity\Corporation;
use Neucore\Entity\EsiToken;
use Neucore\Entity\EveLogin;
use Neucore\Entity\Group;
use Neucore\Entity\Player;
use Neucore\Entity\SystemVariable;
use Neucore\Factory\RepositoryFactory;
use Neucore\Plugin\Data\CoreGroup;
use Neucore\Service\AccountGroup;
use PHPUnit\Framework\TestCase;
use Tests\Helper;

class AccountGroupTest extends TestCase
{
    private Helper $helper;

    private ObjectManager $om;

    private AccountGroup $service;

    protected function setUp(): void
    {
        $this->helper = new Helper();
        $this->helper->emptyDb();
        $this->om = $this->helper->getObjectManager();
        $this->service = new AccountGroup(new RepositoryFactory($this->om), $this->om);
    }

    public function testGroupsDeactivatedValidToken()
    {
        $setting1 = (new SystemVariable(SystemVariable::GROUPS_REQUIRE_VALID_TOKEN))->setValue('1');
        $setting2 = (new SystemVariable(SystemVariable::ACCOUNT_DEACTIVATION_ALLIANCES))->setValue('11');
        $setting3 = (new SystemVariable(SystemVariable::ACCOUNT_DEACTIVATION_CORPORATIONS))->setValue('101');
        $this->om->persist($setting1);
        $this->om->persist($setting2);
        $this->om->persist($setting3);
        $this->om->flush();

        $alliance = (new Alliance())->setId(11);
        $corporation = (new Corporation())->setId(101);
        $corporation->setAlliance($alliance);
        $player = (new Player())->addCharacter(
            (new Character())
            ->setCorporation($corporation)
            ->addEsiToken((new EsiToken())
                ->setEveLogin((new EveLogin())->setName(EveLogin::NAME_DEFAULT))
                ->setValidToken(true)),
        );

        $this->assertFalse($this->service->groupsDeactivated($player));
    }

    public function testGroupsDeactivatedWrongAllianceAndCorporation()
    {
        $setting1 = (new SystemVariable(SystemVariable::GROUPS_REQUIRE_VALID_TOKEN))->setValue('1');
        $setting2 = (new SystemVariable(SystemVariable::ACCOUNT_DEACTIVATION_ALLIANCES))->setValue('11');
        $setting3 = (new SystemVariable(SystemVariable::ACCOUNT_DEACTIVATION_CORPORATIONS))->setValue('101');
        $this->om->persist($setting1);
        $this->om->persist($setting2);
        $this->om->persist($setting3);
        $this->om->flush();

        $alliance = (new Alliance())->setId(12);
        $corporation = (new Corporation())->setId(102);
        $corporation->setAlliance($alliance);
        $player = (new Player())->addCharacter(
            (new Character())
            ->setCorporation($corporation)
            ->addEsiToken((new EsiToken())
                ->setEveLogin((new EveLogin())->setName(EveLogin::NAME_DEFAULT))
                ->setValidToken(false)),
        );

        $this->assertFalse($this->service->groupsDeactivated($player));
    }

    public function testGroupsDeactivatedInvalidToken()
    {
        $setting1 = (new SystemVariable(SystemVariable::GROUPS_REQUIRE_VALID_TOKEN))->setValue('1');
        $setting2 = (new SystemVariable(SystemVariable::ACCOUNT_DEACTIVATION_ALLIANCES))->setValue('11');
        $setting3 = (new SystemVariable(SystemVariable::ACCOUNT_DEACTIVATION_CORPORATIONS))->setValue('101');
        $this->om->persist($setting1);
        $this->om->persist($setting2);
        $this->om->persist($setting3);
        $this->om->flush();

        $alliance = (new Alliance())->setId(11);
        $corporation = (new Corporation())->setId(101);
        $corporation->setAlliance($alliance);
        $player = (new Player())->addCharacter(
            (new Character())
            ->setCorporation($corporation)
            ->addEsiToken((new EsiToken())
                ->setEveLogin((new EveLogin())->setName(EveLogin::NAME_DEFAULT))
                ->setValidToken(false)),
        );

        $this->assertTrue($this->service->groupsDeactivated($player));
    }

    public function testGroupsDeactivatedInvalidTokenManaged()
    {
        $setting1 = (new SystemVariable(SystemVariable::GROUPS_REQUIRE_VALID_TOKEN))->setValue('1');
        $setting2 = (new SystemVariable(SystemVariable::ACCOUNT_DEACTIVATION_ALLIANCES))->setValue('11');
        $setting3 = (new SystemVariable(SystemVariable::ACCOUNT_DEACTIVATION_CORPORATIONS))->setValue('101');
        $this->om->persist($setting1);
        $this->om->persist($setting2);
        $this->om->persist($setting3);
        $this->om->flush();

        $alliance = (new Alliance())->setId(11);
        $corporation = (new Corporation())->setId(101);
        $corporation->setAlliance($alliance);
        $player = (new Player())
            ->setStatus(Player::STATUS_MANAGED)
            ->addCharacter(
                (new Character())
                ->setCorporation($corporation)
                ->addEsiToken((new EsiToken())
                    ->setEveLogin((new EveLogin())->setName(EveLogin::NAME_DEFAULT))
                    ->setValidToken(false)),
            );

        $this->assertFalse($this->service->groupsDeactivated($player));
    }

    public function testGroupsDeactivatedInvalidTokenWithDelay()
    {
        $setting1 = (new SystemVariable(SystemVariable::GROUPS_REQUIRE_VALID_TOKEN))->setValue('1');
        $setting2 = (new SystemVariable(SystemVariable::ACCOUNT_DEACTIVATION_ALLIANCES))->setValue('11');
        $setting3 = (new SystemVariable(SystemVariable::ACCOUNT_DEACTIVATION_CORPORATIONS))->setValue('101');
        $setting4 = (new SystemVariable(SystemVariable::ACCOUNT_DEACTIVATION_DELAY))->setValue('24');
        $this->om->persist($setting1);
        $this->om->persist($setting2);
        $this->om->persist($setting3);
        $this->om->persist($setting4);
        $this->om->flush();

        $alliance = (new Alliance())->setId(11);
        $corporation = (new Corporation())->setId(101);
        $corporation->setAlliance($alliance);
        $player = (new Player())->addCharacter(
            (new Character())
            ->setCorporation($corporation)
            ->addEsiToken((new EsiToken())
                ->setEveLogin((new EveLogin())->setName(EveLogin::NAME_DEFAULT))
                ->setValidToken(false)
                ->setValidTokenTime(new \DateTime("now -12 hours"))),
        );

        $this->assertFalse($this->service->groupsDeactivated($player));
    }

    public function testGroupsDeactivatedInvalidTokenIgnoreDelay()
    {
        $setting1 = (new SystemVariable(SystemVariable::GROUPS_REQUIRE_VALID_TOKEN))->setValue('1');
        $setting2 = (new SystemVariable(SystemVariable::ACCOUNT_DEACTIVATION_ALLIANCES))->setValue('11');
        $setting3 = (new SystemVariable(SystemVariable::ACCOUNT_DEACTIVATION_CORPORATIONS))->setValue('101');
        $setting4 = (new SystemVariable(SystemVariable::ACCOUNT_DEACTIVATION_DELAY))->setValue('24');
        $this->om->persist($setting1);
        $this->om->persist($setting2);
        $this->om->persist($setting3);
        $this->om->persist($setting4);
        $this->om->flush();

        $alliance = (new Alliance())->setId(11);
        $corporation = (new Corporation())->setId(101);
        $corporation->setAlliance($alliance);
        $player = (new Player())->addCharacter(
            (new Character())
            ->setCorporation($corporation)
            ->addEsiToken((new EsiToken())
                ->setEveLogin((new EveLogin())->setName(EveLogin::NAME_DEFAULT))
                ->setValidToken(false)
                ->setValidTokenTime(new \DateTime("now -12 hours"))),
        );

        $this->assertTrue($this->service->groupsDeactivated($player, true));
    }

    public function testGroupsDeactivatedInvalidTokenSettingNotActive()
    {
        $setting2 = (new SystemVariable(SystemVariable::ACCOUNT_DEACTIVATION_ALLIANCES))->setValue('11');
        $setting3 = (new SystemVariable(SystemVariable::ACCOUNT_DEACTIVATION_CORPORATIONS))->setValue('101');
        $this->om->persist($setting2);
        $this->om->persist($setting3);
        $this->om->flush();

        $alliance = (new Alliance())->setId(11);
        $corporation = (new Corporation())->setId(101);
        $corporation->setAlliance($alliance);
        $player = (new Player())->addCharacter(
            (new Character())
            ->setCorporation($corporation)
            ->addEsiToken((new EsiToken())
                ->setEveLogin((new EveLogin())->setName(EveLogin::NAME_DEFAULT))
                ->setValidToken(false)),
        );

        // test with missing setting
        $this->assertFalse($this->service->groupsDeactivated($player));

        // add "deactivated groups" setting set to 0
        $setting1 = (new SystemVariable(SystemVariable::GROUPS_REQUIRE_VALID_TOKEN))->setValue('0');
        $this->om->persist($setting1);
        $this->om->flush();

        $this->assertFalse($this->service->groupsDeactivated($player));
    }

    public function testGetCoreGroups()
    {
        $character = $this->helper->setupDeactivateAccount();

        $player = (new Player())->addGroup(new Group());
        $this->assertEquals([new CoreGroup(0, '')], $this->service->getCoreGroups($player));

        $player->addCharacter($character); // character with invalid ESI token
        $this->assertEquals([], $this->service->getCoreGroups($player));
    }

}
