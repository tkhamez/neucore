<?php

declare(strict_types=1);

namespace Neucore\Factory;

use Doctrine\Persistence\ObjectManager;
use Neucore\Entity\Alliance;
use Neucore\Entity\App;
use Neucore\Entity\Character;
use Neucore\Entity\Corporation;
use Neucore\Entity\CorporationMember;
use Neucore\Entity\EsiLocation;
use Neucore\Entity\EsiType;
use Neucore\Entity\Group;
use Neucore\Entity\GroupApplication;
use Neucore\Entity\Player;
use Neucore\Entity\PlayerLogins;
use Neucore\Entity\RemovedCharacter;
use Neucore\Entity\Role;
use Neucore\Entity\SystemVariable;
use Neucore\Entity\Watchlist;
use Neucore\Repository\AllianceRepository;
use Neucore\Repository\AppRepository;
use Neucore\Repository\CharacterRepository;
use Neucore\Repository\CorporationMemberRepository;
use Neucore\Repository\CorporationRepository;
use Neucore\Repository\EsiLocationRepository;
use Neucore\Repository\EsiTypeRepository;
use Neucore\Repository\GroupApplicationRepository;
use Neucore\Repository\GroupRepository;
use Neucore\Repository\PlayerLoginsRepository;
use Neucore\Repository\PlayerRepository;
use Neucore\Repository\RemovedCharacterRepository;
use Neucore\Repository\RoleRepository;
use Neucore\Repository\SystemVariableRepository;
use Neucore\Repository\WatchlistRepository;

class RepositoryFactory
{
    /**
     * @var RepositoryFactory
     */
    private static $instance;

    /**
     * @var ObjectManager
     */
    private $objectManager;

    /**
     * @var array
     */
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

    public function getEsiLocationRepository(): EsiLocationRepository
    {
        return $this->getRepository(EsiLocationRepository::class, EsiLocation::class);
    }

    public function getEsiTypeRepository(): EsiTypeRepository
    {
        return $this->getRepository(EsiTypeRepository::class, EsiType::class);
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

    public function getPlayerLoginsRepository(): PlayerLoginsRepository
    {
        return $this->getRepository(PlayerLoginsRepository::class, PlayerLogins::class);
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

    public function getWatchlistRepository(): WatchlistRepository
    {
        return $this->getRepository(WatchlistRepository::class, Watchlist::class);
    }

    /**
     * @param string $repositoryClass
     * @param string $entityClass
     * @return mixed
     */
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
