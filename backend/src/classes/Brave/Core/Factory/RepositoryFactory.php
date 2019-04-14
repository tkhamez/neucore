<?php declare(strict_types=1);

namespace Brave\Core\Factory;

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
use Brave\Core\Repository\RemovedCharacterRepository;
use Brave\Core\Repository\RoleRepository;
use Brave\Core\Repository\SystemVariableRepository;
use Doctrine\Common\Persistence\ObjectManager;

class RepositoryFactory
{
    private static $instance;

    private $objectManager;

    private $factories = [];

    public static function getInstance(ObjectManager $objectManager): self
    {
        if (self::$instance === null) {
            self::$instance = new self($objectManager);
        }
        return self::$instance;
    }

    public function __construct(ObjectManager $objectManager)
    {
        self::$instance = $this;
        $this->objectManager = $objectManager;
    }

    public function getAllianceRepository(): AllianceRepository
    {
        return $this->getRepository(AllianceRepository::class, Alliance::class);
    }

    public function getAppRepository(): AppRepository
    {
        return $this->getRepository(AppRepository::class, App::class);
    }

    public function getCharacterRepository(): CharacterRepository
    {
        return $this->getRepository(CharacterRepository::class, Character::class);
    }

    public function getCorporationRepository(): CorporationRepository
    {
        return $this->getRepository(CorporationRepository::class, Corporation::class);
    }

    public function getCorporationMemberRepository(): CorporationMemberRepository
    {
        return $this->getRepository(CorporationMemberRepository::class, CorporationMember::class);
    }

    public function getGroupRepository(): GroupRepository
    {
        return $this->getRepository(GroupRepository::class, Group::class);
    }

    public function getGroupApplicationRepository(): GroupApplicationRepository
    {
        return $this->getRepository(GroupApplicationRepository::class, GroupApplication::class);
    }

    public function getPlayerRepository(): PlayerRepository
    {
        return $this->getRepository(PlayerRepository::class, Player::class);
    }

    public function getRoleRepository(): RoleRepository
    {
        return $this->getRepository(RoleRepository::class, Role::class);
    }

    public function getSystemVariableRepository(): SystemVariableRepository
    {
        return $this->getRepository(SystemVariableRepository::class, SystemVariable::class);
    }

    public function getRemovedCharacterRepository(): RemovedCharacterRepository
    {
        return $this->getRepository(RemovedCharacterRepository::class, RemovedCharacter::class);
    }

    private function getRepository(string $repositoryClass, string $entityClass)
    {
        if (! isset($this->factories[$repositoryClass])) {
            $metadata = $this->objectManager->getClassMetadata($entityClass);
            $repository = new $repositoryClass($this->objectManager, $metadata);
            $this->factories[$repositoryClass] = $repository;
        }
        return $this->factories[$repositoryClass];
    }
}
