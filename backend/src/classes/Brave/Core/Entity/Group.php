<?php declare(strict_types=1);

namespace Brave\Core\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Swagger\Annotations as SWG;
use Doctrine\ORM\Mapping as ORM;

/**
 * @SWG\Definition(
 *     definition="Group",
 *     required={"id", "name"}
 * )
 * @ORM\Entity
 * @ORM\Table(name="groups_tbl")
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
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue
     * @var integer
     */
    private $id;

    /**
     * A unique group name (can be changed).
     *
     * @SWG\Property(maxLength=64, pattern="^[-._a-zA-Z0-9]+$")
     * @ORM\Column(type="string", unique=true, length=64)
     * @var string
     */
    private $name;

    /**
     *
     * @SWG\Property(enum={"private", "public", "conditioned"})
     * @ORM\Column(type="string", length=16, options={"default" : "private"})
     * @var string
     */
    private $visibility = self::VISIBILITY_PRIVATE;

    /**
     * @ORM\OneToMany(targetEntity="GroupApplication", mappedBy="group", cascade={"remove"})
     * @ORM\OrderBy({"created" = "DESC"})
     * @var Collection
     */
    private $applications;

    /**
     * Group members.
     *
     * @ORM\ManyToMany(targetEntity="Player", mappedBy="groups")
     * @ORM\OrderBy({"name" = "ASC"})
     * @var Collection
     */
    private $players;

    /**
     * @ORM\ManyToMany(targetEntity="Player", inversedBy="managerGroups")
     * @ORM\JoinTable(name="group_manager")
     * @ORM\OrderBy({"name" = "ASC"})
     * @var Collection
     */
    private $managers;

    /**
     * @ORM\ManyToMany(targetEntity="App", mappedBy="groups")
     * @ORM\OrderBy({"name" = "ASC"})
     * @var Collection
     */
    private $apps;

    /**
     * Corporations for automatic assignment.
     *
     * @ORM\ManyToMany(targetEntity="Corporation", mappedBy="groups")
     * @ORM\OrderBy({"name" = "ASC"})
     * @var Collection
     */
    private $corporations;

    /**
     * Alliances for automatic assignment.
     *
     * @ORM\ManyToMany(targetEntity="Alliance", mappedBy="groups")
     * @ORM\OrderBy({"name" = "ASC"})
     * @var Collection
     */
    private $alliances;

    /**
     * A player must be a member of one of these groups in order to be a member of this group
     * (API: not included by default).
     *
     * @ORM\ManyToMany(targetEntity="Group", inversedBy="requiredBy")
     * @ORM\JoinTable(name="group_required_groups")
     * @ORM\OrderBy({"name" = "ASC"})
     * @var Collection
     */
    private $requiredGroups;

    /**
     * Groups for which this group is required.
     * (API: not included by default).
     *
     * @ORM\ManyToMany(targetEntity="Group", mappedBy="requiredGroups")
     * @ORM\OrderBy({"name" = "ASC"})
     * @var Collection
     */
    private $requiredBy;

    /**
     * Contains only information that is of interest for clients.
     *
     * {@inheritDoc}
     * @see \JsonSerializable::jsonSerialize()
     */
    public function jsonSerialize($includeRequiredGroups = false)
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
        $this->requiredGroups = new ArrayCollection();
        $this->requiredBy = new ArrayCollection();
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
    public function getApplications()
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

    /**
     * Add requiredGroup.
     *
     * @param Group $requiredGroup
     *
     * @return Group
     */
    public function addRequiredGroup(Group $requiredGroup)
    {
        $this->requiredGroups[] = $requiredGroup;

        return $this;
    }

    /**
     * Remove requiredGroup.
     *
     * @param Group $requiredGroup
     *
     * @return boolean TRUE if this collection contained the specified element, FALSE otherwise.
     */
    public function removeRequiredGroup(Group $requiredGroup)
    {
        return $this->requiredGroups->removeElement($requiredGroup);
    }

    /**
     * Get requiredGroups, ordered by name asc.
     *
     * @return Group[]
     */
    public function getRequiredGroups(): array
    {
        return $this->requiredGroups->toArray();
    }

    /**
     * Add requiredBy.
     *
     * @param Group $requiredBy
     *
     * @return Group
     */
    public function addRequiredBy(Group $requiredBy)
    {
        $this->requiredBy[] = $requiredBy;

        return $this;
    }

    /**
     * Remove requiredBy.
     *
     * @param Group $requiredBy
     *
     * @return boolean TRUE if this collection contained the specified element, FALSE otherwise.
     */
    public function removeRequiredBy(Group $requiredBy)
    {
        return $this->requiredBy->removeElement($requiredBy);
    }

    /**
     * Get requiredBy.
     *
     * @return Group[]
     */
    public function getRequiredBy(): array
    {
        return $this->requiredBy->toArray();
    }
}
