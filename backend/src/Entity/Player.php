<?php

declare(strict_types=1);

namespace Neucore\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
/* @phan-suppress-next-line PhanUnreferencedUseNormal */
use Doctrine\ORM\Mapping as ORM;
use Neucore\Plugin\Data\CoreAccount;
use Neucore\Plugin\Data\CoreCharacter;
use Neucore\Plugin\Data\CoreGroup;
use Neucore\Plugin\Data\CoreRole;
/* @phan-suppress-next-line PhanUnreferencedUseNormal */
use OpenApi\Annotations as OA;

/**
 *
 * @OA\Schema(
 *     schema="Player",
 *     required={"id", "name"},
 *     @OA\Property(
 *         property="serviceAccounts",
 *         type="array",
 *         description="External service accounts (API: not included by default)",
 *         @OA\Items(ref="#/components/schemas/ServiceAccount")
 *     ),
 *     @OA\Property(
 *         property="characterId",
 *         description="ID of main character (API: not included by default)",
 *         type="integer",
 *     ),
 *     @OA\Property(
 *         property="corporationName",
 *         description="Corporation of main character (API: not included by default)",
 *         type="string",
 *     ),
 *     @OA\Property(
 *         property="allianceName",
 *         description="Alliance of main character (API: not included by default)",
 *         type="string",
 *     )
 * )
 *
 * @ORM\Entity
 * @ORM\Table(name="players")
 */
class Player implements \JsonSerializable
{
    /**
     * Standard account.
     *
     * @var string
     */
    public const STATUS_STANDARD = 'standard';

    /**
     * Manually managed account.
     *
     * @var string
     */
    public const STATUS_MANAGED = 'managed';

    /**
     * @OA\Property()
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue
     */
    private ?int $id = null;

    /**
     * A name for the player.
     *
     * This is the EVE character name of the current main character or of
     * the last main character if there is currently none.
     *
     * @OA\Property()
     * @ORM\Column(type="string", length=255)
     */
    private string $name = '';

    /**
     * Last automatic group assignment.
     *
     * @ORM\Column(type="datetime", name="last_update", nullable=true)
     */
    private ?\DateTime $lastUpdate = null;

    /**
     * Player account status.
     *
     * @OA\Property(
     *     enum={"standard", "managed"})
     * )
     * @ORM\Column(type="string", length=16)
     */
    private string $status = self::STATUS_STANDARD;

    /**
     * Set to true when the "account deactivated" mail was sent or has a permanent error
     * (CSPA charge or blocked sender).
     *
     * Reset to false when all characters on the account
     * have valid tokens.
     *
     * @ORM\Column(type="boolean", name="deactivation_mail_sent")
     */
    private bool $deactivationMailSent = false;

    /**
     * Roles for authorization.
     *
     * @OA\Property(type="array", @OA\Items(ref="#/components/schemas/Role"))
     * @ORM\ManyToMany(targetEntity="Role", inversedBy="players")
     * @ORM\OrderBy({"name" = "ASC"})
     */
    private Collection $roles;

    /**
     * @OA\Property(type="array", @OA\Items(ref="#/components/schemas/Character"))
     * @ORM\OneToMany(targetEntity="Character", mappedBy="player")
     * @ORM\OrderBy({"name" = "ASC"})
     */
    private Collection $characters;

    /**
     * Group applications.
     *
     * @ORM\OneToMany(targetEntity="GroupApplication", mappedBy="player")
     * @ORM\OrderBy({"created" = "DESC"})
     */
    private Collection $groupApplications;

    /**
     * Group membership.
     *
     * @OA\Property(type="array", @OA\Items(ref="#/components/schemas/Group"))
     * @ORM\ManyToMany(targetEntity="Group", inversedBy="players")
     * @ORM\OrderBy({"name" = "ASC"})
     */
    private Collection $groups;

    /**
     * Manager of groups.
     *
     * @OA\Property(type="array", @OA\Items(ref="#/components/schemas/Group"))
     * @ORM\ManyToMany(targetEntity="Group", mappedBy="managers")
     * @ORM\OrderBy({"name" = "ASC"})
     */
    private Collection $managerGroups;

    /**
     * Manager of apps.
     *
     * @OA\Property(type="array", @OA\Items(ref="#/components/schemas/App"))
     * @ORM\ManyToMany(targetEntity="App", mappedBy="managers")
     * @ORM\OrderBy({"name" = "ASC"})
     */
    private Collection $managerApps;

    /**
     * Characters that were removed from a player (API: not included by default).
     *
     * @OA\Property(type="array", @OA\Items(ref="#/components/schemas/RemovedCharacter"))
     * @ORM\OneToMany(targetEntity="RemovedCharacter", mappedBy="player")
     * @ORM\OrderBy({"removedDate" = "ASC"})
     */
    private Collection $removedCharacters;

    /**
     * Characters that were moved from another player account to this account (API: not included by default).
     *
     * @OA\Property(type="array", @OA\Items(ref="#/components/schemas/RemovedCharacter"))
     * @ORM\OneToMany(targetEntity="RemovedCharacter", mappedBy="newPlayer")
     * @ORM\OrderBy({"removedDate" = "ASC"})
     */
    private Collection $incomingCharacters;

    /**
     * Contains only information that is of interest for clients.
     */
    public function jsonSerialize(
        bool $minimum = false,
        bool $withNameChanges = false,
        bool $withEsiTokens = false
    ): array {
        if ($minimum) {
            return [
                'id' => $this->id,
                'name' => $this->name,
            ];
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'status' => $this->status,
            'roles' => $this->getRoles(),
            'characters' => array_map(function (Character $character) use ($withNameChanges, $withEsiTokens) {
                return $character->jsonSerialize(false, true, $withNameChanges, $withEsiTokens);
            }, $this->getCharacters()),
            'groups' => $this->getGroups(),
            'managerGroups' => $this->getManagerGroups(),
            'managerApps' => $this->getManagerApps(),
            // API: removedCharacters are not included by default
        ];
    }

    public function __construct()
    {
        $this->roles = new ArrayCollection();
        $this->characters = new ArrayCollection();
        $this->groupApplications = new ArrayCollection();
        $this->groups = new ArrayCollection();
        $this->managerGroups = new ArrayCollection();
        $this->managerApps = new ArrayCollection();
        $this->removedCharacters = new ArrayCollection();
        $this->incomingCharacters = new ArrayCollection();
    }

    public function __toString(): string
    {
        return "$this->name #$this->id";
    }

    /**
     * Setter for identifier (autoincrement).
     */
    public function setId(int $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getId(): int
    {
        return (int) $this->id;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setLastUpdate(\DateTime $lastUpdate): self
    {
        $this->lastUpdate = clone $lastUpdate;
        return $this;
    }

    public function getLastUpdate(): ?\DateTime
    {
        return $this->lastUpdate;
    }

    public function setStatus(string $status): Player
    {
        $this->status = $status;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getDeactivationMailSent(): bool
    {
        return $this->deactivationMailSent;
    }

    public function setDeactivationMailSent(bool $deactivationMailSent): self
    {
        $this->deactivationMailSent = $deactivationMailSent;
        return $this;
    }

    public function addRole(Role $role): self
    {
        $this->roles[] = $role;
        return $this;
    }

    /**
     * @return boolean TRUE if this collection contained the specified element, FALSE otherwise.
     */
    public function removeRole(Role $role): bool
    {
        return $this->roles->removeElement($role);
    }

    /**
     * @return Role[]
     */
    public function getRoles(): array
    {
        return array_values($this->roles->toArray());
    }

    /**
     * @return CoreRole[]
     */
    public function getCoreRoles(): array
    {
        return array_map(function (Role $role) {
            return new CoreRole($role->getId(), $role->getName());
        }, $this->getRoles());
    }

    /**
     * @return string[]
     */
    public function getRoleNames(): array
    {
        $names = [];
        foreach ($this->getRoles() as $role) {
            $names[] = $role->getName();
        }
        return $names;
    }

    public function hasRole(string $name): bool
    {
        return in_array($name, $this->getRoleNames());
    }

    public function addCharacter(Character $character): self
    {
        $this->characters[] = $character;
        return $this;
    }

    /**
     * @return boolean TRUE if this collection contained the specified element, FALSE otherwise.
     */
    public function removeCharacter(Character $character): bool
    {
        return $this->characters->removeElement($character);
    }

    /**
     * @return Character[]
     */
    public function getCharacters(): array
    {
        return array_values($this->characters->toArray());
    }

    /**
     * @return CoreCharacter[]
     */
    public function getCoreCharacters(): array
    {
        return array_map(function (Character $character) {
            return $character->toCoreCharacter();
        }, $this->getCharacters());
    }

    public function getCharactersId(): array
    {
        return array_map(function (Character $character) {
            return $character->getId();
        }, $this->getCharacters());
    }

    public function hasCharacter(int $charId): bool
    {
        foreach ($this->getCharacters() as $c) {
            if ($c->getId() === $charId) {
                return true;
            }
        }
        return false;
    }

    public function getCharacter(int $characterId): ?Character
    {
        foreach ($this->getCharacters() as $c) {
            if ($c->getId() === $characterId) {
                return $c;
            }
        }
        return null;
    }

    /**
     * @param int[] $alliances
     * @param int[] $corporations
     * @return bool
     */
    public function hasCharacterInAllianceOrCorporation(array $alliances, array $corporations): bool
    {
        $isMember = false;
        foreach ($this->getCharacters() as $character) {
            if ($character->getCorporation() === null) {
                continue;
            }
            if ((
                $character->getCorporation()->getAlliance() !== null &&
                in_array($character->getCorporation()->getAlliance()->getId(), $alliances)
            ) ||
                in_array($character->getCorporation()->getId(), $corporations)
            ) {
                $isMember = true;
                break;
            }
        }
        return $isMember;
    }

    public function hasCharacterWithInvalidTokenOlderThan(int $hours): bool
    {
        foreach ($this->getCharacters() as $char) {
            $token = $char->getEsiToken(EveLogin::NAME_DEFAULT);
            if (!$token) {
                return true;
            }
            if ($token->getValidToken() === true) {
                continue;
            }
            if ($token->getValidTokenTime() === null) {
                return true;
            }
            $time = $token->getValidTokenTime()->getTimestamp();
            if (time() - $time >= 60 * 60 * $hours) {
                return true;
            }
        }
        return false;
    }

    public function getMain(): ?Character
    {
        foreach ($this->getCharacters() as $c) {
            if ($c->getMain()) {
                return $c;
            }
        }
        return null;
    }

    public function addGroupApplication(GroupApplication $groupApplication): self
    {
        $this->groupApplications[] = $groupApplication;
        return $this;
    }

    /**
     * @return boolean TRUE if this collection contained the specified element, FALSE otherwise.
     */
    public function removeGroupApplication(GroupApplication $groupApplication): bool
    {
        return $this->groupApplications->removeElement($groupApplication);
    }

    /**
     * @return GroupApplication[]
     */
    public function getGroupApplications(): array
    {
        return array_values($this->groupApplications->toArray());
    }

    public function addGroup(Group $group): self
    {
        $this->groups[] = $group;
        return $this;
    }

    /**
     * @return boolean TRUE if this collection contained the specified element, FALSE otherwise.
     */
    public function removeGroup(Group $group): bool
    {
        return $this->groups->removeElement($group);
    }

    public function findGroupById(int $groupId): ?Group
    {
        foreach ($this->getGroups() as $group) {
            if ($group->getId() === $groupId) {
                return $group;
            }
        }
        return null;
    }

    /**
     * @return Group[]
     */
    public function getGroups(): array
    {
        return array_values($this->groups->toArray());
    }

    /**
     * @return int[]
     */
    public function getGroupIds(): array
    {
        $groupIds = [];
        foreach ($this->getGroups() as $group) {
            $groupIds[] = $group->getId();
        }
        return $groupIds;
    }

    /**
     * @return CoreGroup[]
     */
    public function getCoreGroups(): array
    {
        return array_map(function (Group $group) {
            return new CoreGroup($group->getId(), $group->getName());
        }, $this->getGroups());
    }

    public function hasGroup(int $groupId): bool
    {
        foreach ($this->getGroups() as $g) {
            if ($g->getId() === $groupId) {
                return true;
            }
        }
        return false;
    }

    /**
     * Returns true if the player is a member of one of the specified groups.
     *
     * @param int[] $groupIds
     * @return bool
     */
    public function hasAnyGroup(array $groupIds): bool
    {
        return ! empty(array_intersect($groupIds, $this->getGroupIds()));
    }

    /**
     * Checks required and forbidden groups.
     */
    public function isAllowedMember(Group $group): bool
    {
        $requiredGroups = $group->getRequiredGroups();
        $notAllowed = count($requiredGroups) > 0;
        foreach ($requiredGroups as $requiredGroup) {
            if ($this->hasGroup($requiredGroup->getId())) {
                $notAllowed = false;
                break;
            }
        }

        foreach ($group->getForbiddenGroups() as $forbiddenGroup) {
            if ($this->hasGroup($forbiddenGroup->getId())) {
                return false;
            }
        }

        return !$notAllowed;
    }

    public function addManagerGroup(Group $managerGroup): self
    {
        $this->managerGroups[] = $managerGroup;
        return $this;
    }

    /**
     * @return boolean TRUE if this collection contained the specified element, FALSE otherwise.
     */
    public function removeManagerGroup(Group $managerGroup): bool
    {
        return $this->managerGroups->removeElement($managerGroup);
    }

    /**
     * @return Group[]
     */
    public function getManagerGroups(): array
    {
        return array_values($this->managerGroups->toArray());
    }

    /**
     * @return CoreGroup[]
     */
    public function getManagerCoreGroups(): array
    {
        return array_map(function (Group $group) {
            return new CoreGroup($group->getId(), $group->getName());
        }, $this->getManagerGroups());
    }

    public function hasManagerGroup(int $groupId): bool
    {
        foreach ($this->getManagerGroups() as $mg) {
            if ($mg->getId() === $groupId) {
                return true;
            }
        }
        return false;
    }

    public function addManagerApp(App $managerApp): self
    {
        $this->managerApps[] = $managerApp;
        return $this;
    }

    /**
     * @return boolean TRUE if this collection contained the specified element, FALSE otherwise.
     */
    public function removeManagerApp(App $managerApp): bool
    {
        return $this->managerApps->removeElement($managerApp);
    }

    /**
     * @return App[]
     */
    public function getManagerApps(): array
    {
        return array_values($this->managerApps->toArray());
    }

    public function addRemovedCharacter(RemovedCharacter $removedCharacter): self
    {
        $this->removedCharacters[] = $removedCharacter;
        return $this;
    }

    /**
     * @return boolean TRUE if this collection contained the specified element, FALSE otherwise.
     */
    public function removeRemovedCharacter(RemovedCharacter $removedCharacter): bool
    {
        return $this->removedCharacters->removeElement($removedCharacter);
    }

    /**
     * @return RemovedCharacter[]
     */
    public function getRemovedCharacters(): array
    {
        return array_values($this->removedCharacters->toArray());
    }

    public function addIncomingCharacters(RemovedCharacter $incomingCharacters): self
    {
        $this->incomingCharacters[] = $incomingCharacters;
        return $this;
    }

    /**
     * @return RemovedCharacter[]
     */
    public function getIncomingCharacters(): array
    {
        return array_values($this->incomingCharacters->toArray());
    }

    public function getCoreAccount(): ?CoreAccount
    {
        $coreAccount = null;
        if ($this->getMain()) {
            $coreAccount = new CoreAccount(
                $this->getMain()->toCoreCharacter(),
                $this->getCoreCharacters(),
                $this->getCoreGroups(),
                $this->getManagerCoreGroups(),
                $this->getCoreRoles(),
            );
        }
        return $coreAccount;
    }
}
