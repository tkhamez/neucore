<?php
namespace Brave\Core\Entity;

/**
 * Roles that are used to determined users permission in this app.
 *
 * @Entity(repositoryClass="Brave\Core\Entity\RoleRepository")
 * @Table(name="roles")
 */
class Role
{

	/**
	 * @Id
	 * @Column(type="integer")
	 * @GeneratedValue
	 * @var integer
	 */
    private $id;

	/**
	 * @ManyToMany(targetEntity="User", mappedBy="roles")
	 */
    private $users;

    /**
     * @ManyToMany(targetEntity="App", mappedBy="apps")
     */
    private $apps;

    /**
     * @Column(type="string", unique=true, length=64)
     * @var string
     */
    private $name;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->users = new \Doctrine\Common\Collections\ArrayCollection();
        $this->apps = new \Doctrine\Common\Collections\ArrayCollection();
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
     * @return Role
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
     * Add user.
     *
     * @param \Brave\Core\Entity\User $user
     *
     * @return Role
     */
    public function addUser(\Brave\Core\Entity\User $user)
    {
        $this->users[] = $user;

        return $this;
    }

    /**
     * Remove user.
     *
     * @param \Brave\Core\Entity\User $user
     *
     * @return boolean TRUE if this collection contained the specified element, FALSE otherwise.
     */
    public function removeUser(\Brave\Core\Entity\User $user)
    {
        return $this->users->removeElement($user);
    }

    /**
     * Get users.
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getUsers()
    {
        return $this->users;
    }

    /**
     * Add app.
     *
     * @param \Brave\Core\Entity\App $app
     *
     * @return Role
     */
    public function addApp(\Brave\Core\Entity\App $app)
    {
        $this->apps[] = $app;

        return $this;
    }

    /**
     * Remove app.
     *
     * @param \Brave\Core\Entity\App $app
     *
     * @return boolean TRUE if this collection contained the specified element, FALSE otherwise.
     */
    public function removeApp(\Brave\Core\Entity\App $app)
    {
        return $this->apps->removeElement($app);
    }

    /**
     * Get apps.
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getApps()
    {
        return $this->apps;
    }
}
