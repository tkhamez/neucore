<?php declare(strict_types=1);

namespace Brave\Core\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

/**
 * Groups for third party apps.
 *
 * @SWG\Definition(
 *     definition="Group",
 *     required={"id", "name"}
 * )
 * @Entity
 * @Table(name="groups_tbl")
 */
class Group implements \JsonSerializable
{
    const VISIBILITY_PRIVATE = 'private';

    const VISIBILITY_PUBLIC = 'public';

    const VISIBILITY_CONDITIONED = 'conditioned';

    /**
     * Group ID.
     *
     * @SWG\Property()
     * @Id
     * @Column(type="integer")
     * @GeneratedValue
     * @var integer
     */
    private $id;

    /**
     * A unique group name (can be changed).
     *
     * @SWG\Property(maxLength=64, pattern="^[-._a-zA-Z0-9]+$")
     * @Column(type="string", unique=true, length=64)
     * @var string
     */
    private $name;

    /**
     *
     * @SWG\Property(enum={"private", "public", "conditioned"})
     * @Column(type="string", length=16, options={"default" : "private"})
     * @var string
     */
    private $visibility = self::VISIBILITY_PRIVATE;

    /**
     * @OneToMany(targetEntity="GroupApplication", mappedBy="group", cascade={"remove"})
     * @OrderBy({"created" = "DESC"})
     * @var Collection
     */
    private $applications;

    /**
     * Group members.
     *
     * @ManyToMany(targetEntity="Player", mappedBy="groups")
     * @OrderBy({"name" = "ASC"})
     * @var Collection
     */
    private $players;

    /**
     * @ManyToMany(targetEntity="Player", inversedBy="managerGroups")
     * @JoinTable(name="group_manager")
     * @OrderBy({"name" = "ASC"})
     * @var Collection
     */
    private $managers;

    /**
     * @ManyToMany(targetEntity="App", mappedBy="groups")
     * @OrderBy({"name" = "ASC"})
     * @var Collection
     */
    private $apps;

    /**
     * Corporations for automatic assignment.
     *
     * @ManyToMany(targetEntity="Corporation", mappedBy="groups")
     * @OrderBy({"name" = "ASC"})
     * @var Collection
     */
    private $corporations;

    /**
     * Alliances for automatic assignment.
     *
     * @ManyToMany(targetEntity="Alliance", mappedBy="groups")
     * @OrderBy({"name" = "ASC"})
     * @var Collection
     */
    private $alliances;

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
            'visibility' => $this->visibility
        ];
    }

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->applications = new ArrayCollection();
        $this->players = new ArrayCollection();
        $this->managers = new ArrayCollection();
        $this->apps = new ArrayCollection();
        $this->corporations = new ArrayCollection();
        $this->alliances = new ArrayCollection();
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
     * Set visibility.
     *
     * @param string $visibility elf::VISIBILITY_PRIVATE, self::VISIBILITY_PUBLIC or self::VISIBILITY_CONDITIONED
     * @throws \InvalidArgumentException if parameter is invalid
     * @return Group
     */
    public function setVisibility(string $visibility)
    {
        $valid = [self::VISIBILITY_PRIVATE, self::VISIBILITY_PUBLIC, self::VISIBILITY_CONDITIONED];
        if (! in_array($visibility, $valid)) {
            throw new \InvalidArgumentException('Parameter must be one of ' . implode(', ', $valid));
        }

        $this->visibility = $visibility;

        return $this;
    }

    /**
     * Get visibility.
     *
     * @return string
     */
    public function getVisibility()
    {
        return $this->visibility;
    }

    /**
     * Add group application.
     *
     * @param GroupApplication $application
     *
     * @return Group
     */
    public function addApplication(GroupApplication $application)
    {
        $this->applications[] = $application;

        return $this;
    }

    /**
     * Remove group application.
     *
     * @param GroupApplication $application
     *
     * @return boolean TRUE if this collection contained the specified element, FALSE otherwise.
     */
    public function removeApplication(GroupApplication $application)
    {
        return $this->applications->removeElement($application);
    }

    /**
     * Get group applications.
     *
     * @return GroupApplication[]
     */
    public function getApplication()
    {
        return $this->applications->toArray();
    }

    /**
     * Add player.
     *
     * @param Player $player
     *
     * @return Group
     */
    public function addPlayer(Player $player)
    {
        $this->players[] = $player;

        return $this;
    }

    /**
     * Remove player.
     *
     * @param Player $player
     *
     * @return boolean TRUE if this collection contained the specified element, FALSE otherwise.
     */
    public function removePlayer(Player $player)
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
        return $this->players->toArray();
    }

    /**
     * Add manager.
     *
     * @param Player $manager
     *
     * @return Group
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

    /**
     * Add app.
     *
     * @param App $app
     *
     * @return Group
     */
    public function addApp(App $app)
    {
        $this->apps[] = $app;

        return $this;
    }

    /**
     * Remove app.
     *
     * @param App $app
     *
     * @return boolean TRUE if this collection contained the specified element, FALSE otherwise.
     */
    public function removeApp(App $app)
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
        return $this->apps->toArray();
    }

    /**
     * Add corporation.
     *
     * @param Corporation $corporation
     *
     * @return Group
     */
    public function addCorporation(Corporation $corporation)
    {
        $this->corporations[] = $corporation;

        return $this;
    }

    /**
     * Remove corporation.
     *
     * @param Corporation $corporation
     *
     * @return boolean TRUE if this collection contained the specified element, FALSE otherwise.
     */
    public function removeCorporation(Corporation $corporation)
    {
        return $this->corporations->removeElement($corporation);
    }

    /**
     * Get corporations.
     *
     * @return Corporation[]
     */
    public function getCorporations()
    {
        return $this->corporations->toArray();
    }

    /**
     * Add alliance.
     *
     * @param Alliance $alliance
     *
     * @return Group
     */
    public function addAlliance(Alliance $alliance)
    {
        $this->alliances[] = $alliance;

        return $this;
    }

    /**
     * Remove alliance.
     *
     * @param Alliance $alliance
     *
     * @return boolean TRUE if this collection contained the specified element, FALSE otherwise.
     */
    public function removeAlliance(Alliance $alliance)
    {
        return $this->alliances->removeElement($alliance);
    }

    /**
     * Get alliances.
     *
     * @return Alliance[]
     */
    public function getAlliances()
    {
        return $this->alliances->toArray();
    }
}
