<?php

namespace Tests\Unit\Core\Repository;

use Brave\Core\Entity\Alliance;
use Brave\Core\Entity\App;
use Brave\Core\Entity\Character;
use Brave\Core\Entity\Corporation;
use Brave\Core\Entity\Group;
use Brave\Core\Entity\Player;
use Brave\Core\Entity\Role;
use Brave\Core\Repository\AllianceRepository;
use Brave\Core\Repository\AppRepository;
use Brave\Core\Repository\CharacterRepository;
use Brave\Core\Repository\CorporationRepository;
use Brave\Core\Repository\GroupRepository;
use Brave\Core\Repository\PlayerRepository;
use Brave\Core\Repository\RepositoryFactory;
use Brave\Core\Repository\RoleRepository;
use Tests\Helper;

class RepositoryFactoryTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var RepositoryFactory
     */
    private $factory;

    public function setUp()
    {
        $em = (new Helper())->getEm();
        $this->factory = new RepositoryFactory($em);
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

    public function testGetCharacterRepository()
    {
        $repo = $this->factory->getCharacterRepository();

        $this->assertInstanceOf(CharacterRepository::class, $repo);
        $this->assertSame(Character::class, $repo->getClassName());
    }

    public function testGetCorporationRepository()
    {
        $repo = $this->factory->getCorporationRepository();

        $this->assertInstanceOf(CorporationRepository::class, $repo);
        $this->assertSame(Corporation::class, $repo->getClassName());
    }

    public function testGetGroupRepository()
    {
        $repo = $this->factory->getGroupRepository();

        $this->assertInstanceOf(GroupRepository::class, $repo);
        $this->assertSame(Group::class, $repo->getClassName());
    }

    public function testGetPlayerRepository()
    {
        $repo = $this->factory->getPlayerRepository();

        $this->assertInstanceOf(PlayerRepository::class, $repo);
        $this->assertSame(Player::class, $repo->getClassName());
    }

    public function testGetRoleRepository()
    {
        $repo = $this->factory->getRoleRepository();

        $this->assertInstanceOf(RoleRepository::class, $repo);
        $this->assertSame(Role::class, $repo->getClassName());
    }
}
