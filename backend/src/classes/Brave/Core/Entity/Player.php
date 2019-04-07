<?php declare(strict_types=1);

namespace Brave\Core\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

/**
 *
 * @SWG\Definition(
 *     definition="Player",
 *     required={"id", "name"}
 * )
 *
 * @Entity
 * @Table(name="players")
 */
class Player implements \JsonSerializable
{
    /**
     * Standard account.
     *
     * @var string
     */
    const STATUS_STANDARD = 'standard';

    /**
     * Manually managed account.
     *
     * @var string
     */
    const STATUS_MANAGED = 'managed';

    /**
     * @SWG\Property()
     * @Id
     * @Column(type="integer")
     * @GeneratedValue
     * @var integer
     */
    private $id;

    /**
     * A name for the player.
     *
     * This is the EVE character name of the current main character or of
     * the last main character if there is currently none.
     *
     * @SWG\Property()
     * @Column(type="string", length=255)
     * @var string
     */
    private $name;

    /**
     * Last automatic group assignment.
     *
     * @Column(type="datetime", name="last_update", nullable=true)
     * @var \DateTime
     */
    private $lastUpdate;

    /**
     * Player account status.
     *
     * @SWG\Property(
     *     enum={"standard", "managed"})
     * )
     * @Column(type="string", length=16)
     * @var string
     */
    private $status = self::STATUS_STANDARD;

    /**
     * Set to true when the "account deactivated" mail was sent.
     *
     * Reset to false when all characters on the account
     * have valid tokens.
     *
     * @Column(type="boolean", name="deactivation_mail_sent")
     * @var bool
     */
    private $deactivationMailSent = false;

    /**
     * Roles for authorization.
     *
     * @SWG\Property(type="array", @SWG\Items(ref="#/definitions/Role"))
     * @ManyToMany(targetEntity="Role", inversedBy="players")
     * @OrderBy({"name" = "ASC"})
     * @var Collection
     */
    private $roles;

    /**
     * @SWG\Property(type="array", @SWG\Items(ref="#/definitions/Character"))
     * @OneToMany(targetEntity="Character", mappedBy="player")
     * @OrderBy({"name" = "ASC"})
     * @var Collection
     */
    private $characters;

    /**
     * Group applications.
     *
     * @OneToMany(targetEntity="GroupApplication", mappedBy="player")
     * @OrderBy({"created" = "DESC"})
     * @var Collection
     */
    private $groupApplications;

    /**
     * Group membership.
     *
     * @SWG\Property(type="array", @SWG\Items(ref="#/definitions/Group"))
     * @ManyToMany(targetEntity="Group", inversedBy="players")
     * @OrderBy({"name" = "ASC"})
     * @var Collection
     */
    private $groups;

    /**
     * Manager of groups.
     *
     * @SWG\Property(type="array", @SWG\Items(ref="#/definitions/Group"))
     * @ManyToMany(targetEntity="Group", mappedBy="managers")
     * @OrderBy({"name" = "ASC"})
     * @var Collection
     */
    private $managerGroups;

    /**
     * Manager of apps.
     *
     * @SWG\Property(type="array", @SWG\Items(ref="#/definitions/App"))
     * @ManyToMany(targetEntity="App", mappedBy="managers")
     * @OrderBy({"name" = "ASC"})
     * @var Collection
     */
    private $managerApps;

    /**
     * Characters that were removed from a player (API: not included by default).
     *
     * @SWG\Property(type="array", @SWG\Items(ref="#/definitions/RemovedCharacter"))
     * @OneToMany(targetEntity="RemovedCharacter", mappedBy="player")
     * @OrderBy({"removedDate" = "ASC"})
     * @var Collection
     */
    private $removedCharacters;

    /**
     * Contains only information that is of interest for clients.
     *
     * {@inheritDoc}
     * @see \JsonSerializable::jsonSerialize()
     */
    public function jsonSerialize(bool $minimum = false): array
    {
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
            'characters' => $this->getCharacters(),
            'groups' => $this->getGroups(),
            'managerGroups' => $this->getManagerGroups(),
            'managerApps' => $this->getManagerApps(),
            // API: removedCharacters are not included by default
        ];
    }

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->roles = new ArrayCollection();
        $this->characters = new ArrayCollection();
        $this->groupApplications = new ArrayCollection();
        $this->groups = new ArrayCollection();
        $this->managerGroups = new ArrayCollection();
        $this->managerApps = new ArrayCollection();
        $this->removedCharacters = new ArrayCollection();
    }

    /**
     * Get id.
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set name.
     *
     * @param string $name
     *
     * @return Player
     */
    public function setName(string $name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get name.
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set lastUpdate.
     *
     * @param \DateTime $lastUpdate
     *
     * @return Player
     */
    public function setLastUpdate($lastUpdate)
    {
        $this->lastUpdate = clone $lastUpdate;

        return $this;
    }

    /**
     * Get lastUpdate.
     *
     * @return \DateTime|null
     */
    public function getLastUpdate()
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

    /**
     * @return bool
     */
    public function getDeactivationMailSent(): bool
    {
        return $this->deactivationMailSent;
    }

    /**
     * @param bool $deactivationMailSent
     * @return Player
     */
    public function setDeactivationMailSent(bool $deactivationMailSent): self
    {
        $this->deactivationMailSent = $deactivationMailSent;

        return $this;
    }

    /**
     * Add role.
     *
     * @param Role $role
     *
     * @return Player
     */
    public function addRole(Role $role)
    {
        $this->roles[] = $role;

        return $this;
    }

    /**
     * Remove role.
     *
     * @param Role $role
     *
     * @return boolean TRUE if this collection contained the specified element, FALSE otherwise.
     */
    public function removeRole(Role $role)
    {
        return $this->roles->removeElement($role);
    }

    /**
     * Get roles.
     *
     * @return Role[]
     */
    public function getRoles()
    {
        return $this->roles->toArray();
    }

    /**
     *
     * @return string[]
     */
    public function getRoleNames()
    {
        $names = [];
        foreach ($this->getRoles() as $role) {
            $names[] = $role->getName();
        }

        return $names;
    }

    /**
     *
     * @param string $name
     * @return boolean
     */
    public function hasRole(string $name)
    {
        return in_array($name, $this->getRoleNames());
    }

    /**
     * Add character.
     *
     * @param Character $character
     *
     * @return Player
     */
    public function addCharacter(Character $character)
    {
        $this->characters[] = $character;

        return $this;
    }

    /**
     * Remove character.
     *
     * @param Character $character
     *
     * @return boolean TRUE if this collection contained the specified element, FALSE otherwise.
     */
    public function removeCharacter(Character $character)
    {
        return $this->characters->removeElement($character);
    }

    /**
     * Get characters.
     *
     * @return Character[]
     */
    public function getCharacters()
    {
        return $this->characters->toArray();
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

    /**
     * @param int $hours
     * @return bool
     */
    public function hasCharacterWithInvalidTokenOlderThan(int $hours): bool
    {
        foreach ($this->getCharacters() as $char) {
            if ($char->getValidToken() === true) {
                continue;
            }
            if ($char->getValidTokenTime() === null) {
                return true;
            }
            $time = $char->getValidTokenTime()->getTimestamp();
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

    /**
     * Add group application.
     *
     * @param GroupApplication $groupApplication
     *
     * @return Player
     */
    public function addGroupApplication(GroupApplication $groupApplication)
    {
        $this->groupApplications[] = $groupApplication;

        return $this;
    }

    /**
     * Remove group application.
     *
     * @param GroupApplication $groupApplication
     *
     * @return boolean TRUE if this collection contained the specified element, FALSE otherwise.
     */
    public function removeGroupApplication(GroupApplication $groupApplication)
    {
        return $this->groupApplications->removeElement($groupApplication);
    }

    /**
     * Get group applications.
     *
     * @return GroupApplication[]
     */
    public function getGroupApplications()
    {
        return $this->groupApplications->toArray();
    }

    /**
     * Add group.
     *
     * @param Group $group
     *
     * @return Player
     */
    public function addGroup(Group $group)
    {
        $this->groups[] = $group;

        return $this;
    }

    /**
     * Remove group.
     *
     * @param Group $group
     *
     * @return boolean TRUE if this collection contained the specified element, FALSE otherwise.
     */
    public function removeGroup(Group $group)
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
     * Get groups.
     *
     * @return Group[]
     */
    public function getGroups()
    {
        return $this->groups->toArray();
    }

    /**
     *
     * @return int[]
     */
    public function getGroupIds()
    {
        $groupIds = [];
        foreach ($this->getGroups() as $group) {
            $groupIds[] = $group->getId();
        }
        return $groupIds;
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
     * Add managerGroup.
     *
     * @param Group $managerGroup
     *
     * @return Player
     */
    public function addManagerGroup(Group $managerGroup)
    {
        $this->managerGroups[] = $managerGroup;

        return $this;
    }

    /**
     * Remove managerGroup.
     *
     * @param Group $managerGroup
     *
     * @return boolean TRUE if this collection contained the specified element, FALSE otherwise.
     */
    public function removeManagerGroup(Group $managerGroup)
    {
        return $this->managerGroups->removeElement($managerGroup);
    }

    /**
     * Get managerGroups.
     *
     * @return Group[]
     */
    public function getManagerGroups()
    {
        return $this->managerGroups->toArray();
    }

    public function hasManagerGroup(Group $group): bool
    {
        foreach ($this->getManagerGroups() as $mg) {
            // name is unique, id could be null, so this is easier
            if ($mg->getName() === $group->getName()) {
                return true;
            }
        }
        return false;
    }

    /**
     * Add managerApp.
     *
     * @param App $managerApp
     *
     * @return Player
     */
    public function addManagerApp(App $managerApp)
    {
        $this->managerApps[] = $managerApp;

        return $this;
    }

    /**
     * Remove managerApp.
     *
     * @param App $managerApp
     *
     * @return boolean TRUE if this collection contained the specified element, FALSE otherwise.
     */
    public function removeManagerApp(App $managerApp)
    {
        return $this->managerApps->removeElement($managerApp);
    }

    /**
     * Get managerApps.
     *
     * @return App[]
     */
    public function getManagerApps()
    {
        return $this->managerApps->toArray();
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
        return $this->removedCharacters->toArray();
    }
}
