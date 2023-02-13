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

    private static int $playerId;

    private AccountInterface $account;

    public static function setUpBeforeClass(): void
    {
        self::$helper = new Helper();
        self::$helper->emptyDb();

        $groups = self::$helper->addGroups(['G1', 'G2', 'G3']);
        $player = self::$helper->addCharacterMain('Main', 102030, [Role::USER, Role::GROUP_MANAGER])->getPlayer();
        self::$helper->addCharacterToPlayer('Alt 1', 102031, $player);
        self::$helper->setupDeactivateAccount($player, 102032, 'Alt 2');
        self::$playerId = $player->getId();
        $player->addGroup($groups[0]);
        $player->addGroup($groups[1]);
        $groups[2]->addManager($player);

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

    public function testGetAccount()
    {
        $this->assertInstanceOf(CoreAccount::class, $this->account->getAccount(self::$playerId));
        $this->assertTrue($this->account->getAccount(self::$playerId)->groupsDeactivated);
        $this->assertNull($this->account->getAccount(self::$playerId + 2));
    }

    public function testGetMain()
    {
        $this->assertInstanceOf(CoreCharacter::class, $this->account->getMain(self::$playerId));
        $this->assertSame(102030, $this->account->getMain(self::$playerId)->id);
        $this->assertNull($this->account->getMain(self::$playerId +2));
    }

    public function testGetCharacters()
    {
        $this->assertSame(3, count($this->account->getCharacters(self::$playerId)));
        $this->assertInstanceOf(CoreCharacter::class, $this->account->getCharacters(self::$playerId)[0]);
        $this->assertSame([], $this->account->getCharacters(self::$playerId +2));
    }

    public function testGetMemberGroups()
    {
        $this->assertSame(2, count($this->account->getMemberGroups(self::$playerId)));
        $this->assertInstanceOf(CoreGroup::class, $this->account->getMemberGroups(self::$playerId)[0]);
        $this->assertSame('G1', $this->account->getMemberGroups(self::$playerId)[0]->name);
        $this->assertSame('G2', $this->account->getMemberGroups(self::$playerId)[1]->name);
        $this->assertSame([], $this->account->getMemberGroups(self::$playerId + 2));
    }

    public function testGroupsDeactivated()
    {
        $this->assertTrue($this->account->groupsDeactivated(self::$playerId));
        $this->assertFalse($this->account->groupsDeactivated(self::$playerId + 2));
    }

    public function testManagerGroups()
    {
        $this->assertSame(1, count($this->account->getManagerGroups(self::$playerId)));
        $this->assertInstanceOf(CoreGroup::class, $this->account->getManagerGroups(self::$playerId)[0]);
        $this->assertSame('G3', $this->account->getManagerGroups(self::$playerId)[0]->name);
        $this->assertSame([], $this->account->getManagerGroups(self::$playerId + 2));
    }

    public function testGetRoles()
    {
        $this->assertSame(2, count($this->account->getRoles(self::$playerId)));
        $this->assertInstanceOf(CoreRole::class, $this->account->getRoles(self::$playerId)[0]);
        $this->assertSame(Role::GROUP_MANAGER, $this->account->getRoles(self::$playerId)[0]->name);
        $this->assertSame(Role::USER, $this->account->getRoles(self::$playerId)[1]->name);
        $this->assertSame([], $this->account->getRoles(self::$playerId + 2));
    }
}
