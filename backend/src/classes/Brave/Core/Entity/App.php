<?php
namespace Brave\Core\Entity;

/**
 * @SWG\Definition(definition="App")
 * @Entity(repositoryClass="Brave\Core\Entity\AppRepository")
 * @Table(name="apps")
 */
class App implements \JsonSerializable
{

    /**
     * App ID
     *
     * @var int
     * @SWG\Property()
     * @Id
     * @Column(type="integer")
     * @GeneratedValue
     */
    private $id;

    /**
     * @ManyToMany(targetEntity="Role", inversedBy="apps")
     * @JoinTable(name="apps_roles")
     * @var \Doctrine\Common\Collections\Collection
     */
    private $roles;

    /**
     * @Column(type="string", length=255)
     */
    private $secret;

    /**
     * App name
     *
     * @var string
     * @SWG\Property()
     * @Column(type="string", length=255)
     */
    private $name;

    /**
     * Contains only information that is of interest for clients.
     *
     * {@inheritDoc}
     * @see \JsonSerializable::jsonSerialize()
     */
    public function jsonSerialize()
    {
        $arr = [
            'id' => $this->id,
            'name' => $this->name,
        ];

        return $arr;
    }

    /**
     * Constructor
     */
    public function __construct()
    {
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
     * Add role.
     *
     * @param \Brave\Core\Entity\Role $role
     *
     * @return App
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

    /**
     * Set secret.
     *
     * @param string $secret
     *
     * @return App
     */
    public function setSecret($secret)
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
     * Set name.
     *
     * @param string $name
     *
     * @return App
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
}
