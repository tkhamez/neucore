<?php

declare(strict_types=1);

namespace Neucore\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="watchlists")
 */
class Watchlist
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="NONE")
     * @var integer
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=32)
     * @var string
     */
    private $name;

    /**
     * Player accounts that are white listed.
     *
     * @ORM\ManyToMany(targetEntity="Player")
     * @ORM\JoinTable(name="watchlist_exemption")
     * @ORM\OrderBy({"name" = "ASC"})
     * @var Collection
     */
    private $exemptions;

    /**
     * Corporations whose members are on this list if their player accounts also have
     * members in other corporations that are not on this list.
     *
     * @ORM\ManyToMany(targetEntity="Corporation")
     * @ORM\OrderBy({"name" = "ASC"})
     * @var Collection
     */
    private $corporations;

    /**
     * Same as $corporations but for alliances.
     *
     * @ORM\ManyToMany(targetEntity="Alliance")
     * @ORM\OrderBy({"name" = "ASC"})
     * @var Collection
     */
    private $alliances;

    /**
     * Members of theses groups have read access to this list.
     *
     * @ORM\ManyToMany(targetEntity="Group")
     * @ORM\OrderBy({"name" = "ASC"})
     * @var Collection
     */
    private $groups;

    /**
     * Accounts that are on the list and have members in one of these corporations
     * are moved to the blacklist.
     *
     * @ORM\ManyToMany(targetEntity="Corporation")
     * @ORM\JoinTable(name="watchlist_blacklist_corporation")
     * @ORM\OrderBy({"name" = "ASC"})
     * @var Collection
     */
    private $blacklistCorporations;

    /**
     * Same as $blacklistCorporations but for alliances.
     *
     * @ORM\ManyToMany(targetEntity="Alliance")
     * @ORM\JoinTable(name="watchlist_blacklist_alliance")
     * @ORM\OrderBy({"name" = "ASC"})
     * @var Collection
     */
    private $blacklistAlliances;

    /**
     * Corporations that should be treated like NPC corporations, for example personal alt corps.
     * Accounts will not be added to the list is they have a character in one of these.
     *
     * @ORM\ManyToMany(targetEntity="Corporation")
     * @ORM\JoinTable(name="watchlist_whitelist_corporation")
     * @ORM\OrderBy({"name" = "ASC"})
     * @var Collection
     */
    private $whitelistCorporations;

    /**
     * Same as $whitelistCorporations but for alliances.
     *
     * @ORM\ManyToMany(targetEntity="Alliance")
     * @ORM\JoinTable(name="watchlist_whitelist_alliance")
     * @ORM\OrderBy({"name" = "ASC"})
     * @var Collection
     */
    private $whitelistAlliances;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->exemptions = new ArrayCollection();
        $this->corporations = new ArrayCollection();
        $this->alliances = new ArrayCollection();
        $this->groups = new ArrayCollection();
        $this->blacklistCorporations = new ArrayCollection();
        $this->blacklistAlliances = new ArrayCollection();
        $this->whitelistCorporations = new ArrayCollection();
        $this->whitelistAlliances = new ArrayCollection();
    }

    public function setId(int $id): Watchlist
    {
        $this->id = $id;

        return $this;
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    public function setName(string $name): Watchlist
    {
        $this->name = $name;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function addExemption(Player $exemption): Watchlist
    {
        foreach ($this->getExemptions() as $entity) {
            if ($entity->getId() === $exemption->getId()) {
                return $this;
            }
        }
        $this->exemptions[] = $exemption;

        return $this;
    }

    public function removeExemption(Player $exemption): bool
    {
        return $this->exemptions->removeElement($exemption);
    }

    /**
     * @return Player[]
     */
    public function getExemptions(): array
    {
        return $this->exemptions->toArray();
    }

    public function addCorporation(Corporation $corporation): Watchlist
    {
        foreach ($this->getCorporations() as $entity) {
            if ($entity->getId() === $corporation->getId()) {
                return $this;
            }
        }
        $this->corporations[] = $corporation;

        return $this;
    }

    public function removeCorporation(Corporation $corporation): bool
    {
        return $this->corporations->removeElement($corporation);
    }

    /**
     * @return Corporation[]
     */
    public function getCorporations(): array
    {
        return $this->corporations->toArray();
    }

    public function addAlliance(Alliance $alliance): Watchlist
    {
        foreach ($this->getAlliances() as $entity) {
            if ($entity->getId() === $alliance->getId()) {
                return $this;
            }
        }
        $this->alliances[] = $alliance;

        return $this;
    }

    public function removeAlliance(Alliance $alliance): bool
    {
        return $this->alliances->removeElement($alliance);
    }

    /**
     * @return Alliance[]
     */
    public function getAlliances(): array
    {
        return $this->alliances->toArray();
    }

    public function addGroup(Group $group): Watchlist
    {
        foreach ($this->getGroups() as $entity) {
            if ($entity->getId() === $group->getId()) {
                return $this;
            }
        }
        $this->groups[] = $group;

        return $this;
    }

    public function removeGroup(Group $group): bool
    {
        return $this->groups->removeElement($group);
    }

    /**
     * @return Group[]
     */
    public function getGroups(): array
    {
        return $this->groups->toArray();
    }

    public function addBlacklistCorporation(Corporation $blacklistCorporation): self
    {
        foreach ($this->getBlacklistCorporations() as $entity) {
            if ($entity->getId() === $blacklistCorporation->getId()) {
                return $this;
            }
        }

        $this->blacklistCorporations[] = $blacklistCorporation;

        return $this;
    }

    /**
     * @return boolean TRUE if this collection contained the specified element, FALSE otherwise.
     */
    public function removeBlacklistCorporation(Corporation $blacklistCorporation): bool
    {
        return $this->blacklistCorporations->removeElement($blacklistCorporation);
    }

    /**
     * @return Corporation[]
     */
    public function getBlacklistCorporations(): array
    {
        return $this->blacklistCorporations->toArray();
    }

    public function addBlacklistAlliance(Alliance $blacklistAlliance): self
    {
        foreach ($this->getBlacklistAlliances() as $entity) {
            if ($entity->getId() === $blacklistAlliance->getId()) {
                return $this;
            }
        }
        $this->blacklistAlliances[] = $blacklistAlliance;

        return $this;
    }

    /**
     * @return boolean TRUE if this collection contained the specified element, FALSE otherwise.
     */
    public function removeBlacklistAlliance(Alliance $blacklistAlliance): bool
    {
        return $this->blacklistAlliances->removeElement($blacklistAlliance);
    }

    /**
     * @return Alliance[]
     */
    public function getBlacklistAlliances(): array
    {
        return $this->blacklistAlliances->toArray();
    }

    public function addWhitelistCorporation(Corporation $whitelistCorporation): self
    {
        foreach ($this->getWhitelistCorporations() as $entity) {
            if ($entity->getId() === $whitelistCorporation->getId()) {
                return $this;
            }
        }

        $this->whitelistCorporations[] = $whitelistCorporation;

        return $this;
    }

    /**
     * @return boolean TRUE if this collection contained the specified element, FALSE otherwise.
     */
    public function removeWhitelistCorporation(Corporation $whitelistCorporation): bool
    {
        return $this->whitelistCorporations->removeElement($whitelistCorporation);
    }

    /**
     * @return Corporation[]
     */
    public function getWhitelistCorporations(): array
    {
        return $this->whitelistCorporations->toArray();
    }

    public function addWhitelistAlliance(Alliance $whitelistAlliance): self
    {
        foreach ($this->getWhitelistAlliances() as $entity) {
            if ($entity->getId() === $whitelistAlliance->getId()) {
                return $this;
            }
        }

        $this->whitelistAlliances[] = $whitelistAlliance;

        return $this;
    }

    /**
     * @return boolean TRUE if this collection contained the specified element, FALSE otherwise.
     */
    public function removeWhitelistAlliance(Alliance $whitelistAlliance): bool
    {
        return $this->whitelistAlliances->removeElement($whitelistAlliance);
    }

    /**
     * @return Alliance[]
     */
    public function getWhitelistAlliances(): array
    {
        return $this->whitelistAlliances->toArray();
    }
}
