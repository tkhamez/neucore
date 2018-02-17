<?php
namespace Brave\Core\Entity;

/**
 * @SWG\Definition(definition="User")
 * @Entity(repositoryClass="Brave\Core\Entity\UserRepository")
 * @Table(name="users")
 */
class User implements \JsonSerializable
{

    /**
     * @Id
     * @Column(type="integer")
     * @GeneratedValue
     */
    private $id;

    /**
     * @var \Doctrine\Common\Collections\Collection
     * @SWG\Property(type="array", @SWG\Items(type="string"))
     * @ManyToMany(targetEntity="Role", inversedBy="users")
     * @JoinTable(name="users_roles")
     */
    private $roles;

    /**
     * Eve character ID
     *
     * @var int
     * @SWG\Property(format="int64")
     * @Column(type="bigint", name="character_id", unique=true)
     */
    private $characterId;

    /**
     * Eve character name
     *
     * @var string
     * @SWG\Property()
     * @Column(type="string", length=255)
     */
    private $name;

    /**
     * @var \Doctrine\Common\Collections\Collection
     * @SWG\Property(type="array", @SWG\Items(type="string"))
     * @ManyToMany(targetEntity="Group", inversedBy="users")
     * @JoinTable(name="users_groups")
     */
    private $groups;

    /**
     * Contains only information that is of interest for clients.
     *
     * {@inheritDoc}
     * @see \JsonSerializable::jsonSerialize()
     */
    public function jsonSerialize()
    {
        $arr = [
            'characterId' => (int) $this->characterId,
            'name' => $this->name,
            'roles' => $this->roles->toArray(),
            'groups' => $this->groups->toArray(),
        ];

        return $arr;
    }

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->groups = new \Doctrine\Common\Collections\ArrayCollection();
        $this->roles = new \Doctrine\Common\Collections\ArrayCollection();
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
     * Set characterId.
     *
     * @param int $characterId
     *
     * @return User
     */
    public function setCharacterId($characterId)
    {
        $this->characterId = $characterId;

        return $this;
    }

    /**
     * Get characterId.
     *
     * @return int
     */
    public function getCharacterId()
    {
        return $this->characterId;
    }

    /**
     * Set name.
     *
     * @param string $name
     *
     * @return User
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
     * Add group.
     *
     * @param \Brave\Core\Entity\Group $group
     *
     * @return User
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
     * @return \Brave\Core\Entity\Group[]
     */
    public function getGroups()
    {
        return $this->groups;
    }

    /**
     * Add role.
     *
     * @param \Brave\Core\Entity\Role $role
     *
     * @return User
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
     * @return \Brave\Core\Entity\Role[]
     */
    public function getRoles()
    {
        return $this->roles;
    }
}
