<?php

declare(strict_types=1);

namespace Tests\Unit\Factory;

use Doctrine\Persistence\ObjectManager;
use Neucore\Entity\Alliance;
use Neucore\Entity\App;
use Neucore\Entity\AppRequests;
use Neucore\Entity\Character;
use Neucore\Entity\CharacterNameChange;
use Neucore\Entity\Corporation;
use Neucore\Entity\CorporationMember;
use Neucore\Entity\EsiLocation;
use Neucore\Entity\EsiToken;
use Neucore\Entity\EsiType;
use Neucore\Entity\EveLogin;
use Neucore\Entity\Group;
use Neucore\Entity\GroupApplication;
use Neucore\Entity\Player;
use Neucore\Entity\PlayerLogins;
use Neucore\Entity\RemovedCharacter;
use Neucore\Entity\Role;
use Neucore\Entity\Plugin;
use Neucore\Entity\SystemVariable;
use Neucore\Entity\Watchlist;
use Neucore\Repository\AllianceRepository;
use Neucore\Repository\AppRepository;
use Neucore\Repository\AppRequestsRepository;
use Neucore\Repository\CharacterNameChangeRepository;
use Neucore\Repository\CharacterRepository;
use Neucore\Repository\CorporationMemberRepository;
use Neucore\Repository\CorporationRepository;
use Neucore\Repository\EsiLocationRepository;
use Neucore\Repository\EsiTokenRepository;
use Neucore\Repository\EsiTypeRepository;
use Neucore\Repository\EveLoginRepository;
use Neucore\Repository\GroupApplicationRepository;
use Neucore\Repository\GroupRepository;
use Neucore\Repository\PlayerLoginsRepository;
use Neucore\Repository\PlayerRepository;
use Neucore\Factory\RepositoryFactory;
use Neucore\Repository\RemovedCharacterRepository;
use Neucore\Repository\RoleRepository;
use Neucore\Repository\PluginRepository;
use Neucore\Repository\SystemVariableRepository;
use Neucore\Repository\WatchlistRepository;
use PHPUnit\Framework\TestCase;
use Tests\Helper;

class RepositoryFactoryTest extends TestCase
{
    private ObjectManager $om;

    private RepositoryFactory $factory;

    protected function setUp(): void
    {
        $this->om = (new Helper())->getObjectManager();
        $this->factory = new RepositoryFactory($this->om);
    }

    public function testGetInstance()
    {
        $this->assertInstanceOf(RepositoryFactory::class, RepositoryFactory::getInstance($this->om));
    }

    public function testGetAllianceRepository()
    {
        $repo = $this->factory->getAllianceRepository();
        $this->assertInstanceOf(AllianceRepository::class, $repo);
        $this->assertSame(Alliance::class, $repo->getClassName());
    }

    public function testGetAppRepository()
    {
        $repo = $this->factory->getAppRepository();
        $this->assertInstanceOf(AppRepository::class, $repo);
        $this->assertSame(App::class, $repo->getClassName());
    }

    public function testGetAppRequestsRepository()
    {
        $repo = $this->factory->getAppRequestsRepository();
        $this->assertInstanceOf(AppRequestsRepository::class, $repo);
        $this->assertSame(AppRequests::class, $repo->getClassName());
    }

    public function testGetCharacterRepository()
    {
        $repo = $this->factory->getCharacterRepository();
        $this->assertInstanceOf(CharacterRepository::class, $repo);
        $this->assertSame(Character::class, $repo->getClassName());
    }

    public function testGetCharacterNameChangeRepository()
    {
        $repo = $this->factory->getCharacterNameChangeRepository();
        $this->assertInstanceOf(CharacterNameChangeRepository::class, $repo);
        $this->assertSame(CharacterNameChange::class, $repo->getClassName());
    }

    public function testGetCorporationRepository()
    {
        $repo = $this->factory->getCorporationRepository();
        $this->assertInstanceOf(CorporationRepository::class, $repo);
        $this->assertSame(Corporation::class, $repo->getClassName());
    }

    public function testGetCorporationMemberRepository()
    {
        $repo = $this->factory->getCorporationMemberRepository();
        $this->assertInstanceOf(CorporationMemberRepository::class, $repo);
        $this->assertSame(CorporationMember::class, $repo->getClassName());
    }

    public function testGetEsiLocationRepository()
    {
        $repo = $this->factory->getEsiLocationRepository();
        $this->assertInstanceOf(EsiLocationRepository::class, $repo);
        $this->assertSame(EsiLocation::class, $repo->getClassName());
    }

    public function testGetEsiTokenRepository()
    {
        $repo = $this->factory->getEsiTokenRepository();
        $this->assertInstanceOf(EsiTokenRepository::class, $repo);
        $this->assertSame(EsiToken::class, $repo->getClassName());
    }

    public function testGetEsiTypeRepository()
    {
        $repo = $this->factory->getEsiTypeRepository();
        $this->assertInstanceOf(EsiTypeRepository::class, $repo);
        $this->assertSame(EsiType::class, $repo->getClassName());
    }

    public function testGetEveLoginRepository()
    {
        $repo = $this->factory->getEveLoginRepository();
        $this->assertInstanceOf(EveLoginRepository::class, $repo);
        $this->assertSame(EveLogin::class, $repo->getClassName());
    }

    public function testGetGroupRepository()
    {
        $repo = $this->factory->getGroupRepository();
        $this->assertInstanceOf(GroupRepository::class, $repo);
        $this->assertSame(Group::class, $repo->getClassName());
    }

    public function testGetGroupApplicationRepository()
    {
        $repo = $this->factory->getGroupApplicationRepository();
        $this->assertInstanceOf(GroupApplicationRepository::class, $repo);
        $this->assertSame(GroupApplication::class, $repo->getClassName());
    }

    public function testGetPlayerRepository()
    {
        $repo = $this->factory->getPlayerRepository();
        $this->assertInstanceOf(PlayerRepository::class, $repo);
        $this->assertSame(Player::class, $repo->getClassName());
    }

    public function testGetPlayerLoginsRepository()
    {
        $repo = $this->factory->getPlayerLoginsRepository();
        $this->assertInstanceOf(PlayerLoginsRepository::class, $repo);
        $this->assertSame(PlayerLogins::class, $repo->getClassName());
    }

    public function testGetRoleRepository()
    {
        $repo = $this->factory->getRoleRepository();
        $this->assertInstanceOf(RoleRepository::class, $repo);
        $this->assertSame(Role::class, $repo->getClassName());
    }

    public function testGetPluginRepository()
    {
        $repo = $this->factory->getPluginRepository();
        $this->assertInstanceOf(PluginRepository::class, $repo);
        $this->assertSame(Plugin::class, $repo->getClassName());
    }

    public function testGetSystemVariableRepository()
    {
        $repo = $this->factory->getSystemVariableRepository();
        $this->assertInstanceOf(SystemVariableRepository::class, $repo);
        $this->assertSame(SystemVariable::class, $repo->getClassName());
    }

    public function testGetRemovedCharacterRepository()
    {
        $repo = $this->factory->getRemovedCharacterRepository();
        $this->assertInstanceOf(RemovedCharacterRepository::class, $repo);
        $this->assertSame(RemovedCharacter::class, $repo->getClassName());
    }

    public function testGetWatchlistRepository()
    {
        $repo = $this->factory->getWatchlistRepository();
        $this->assertInstanceOf(WatchlistRepository::class, $repo);
        $this->assertSame(Watchlist::class, $repo->getClassName());
    }
}
