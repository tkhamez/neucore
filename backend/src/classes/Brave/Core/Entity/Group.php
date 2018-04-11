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
     *
     * @var int
     */
    private $id;

    /**
     * @Column(type="string", unique=true, length=64)
     *
     * @var string
     */
    private $name;

    /**
     * @ManyToMany(targetEntity="Player", mappedBy="players")
     * @OrderBy({"name" = "ASC"})
     *
     * @var \Doctrine\Common\Collections\Collection
     */
    private $players;

    /**
     * @ManyToMany(targetEntity="App", mappedBy="apps")
     * @OrderBy({"name" = "ASC"})
     *
     * @var \Doctrine\Common\Collections\Collection
     */
    private $apps;

    /**
     * @ManyToMany(targetEntity="Player", inversedBy="managerGroups")
     * @JoinTable(name="group_manager")
     * @OrderBy({"name" = "ASC"})
     *
     * @var \Doctrine\Common\Collections\Collection
     */
    private $managers;

    /**
     * Contains only information that is of interest for clients.
     *
     * {@inheritdoc}
     *
     * @see \JsonSerializable::jsonSerialize()
     */
    public function jsonSerialize()
    {
        return $this->name;
    }

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->players = new \Doctrine\Common\Collections\ArrayCollection();
        $this->apps = new \Doctrine\Common\Collections\ArrayCollection();
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
     * Add player.
     *
     * @param \Brave\Core\Entity\Player $player
     *
     * @return Group
     */
    public function addPlayer(\Brave\Core\Entity\Player $player)
    {
        $this->players[] = $player;

        return $this;
    }

    /**
     * Remove player.
     *
     * @param \Brave\Core\Entity\Player $player
     *
     * @return bool TRUE if this collection contained the specified element, FALSE otherwise.
     */
    public function removePlayer(\Brave\Core\Entity\Player $player)
    {
        return $this->players->removeElement($player);
    }

    /**
     * Get players.
     *
     * @return Player[]
     */
    public function getPlayers()
    {
        return $this->players;
    }

    /**
     * Add app.
     *
     * @param \Brave\Core\Entity\App $app
     *
     * @return Group
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
     * @return bool TRUE if this collection contained the specified element, FALSE otherwise.
     */
    public function removeApp(\Brave\Core\Entity\App $app)
    {
        return $this->apps->removeElement($app);
    }

    /**
     * Get apps.
     *
     * @return App[]
     */
    public function getApps()
    {
        return $this->apps;
    }

    /**
     * Add manager.
     *
     * @param \Brave\Core\Entity\Player $manager
     *
     * @return Group
     */
    public function addManager(\Brave\Core\Entity\Player $manager)
    {
        $this->managers[] = $manager;

        return $this;
    }

    /**
     * Remove manager.
     *
     * @param \Brave\Core\Entity\Player $manager
     *
     * @return bool TRUE if this collection contained the specified element, FALSE otherwise.
     */
    public function removeManager(\Brave\Core\Entity\Player $manager)
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
        return $this->managers;
    }
}
