<?php declare(strict_types=1);

namespace Tests\Unit\Core\Factory;

use Brave\Core\Entity\Alliance;
use Brave\Core\Entity\App;
use Brave\Core\Entity\Character;
use Brave\Core\Entity\Corporation;
use Brave\Core\Entity\CorporationMember;
use Brave\Core\Entity\Group;
use Brave\Core\Entity\GroupApplication;
use Brave\Core\Entity\Player;
use Brave\Core\Entity\RemovedCharacter;
use Brave\Core\Entity\Role;
use Brave\Core\Entity\SystemVariable;
use Brave\Core\Repository\AllianceRepository;
use Brave\Core\Repository\AppRepository;
use Brave\Core\Repository\CharacterRepository;
use Brave\Core\Repository\CorporationMemberRepository;
use Brave\Core\Repository\CorporationRepository;
use Brave\Core\Repository\GroupApplicationRepository;
use Brave\Core\Repository\GroupRepository;
use Brave\Core\Repository\PlayerRepository;
use Brave\Core\Factory\RepositoryFactory;
use Brave\Core\Repository\RemovedCharacterRepository;
use Brave\Core\Repository\RoleRepository;
use Brave\Core\Repository\SystemVariableRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Tests\Helper;

class RepositoryFactoryTest extends TestCase
{
    /**
     * @var EntityManagerInterface
     */
    private $em;

    /**
     * @var RepositoryFactory
     */
    private $factory;

    public function setUp()
    {
        $this->em = (new Helper())->getEm();
        $this->factory = new RepositoryFactory($this->em);
    }

    public function testGetInstance()
    {
        $this->assertInstanceOf(RepositoryFactory::class, RepositoryFactory::getInstance($this->em));
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

    public function testGetCorporationMemberRepository()
    {
        $repo = $this->factory->getCorporationMemberRepository();
        $this->assertInstanceOf(CorporationMemberRepository::class, $repo);
        $this->assertSame(CorporationMember::class, $repo->getClassName());
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

    public function testGetRoleRepository()
    {
        $repo = $this->factory->getRoleRepository();
        $this->assertInstanceOf(RoleRepository::class, $repo);
        $this->assertSame(Role::class, $repo->getClassName());
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
}
