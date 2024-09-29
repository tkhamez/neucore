<?php

declare(strict_types=1);

namespace Neucore\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use OpenApi\Attributes as OA;


#[ORM\Entity]
#[ORM\Table(name: "watchlists", options: ["charset" => "utf8mb4", "collate" => "utf8mb4_unicode_520_ci"])]
#[OA\Schema(required: ['id', 'name'])]
class Watchlist implements \JsonSerializable
{
    #[ORM\Id]
    #[ORM\Column(type: "integer")]
    #[ORM\GeneratedValue]
    #[OA\Property]
    private ?int $id = null;

    #[ORM\Column(type: "string", length: 32)]
    #[OA\Property]
    private ?string $name = null;

    #[ORM\Column(name: "lock_watchlist_settings", type: "boolean")]
    #[OA\Property]
    private bool $lockWatchlistSettings = false;

    /**
     * Player accounts that are on the allowlist.
     *
     */
    #[ORM\ManyToMany(targetEntity: Player::class)]
    #[ORM\JoinTable(name: "watchlist_exemption")]
    #[ORM\OrderBy(["name" => "ASC"])]
    private Collection $exemptions;

    /**
     * Corporations whose members are on this list if their player accounts also have
     * members in other corporations that are not on this list.
     *
     */
    #[ORM\ManyToMany(targetEntity: Corporation::class)]
    #[ORM\OrderBy(["name" => "ASC"])]
    private Collection $corporations;

    /**
     * Same as $corporations but for alliances.
     *
     */
    #[ORM\ManyToMany(targetEntity: Alliance::class)]
    #[ORM\OrderBy(["name" => "ASC"])]
    private Collection $alliances;

    /**
     * Members of these groups have read access to this list.
     *
     */
    #[ORM\ManyToMany(targetEntity: Group::class)]
    #[ORM\OrderBy(["name" => "ASC"])]
    private Collection $groups;

    /**
     * Members of these groups can change settings for this list.
     *
     */
    #[ORM\ManyToMany(targetEntity: Group::class)]
    #[ORM\JoinTable(name: "watchlist_manager_group")]
    #[ORM\OrderBy(["name" => "ASC"])]
    private Collection $managerGroups;

    /**
     * Accounts that are on the list and have members in one of these corporations
     * are moved to the kicklist.
     *
     */
    #[ORM\ManyToMany(targetEntity: Corporation::class)]
    #[ORM\JoinTable(name: "watchlist_kicklist_corporation")]
    #[ORM\OrderBy(["name" => "ASC"])]
    private Collection $kicklistCorporations;

    /**
     * Same as $kicklistCorporations but for alliances.
     *
     */
    #[ORM\ManyToMany(targetEntity: Alliance::class)]
    #[ORM\JoinTable(name: "watchlist_kicklist_alliance")]
    #[ORM\OrderBy(["name" => "ASC"])]
    private Collection $kicklistAlliances;

    /**
     * Corporations that should be treated like NPC corporations, for example personal alt corps.
     * Accounts will not be added to the list is they have a character in one of these.
     *
     */
    #[ORM\ManyToMany(targetEntity: Corporation::class)]
    #[ORM\JoinTable(name: "watchlist_allowlist_corporation")]
    #[ORM\OrderBy(["name" => "ASC"])]
    private Collection $allowlistCorporations;

    /**
     * Same as $allowlistCorporations but for alliances.
     *
     */
    #[ORM\ManyToMany(targetEntity: Alliance::class)]
    #[ORM\JoinTable(name: "watchlist_allowlist_alliance")]
    #[ORM\OrderBy(["name" => "ASC"])]
    private Collection $allowlistAlliances;

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'lockWatchlistSettings' => $this->lockWatchlistSettings,
        ];
    }

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->exemptions = new ArrayCollection();
        $this->corporations = new ArrayCollection();
        $this->alliances = new ArrayCollection();
        $this->groups = new ArrayCollection();
        $this->managerGroups = new ArrayCollection();
        $this->kicklistCorporations = new ArrayCollection();
        $this->kicklistAlliances = new ArrayCollection();
        $this->allowlistCorporations = new ArrayCollection();
        $this->allowlistAlliances = new ArrayCollection();
    }

    public function getId(): int
    {
        return (int) $this->id;
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

    public function setLockWatchlistSettings(bool $lockWatchlistSettings): self
    {
        $this->lockWatchlistSettings = $lockWatchlistSettings;

        return $this;
    }

    public function getLockWatchlistSettings(): bool
    {
        return $this->lockWatchlistSettings;
    }

    public function addExemption(Player $exemption): Watchlist
    {
        foreach ($this->getExemptions() as $entity) {
            if ($exemption->getId() && $entity->getId() === $exemption->getId()) {
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
        return array_values($this->exemptions->toArray());
    }

    public function addCorporation(Corporation $corporation): Watchlist
    {
        foreach ($this->getCorporations() as $entity) {
            if ($corporation->getId() && $entity->getId() === $corporation->getId()) {
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
        return array_values($this->corporations->toArray());
    }

    public function addAlliance(Alliance $alliance): Watchlist
    {
        foreach ($this->getAlliances() as $entity) {
            if ($alliance->getId() && $entity->getId() === $alliance->getId()) {
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
        return array_values($this->alliances->toArray());
    }

    public function addGroup(Group $group): Watchlist
    {
        foreach ($this->getGroups() as $entity) {
            if ($group->getId() && $entity->getId() === $group->getId()) {
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
        return array_values($this->groups->toArray());
    }

    public function addManagerGroup(Group $group): Watchlist
    {
        foreach ($this->getManagerGroups() as $entity) {
            if ($group->getId() && $entity->getId() === $group->getId()) {
                return $this;
            }
        }
        $this->managerGroups[] = $group;

        return $this;
    }

    public function removeManagerGroup(Group $group): bool
    {
        return $this->managerGroups->removeElement($group);
    }

    /**
     * @return Group[]
     */
    public function getManagerGroups(): array
    {
        return array_values($this->managerGroups->toArray());
    }

    public function addKicklistCorporation(Corporation $kicklistCorporation): self
    {
        foreach ($this->getKicklistCorporations() as $entity) {
            if ($kicklistCorporation->getId() && $entity->getId() === $kicklistCorporation->getId()) {
                return $this;
            }
        }

        $this->kicklistCorporations[] = $kicklistCorporation;

        return $this;
    }

    /**
     * @return boolean TRUE if this collection contained the specified element, FALSE otherwise.
     */
    public function removeKicklistCorporation(Corporation $kicklistCorporation): bool
    {
        return $this->kicklistCorporations->removeElement($kicklistCorporation);
    }

    /**
     * @return Corporation[]
     */
    public function getKicklistCorporations(): array
    {
        return array_values($this->kicklistCorporations->toArray());
    }

    public function addKicklistAlliance(Alliance $kicklistAlliance): self
    {
        foreach ($this->getKicklistAlliances() as $entity) {
            if ($kicklistAlliance->getId() && $entity->getId() === $kicklistAlliance->getId()) {
                return $this;
            }
        }
        $this->kicklistAlliances[] = $kicklistAlliance;

        return $this;
    }

    /**
     * @return boolean TRUE if this collection contained the specified element, FALSE otherwise.
     */
    public function removeKicklistAlliance(Alliance $kicklistAlliance): bool
    {
        return $this->kicklistAlliances->removeElement($kicklistAlliance);
    }

    /**
     * @return Alliance[]
     */
    public function getKicklistAlliances(): array
    {
        return array_values($this->kicklistAlliances->toArray());
    }

    public function addAllowlistCorporation(Corporation $allowlistCorporation): self
    {
        foreach ($this->getAllowlistCorporations() as $entity) {
            if ($allowlistCorporation->getId() && $entity->getId() === $allowlistCorporation->getId()) {
                return $this;
            }
        }

        $this->allowlistCorporations[] = $allowlistCorporation;

        return $this;
    }

    /**
     * @return boolean TRUE if this collection contained the specified element, FALSE otherwise.
     */
    public function removeAllowlistCorporation(Corporation $allowlistCorporation): bool
    {
        return $this->allowlistCorporations->removeElement($allowlistCorporation);
    }

    /**
     * @return Corporation[]
     */
    public function getAllowlistCorporations(): array
    {
        return array_values($this->allowlistCorporations->toArray());
    }

    public function addAllowlistAlliance(Alliance $allowlistAlliance): self
    {
        foreach ($this->getAllowlistAlliances() as $entity) {
            if ($allowlistAlliance->getId() && $entity->getId() === $allowlistAlliance->getId()) {
                return $this;
            }
        }

        $this->allowlistAlliances[] = $allowlistAlliance;

        return $this;
    }

    /**
     * @return boolean TRUE if this collection contained the specified element, FALSE otherwise.
     */
    public function removeAllowlistAlliance(Alliance $allowlistAlliance): bool
    {
        return $this->allowlistAlliances->removeElement($allowlistAlliance);
    }

    /**
     * @return Alliance[]
     */
    public function getAllowlistAlliances(): array
    {
        return array_values($this->allowlistAlliances->toArray());
    }
}
