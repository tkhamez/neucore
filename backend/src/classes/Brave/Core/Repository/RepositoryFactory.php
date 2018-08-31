<?php declare(strict_types=1);

namespace Brave\Core\Repository;

use Brave\Core\Entity\Alliance;
use Brave\Core\Entity\App;
use Brave\Core\Entity\Character;
use Brave\Core\Entity\Corporation;
use Brave\Core\Entity\Group;
use Brave\Core\Entity\Player;
use Brave\Core\Entity\Role;
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

    public function getGroupRepository(): GroupRepository
    {
        return $this->getInstance(GroupRepository::class, Group::class);
    }

    public function getPlayerRepository(): PlayerRepository
    {
        return $this->getInstance(PlayerRepository::class, Player::class);
    }

    public function getRoleRepository(): RoleRepository
    {
        return $this->getInstance(RoleRepository::class, Role::class);
    }

    private function getInstance(string $repositoryClass, string $entityClass)
    {
        if (! isset($this->instance[$repositoryClass])) {
            $metadata = $this->em->getClassMetadata($entityClass);
            $repository = null;
            if ($repositoryClass === AllianceRepository::class) {
                $repository = new AllianceRepository($this->em, $metadata);
            } elseif ($repositoryClass === AppRepository::class) {
                $repository = new AppRepository($this->em, $metadata);
            } elseif ($repositoryClass === CharacterRepository::class) {
                $repository = new CharacterRepository($this->em, $metadata);
            } elseif ($repositoryClass === CorporationRepository::class) {
                $repository = new CorporationRepository($this->em, $metadata);
            } elseif ($repositoryClass === GroupRepository::class) {
                $repository = new GroupRepository($this->em, $metadata);
            } elseif ($repositoryClass === PlayerRepository::class) {
                $repository = new PlayerRepository($this->em, $metadata);
            } elseif ($repositoryClass === RoleRepository::class) {
                $repository = new RoleRepository($this->em, $metadata);
            }
            $this->instance[$repositoryClass] = $repository;
        }
        return $this->instance[$repositoryClass];
    }
}
