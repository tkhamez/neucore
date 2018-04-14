<?php
namespace Brave\Core\Entity;

/**
 *
 * @SWG\Definition(
 *     definition="Player",
 *     required={"id", "name", "roles", "groups", "characters", "managerGroups", "managerApps"}
 * )
 *
 * @Entity(repositoryClass="Brave\Core\Entity\PlayerRepository")
 * @Table(name="players")
 */
class Player implements \JsonSerializable
{

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
     * This is the EVE character name of the main character.
     *
     * @SWG\Property()
     * @Column(type="string", length=255)
     * @var string
     */
    private $name;

    /**
     * Roles for authorization.
     *
     * @SWG\Property(ref="#/definitions/RoleList")
     * @ManyToMany(targetEntity="Role", inversedBy="players")
     * @OrderBy({"name" = "ASC"})
     * @var \Doctrine\Common\Collections\Collection
     */
    private $roles;

    /**
     * Group membership.
     *
     * @SWG\Property(type="array", @SWG\Items(ref="#/definitions/Group"))
     * @ManyToMany(targetEntity="Group", inversedBy="players")
     * @OrderBy({"name" = "ASC"})
     * @var \Doctrine\Common\Collections\Collection
     */
    private $groups;

    /**
     * @SWG\Property(type="array", @SWG\Items(ref="#/definitions/Character"))
     * @OneToMany(targetEntity="Character", mappedBy="player")
     * @OrderBy({"name" = "ASC"})
     * @var \Doctrine\Common\Collections\Collection
     */
    private $characters;

    /**
     * Manager of groups.
     *
     * @SWG\Property(type="array", @SWG\Items(ref="#/definitions/Group"))
     * @ManyToMany(targetEntity="Group", mappedBy="managers")
     * @OrderBy({"name" = "ASC"})
     * @var \Doctrine\Common\Collections\Collection
     */
    private $managerGroups;

    /**
     * Manager of apps.
     *
     * @SWG\Property(type="array", @SWG\Items(ref="#/definitions/App"))
     * @ManyToMany(targetEntity="App", mappedBy="managers")
     * @OrderBy({"name" = "ASC"})
     * @var \Doctrine\Common\Collections\Collection
     */
    private $managerApps;

    /**
     * Contains only information that is of interest for clients.
     *
     * {@inheritDoc}
     * @see \JsonSerializable::jsonSerialize()
     */
    public function jsonSerialize()
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'roles' => $this->getRoles(),
            'groups' => $this->getGroups(),
            'characters' => $this->getCharacters(),
            'managerGroups' => $this->getManagerGroups(),
            'managerApps' => $this->getManagerApps(),
        ];
    }

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->roles = new \Doctrine\Common\Collections\ArrayCollection();
        $this->groups = new \Doctrine\Common\Collections\ArrayCollection();
        $this->characters = new \Doctrine\Common\Collections\ArrayCollection();
        $this->managerGroups = new \Doctrine\Common\Collections\ArrayCollection();
        $this->managerApps = new \Doctrine\Common\Collections\ArrayCollection();
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
    public function setName($name)
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
     * Add role.
     *
     * @param \Brave\Core\Entity\Role $role
     *
     * @return Player
     */
    public function addRole(\Brave\Core\Entity\Role $role)
    {
        $this->roles[] = $role;

        return $this;
    }

    /**
     * Remove role.
     *
     * @param \Brave\Core\Entity\Role $role
     *
     * @return boolean TRUE if this collection contained the specified element, FALSE otherwise.
     */
    public function removeRole(\Brave\Core\Entity\Role $role)
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
    public function hasRole($name)
    {
        return in_array($name, $this->getRoleNames());
    }

    /**
     * Add group.
     *
     * @param \Brave\Core\Entity\Group $group
     *
     * @return Player
     */
    public function addGroup(\Brave\Core\Entity\Group $group)
    {
        $this->groups[] = $group;

        return $this;
    }

    /**
     * Remove group.
     *
     * @param \Brave\Core\Entity\Group $group
     *
     * @return boolean TRUE if this collection contained the specified element, FALSE otherwise.
     */
    public function removeGroup(\Brave\Core\Entity\Group $group)
    {
        return $this->groups->removeElement($group);
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
     * Add character.
     *
     * @param \Brave\Core\Entity\Character $character
     *
     * @return Player
     */
    public function addCharacter(\Brave\Core\Entity\Character $character)
    {
        $this->characters[] = $character;

        return $this;
    }

    /**
     * Remove character.
     *
     * @param \Brave\Core\Entity\Character $character
     *
     * @return boolean TRUE if this collection contained the specified element, FALSE otherwise.
     */
    public function removeCharacter(\Brave\Core\Entity\Character $character)
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

    /**
     * Add managerGroup.
     *
     * @param \Brave\Core\Entity\Group $managerGroup
     *
     * @return Player
     */
    public function addManagerGroup(\Brave\Core\Entity\Group $managerGroup)
    {
        $this->managerGroups[] = $managerGroup;

        return $this;
    }

    /**
     * Remove managerGroup.
     *
     * @param \Brave\Core\Entity\Group $managerGroup
     *
     * @return boolean TRUE if this collection contained the specified element, FALSE otherwise.
     */
    public function removeManagerGroup(\Brave\Core\Entity\Group $managerGroup)
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

    /**
     * Add managerApp.
     *
     * @param \Brave\Core\Entity\App $managerApp
     *
     * @return Player
     */
    public function addManagerApp(\Brave\Core\Entity\App $managerApp)
    {
        $this->managerApps[] = $managerApp;

        return $this;
    }

    /**
     * Remove managerApp.
     *
     * @param \Brave\Core\Entity\App $managerApp
     *
     * @return boolean TRUE if this collection contained the specified element, FALSE otherwise.
     */
    public function removeManagerApp(\Brave\Core\Entity\App $managerApp)
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
}
