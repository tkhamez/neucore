<?php declare(strict_types=1);

namespace Brave\Core\Entity;

/**
 * @SWG\Definition(
 *     definition="App",
 *     required={"id", "name"}
 * )
 * @Entity(repositoryClass="Brave\Core\Entity\AppRepository")
 * @Table(name="apps")
 */
class App implements \JsonSerializable
{

    /**
     * App ID
     *
     * @SWG\Property()
     * @Id
     * @Column(type="integer")
     * @GeneratedValue
     * @var int
     */
    private $id;

    /**
     * App name
     *
     * @SWG\Property(maxLength=255)
     * @Column(type="string", length=255)
     * @var string
     */
    private $name;

    /**
     * @Column(type="string", length=255)
     * @var string
     */
    private $secret;

    /**
     * Roles for authorization.
     *
     * @ManyToMany(targetEntity="Role", inversedBy="apps")
     * @OrderBy({"name" = "ASC"})
     * @var \Doctrine\Common\Collections\Collection
     */
    private $roles;

    /**
     * Groups the app can see.
     *
     * @ManyToMany(targetEntity="Group", inversedBy="apps")
     * @OrderBy({"name" = "ASC"})
     * @var \Doctrine\Common\Collections\Collection
     */
    private $groups;

    /**
     * @ManyToMany(targetEntity="Player", inversedBy="managerApps")
     * @JoinTable(name="app_manager")
     * @OrderBy({"name" = "ASC"})
     * @var \Doctrine\Common\Collections\Collection
     */
    private $managers;

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
        ];
    }

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->roles = new \Doctrine\Common\Collections\ArrayCollection();
        $this->groups = new \Doctrine\Common\Collections\ArrayCollection();
        $this->managers = new \Doctrine\Common\Collections\ArrayCollection();
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
     * @return App
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
     * Set secret.
     *
     * @param string $secret The hashed string, *not* the plain text password.
     *
     * @return App
     */
    public function setSecret(string $secret)
    {
        $this->secret = $secret;

        return $this;
    }

    /**
     * Get secret.
     *
     * @return string
     */
    public function getSecret()
    {
        return $this->secret;
    }

    /**
     * Add role.
     *
     * @param \Brave\Core\Entity\Role $role
     *
     * @return App
     */
    public function addRole(Role $role)
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
     * Add group.
     *
     * @param \Brave\Core\Entity\Group $group
     *
     * @return App
     */
    public function addGroup(Group $group)
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
    public function removeGroup(Group $group)
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
     * Add manager.
     *
     * @param \Brave\Core\Entity\Player $manager
     *
     * @return App
     */
    public function addManager(Player $manager)
    {
        $this->managers[] = $manager;

        return $this;
    }

    /**
     * Remove manager.
     *
     * @param \Brave\Core\Entity\Player $manager
     *
     * @return boolean TRUE if this collection contained the specified element, FALSE otherwise.
     */
    public function removeManager(Player $manager)
    {
        return $this->managers->removeElement($manager);
    }

    /**
     * Get managers.
     *
     * @return Player[]
     */
    public function getManagers()
    {
        return $this->managers->toArray();
    }

    public function isManager(Player $player): bool
    {
        $isManager = false;

        foreach ($this->getManagers() as $m) {
            if ($m->getId() === $player->getId()) {
                $isManager = true;
                break;
            }
        }

        return $isManager;
    }
}
