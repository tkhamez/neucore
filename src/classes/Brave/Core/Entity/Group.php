<?php
namespace Brave\Core\Entity;

/**
 * Groups for third party apps.
 *
 * @Entity(repositoryClass="Brave\Core\Entity\GroupRepository")
 * @Table(name="groups")
 */
class Group implements \JsonSerializable
{

	/**
	 * @Id
	 * @Column(type="integer")
	 * @GeneratedValue
	 */
	private $id;

	/**
	 * @Column(type="string", unique=true, length=64)
	 */
	private $name;

	/**
	 * @ManyToMany(targetEntity="User", mappedBy="groups")
	 */
	private $users;

	/**
     * Contains only information that is of interest for clients.
	 *
	 * {@inheritDoc}
	 * @see \JsonSerializable::jsonSerialize()
	 */
    public function jsonSerialize()
    {
        return $this->name;
    }

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->users = new \Doctrine\Common\Collections\ArrayCollection();
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
     * @return Group
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
     * @return Group
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
}
