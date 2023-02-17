<?php

declare(strict_types=1);

namespace Tests\Unit\Plugin\Core;

use Neucore\Entity\Role;
use Neucore\Factory\RepositoryFactory;
use Neucore\Plugin\Core\Account;
use Neucore\Plugin\Core\AccountInterface;
use Neucore\Plugin\Data\CoreAccount;
use Neucore\Plugin\Data\CoreCharacter;
use Neucore\Plugin\Data\CoreGroup;
use Neucore\Plugin\Data\CoreRole;
use Neucore\Service\AccountGroup;
use PHPUnit\Framework\TestCase;
use Tests\Helper;

class AccountTest extends TestCase
{
    private static Helper $helper;

    private static int $player1Id;

    private static int $player2Id;

    private static int $group1Id;

    private static int $group3Id;

    private AccountInterface $account;

    public static function setUpBeforeClass(): void
    {
        self::$helper = new Helper();
        self::$helper->emptyDb();

        $groups = self::$helper->addGroups(['G1', 'G2', 'G3']);
        $player1 = self::$helper->addCharacterMain('Main', 102030, [Role::USER, Role::GROUP_MANAGER])->getPlayer();
        self::$helper->addCharacterToPlayer('Alt 1', 102031, $player1);
        self::$helper->setupDeactivateAccount($player1, 102032, 'Alt 2');
        self::$player1Id = $player1->getId();
        self::$player2Id = self::$helper->addCharacterMain('Main', 405060)->setMain(false)->getPlayer()->getId();
        self::$group1Id = $groups[0]->getId();
        self::$group3Id = $groups[2]->getId();
        $player1->addGroup($groups[0]);
        $player1->addGroup($groups[1]);
        $player1->addManagerGroup($groups[1]);
        $groups[2]->addManager($player1);

        self::$helper->getEm()->flush();
        self::$helper->getEm()->clear();
    }

    protected function setUp(): void
    {
        $repositoryFactory = new RepositoryFactory(self::$helper->getEm());
        $this->account = new Account(
            $repositoryFactory,
            new AccountGroup($repositoryFactory, self::$helper->getEm())
        );
    }

    /**
     * @phan-suppress PhanTypeArraySuspiciousNullable
     */
    public function testGetAccountsByGroup()
    {
        $this->assertNull($this->account->getAccountsByGroup(99));

        $result = $this->account->getAccountsByGroup(self::$group1Id);
        $this->assertSame(1, count($result));
        $this->assertSame(self::$player1Id, $result[0]->playerId);
        $this->assertSame('Main', $result[0]->playerName);
        $this->assertNull($result[0]->main);
        $this->assertNull($result[0]->characters);
        $this->assertNull($result[0]->memberGroups);
        $this->assertNull($result[0]->managerGroups);
        $this->assertNull($result[0]->roles);
    }

    /**
     * @phan-suppress PhanTypeArraySuspiciousNullable
     */
    public function testGetAccountsByGroupManager()
    {
        $this->assertNull($this->account->getAccountsByGroupManager(99));

        $this->assertSame(0, count($this->account->getAccountsByGroupManager(self::$group1Id)));
        $result = $this->account->getAccountsByGroupManager(self::$group3Id);
        $this->assertSame(1, count($result));
        $this->assertSame(self::$player1Id, $result[0]->playerId);
    }

    /**
     * @phan-suppress PhanTypeArraySuspiciousNullable
     */
    public function testGetAccountsByRole()
    {
        $this->assertNull($this->account->getAccountsByRole(Role::USER));
        $this->assertNull($this->account->getAccountsByRole('invalid'));

        $result = $this->account->getAccountsByRole(CoreRole::GROUP_MANAGER);
        $this->assertSame(1, count($result));
        $this->assertSame(self::$player1Id, $result[0]->playerId);
    }

    public function testGetAccount()
    {
        $this->assertNull($this->account->getAccount(self::$player1Id + 22));
        $this->assertNull($this->account->getAccount(self::$player2Id)); // no main

        $this->assertInstanceOf(CoreAccount::class, $this->account->getAccount(self::$player1Id));
        $this->assertTrue($this->account->getAccount(self::$player1Id)->groupsDeactivated);
    }

    public function testGetMain()
    {
        $this->assertNull($this->account->getMain(self::$player1Id + 22));

        $this->assertInstanceOf(CoreCharacter::class, $this->account->getMain(self::$player1Id));
        $this->assertSame(102030, $this->account->getMain(self::$player1Id)->id);
    }

    /**
     * @phan-suppress PhanTypeArraySuspiciousNullable
     */
    public function testGetCharacters()
    {
        $this->assertNull($this->account->getCharacters(self::$player1Id + 22));

        $this->assertSame(3, count($this->account->getCharacters(self::$player1Id)));
        $this->assertInstanceOf(CoreCharacter::class, $this->account->getCharacters(self::$player1Id)[0]);
    }

    /**
     * @phan-suppress PhanTypeArraySuspiciousNullable
     */
    public function testGetMemberGroups()
    {
        $this->assertNull($this->account->getMemberGroups(self::$player1Id + 22));

        $this->assertSame(2, count($this->account->getMemberGroups(self::$player1Id)));
        $this->assertInstanceOf(CoreGroup::class, $this->account->getMemberGroups(self::$player1Id)[0]);
        $this->assertSame('G1', $this->account->getMemberGroups(self::$player1Id)[0]->name);
        $this->assertSame('G2', $this->account->getMemberGroups(self::$player1Id)[1]->name);
    }

    public function testGroupsDeactivated()
    {
        $this->assertNull($this->account->groupsDeactivated(self::$player1Id + 2));

        $this->assertTrue($this->account->groupsDeactivated(self::$player1Id));
    }

    /**
     * @phan-suppress PhanTypeArraySuspiciousNullable
     */
    public function testManagerGroups()
    {
        $this->assertNull($this->account->getManagerGroups(self::$player1Id + 2));

        $this->assertSame(1, count($this->account->getManagerGroups(self::$player1Id)));
        $this->assertInstanceOf(CoreGroup::class, $this->account->getManagerGroups(self::$player1Id)[0]);
        $this->assertSame('G3', $this->account->getManagerGroups(self::$player1Id)[0]->name);
    }

    /**
     * @phan-suppress PhanTypeArraySuspiciousNullable
     */
    public function testGetRoles()
    {
        $this->assertNull($this->account->getRoles(self::$player1Id + 2));

        $this->assertSame(2, count($this->account->getRoles(self::$player1Id)));
        $this->assertInstanceOf(CoreRole::class, $this->account->getRoles(self::$player1Id)[0]);
        $this->assertSame(Role::GROUP_MANAGER, $this->account->getRoles(self::$player1Id)[0]->name);
        $this->assertSame(Role::USER, $this->account->getRoles(self::$player1Id)[1]->name);
    }
}
