<?php declare(strict_types=1);

namespace Neucore\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Swagger\Annotations as SWG;
use Doctrine\ORM\Mapping as ORM;

/**
 * @SWG\Definition(
 *     definition="App",
 *     required={"id", "name"}
 * )
 * @ORM\Entity
 * @ORM\Table(name="apps")
 */
class App implements \JsonSerializable
{
    /**
     * App ID
     *
     * @SWG\Property()
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue
     * @var int
     */
    private $id;

    /**
     * App name
     *
     * @SWG\Property(maxLength=255)
     * @ORM\Column(type="string", length=255)
     * @var string
     */
    private $name;

    /**
     * @ORM\Column(type="string", length=255)
     * @var string
     */
    private $secret;

    /**
     * Roles for authorization.
     *
     * @SWG\Property(type="array", @SWG\Items(ref="#/definitions/Role"))
     * @ORM\ManyToMany(targetEntity="Role", inversedBy="apps")
     * @ORM\OrderBy({"name" = "ASC"})
     * @var Collection
     */
    private $roles;

    /**
     * Groups the app can see.
     *
     * @SWG\Property(type="array", @SWG\Items(ref="#/definitions/Group"))
     * @ORM\ManyToMany(targetEntity="Group", inversedBy="apps")
     * @ORM\OrderBy({"name" = "ASC"})
     * @var Collection
     */
    private $groups;

    /**
     * @ORM\ManyToMany(targetEntity="Player", inversedBy="managerApps")
     * @ORM\JoinTable(name="app_manager")
     * @ORM\OrderBy({"name" = "ASC"})
     * @var Collection
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
            'groups' => $this->getGroups(),
            'roles' => $this->getRoles(),
        ];
    }

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->roles = new ArrayCollection();
        $this->groups = new ArrayCollection();
        $this->managers = new ArrayCollection();
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
     * @param Role $role
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
     * @param string $name
     * @return boolean
     */
    public function hasRole(string $name)
    {
        return in_array($name, $this->getRoleNames());
    }

    /**
     * Add group.
     *
     * @param Group $group
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
     * @param Group $group
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
     * @param Player $manager
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
     * @param Player $manager
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
