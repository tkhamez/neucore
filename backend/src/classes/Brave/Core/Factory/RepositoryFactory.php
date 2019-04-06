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
use Doctrine\ORM\EntityManagerInterface;

class RepositoryFactory
{
    private $em;

    private $instance = [];

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function getAllianceRepository(): AllianceRepository
    {
        return $this->getInstance(AllianceRepository::class, Alliance::class);
    }

    public function getAppRepository(): AppRepository
    {
        return $this->getInstance(AppRepository::class, App::class);
    }

    public function getCharacterRepository(): CharacterRepository
    {
        return $this->getInstance(CharacterRepository::class, Character::class);
    }

    public function getCorporationRepository(): CorporationRepository
    {
        return $this->getInstance(CorporationRepository::class, Corporation::class);
    }

    public function getCorporationMemberRepository(): CorporationMemberRepository
    {
        return $this->getInstance(CorporationMemberRepository::class, CorporationMember::class);
    }

    public function getGroupRepository(): GroupRepository
    {
        return $this->getInstance(GroupRepository::class, Group::class);
    }

    public function getGroupApplicationRepository(): GroupApplicationRepository
    {
        return $this->getInstance(GroupApplicationRepository::class, GroupApplication::class);
    }

    public function getPlayerRepository(): PlayerRepository
    {
        return $this->getInstance(PlayerRepository::class, Player::class);
    }

    public function getRoleRepository(): RoleRepository
    {
        return $this->getInstance(RoleRepository::class, Role::class);
    }

    public function getSystemVariableRepository(): SystemVariableRepository
    {
        return $this->getInstance(SystemVariableRepository::class, SystemVariable::class);
    }

    public function getRemovedCharacterRepository(): RemovedCharacterRepository
    {
        return $this->getInstance(RemovedCharacterRepository::class, RemovedCharacter::class);
    }

    private function getInstance(string $repositoryClass, string $entityClass)
    {
        if (! isset($this->instance[$repositoryClass])) {
            $metadata = $this->em->getClassMetadata($entityClass);
            $repository = new $repositoryClass($this->em, $metadata);
            $this->instance[$repositoryClass] = $repository;
        }
        return $this->instance[$repositoryClass];
    }
}
