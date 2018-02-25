<?php
namespace Brave\Core\Entity;

/**
 *
 * @SWG\Definition(definition="Player")
 * @Entity(repositoryClass="Brave\Core\Entity\PlayerRepository")
 * @Table(name="players")
 */
class Player implements \JsonSerializable
{

    /**
     * @Id
     * @Column(type="integer")
     * @GeneratedValue
     * @var integer
     */
    private $id;

    /**
     * A name for the player
     *
     * This is the EVE character name of the main atm.
     *
     * @SWG\Property()
     * @Column(type="string", length=255)
     * @var string
     */
    private $name;

    /**
     * Roles for authorization.
     *
     * @SWG\Property(type="array", @SWG\Items(type="string"))
     * @ManyToMany(targetEntity="Role", inversedBy="players")
     * @OrderBy({"name" = "ASC"})
     * @var \Doctrine\Common\Collections\Collection
     */
    private $roles;

    /**
     * Member of groups.
     *
     * @SWG\Property(type="array", @SWG\Items(type="string"))
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
     * @SWG\Property(type="array", @SWG\Items(type="string"))
     * @ManyToMany(targetEntity="Group", mappedBy="managers")
     * @OrderBy({"name" = "ASC"})
     * @var \Doctrine\Common\Collections\Collection
     */
    private $managerGroups;

    /**
     * Manager of groups.
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
        $arr = [
            'name' => $this->name,
            'roles' => $this->roles->toArray(),
            'groups' => $this->groups->toArray(),
            'characters' => $this->characters->toArray(),
            'managerGroups' => $this->managerGroups->toArray(),
            'managerApps' => $this->managerApps->toArray(),
        ];

        return $arr;
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
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getRoles()
    {
        return $this->roles;
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
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getGroups()
    {
        return $this->groups;
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
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getCharacters()
    {
        return $this->characters;
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
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getManagerGroups()
    {
        return $this->managerGroups;
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
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getManagerApps()
    {
        return $this->managerApps;
    }
}
