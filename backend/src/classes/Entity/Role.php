<?php declare(strict_types=1);

namespace Neucore\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use OpenApi\Annotations as OA;

/**
 * Roles are used to determined player permissions.
 *
 * @OA\Schema(
 *     type="string",
 *     enum={"app", "app-groups", "app-chars", "app-tracking", "app-esi", "user", "user-admin", "user-manager",
 *           "app-admin", "app-manager", "group-admin", "group-manager", "esi", "settings", "tracking"})
 * )
 *
 * @ORM\Entity
 * @ORM\Table(name="roles")
 */
class Role implements \JsonSerializable
{
    /**
     * Role for third party apps.
     *
     * @var string
     */
    const APP = 'app';

    /**
     * Allows an app to get groups from a player account.
     *
     * @var string
     */
    const APP_GROUPS = 'app-groups';

    /**
     * Allows an app to get characters from a player account.
     *
     * @var string
     */
    const APP_CHARS = 'app-chars';

    /**
     * Allows an app to get corporation member tracking data.
     *
     * @var string
     */
    const APP_TRACKING = 'app-tracking';

    /**
     * Allows an app to make an ESI request on behalf of a character from the database.
     *
     * @var string
     */
    const APP_ESI = 'app-esi';

    /**
     * This role is given to unauthenticated user.
     *
     * @var string
     */
    const ANONYMOUS = 'anonymous';

    /**
     * This role is given to every authenticated user.
     *
     * @var string
     */
    const USER = 'user';

    /**
     * Allows a player to add and remove roles from players.
     *
     * @var string
     */
    const USER_ADMIN = 'user-admin';

    /**
     * Allows a player to add and remove groups from players with "managed" status.
     *
     * @var string
     */
    const USER_MANAGER = 'user-manager';

    /**
     * Allows a player to create apps and add and remove managers and roles.
     *
     * @var string
     */
    const APP_ADMIN = 'app-admin';

    /**
     * Allows a player to change the secret of his apps.
     *
     * @var string
     */
    const APP_MANAGER = 'app-manager';

    /**
     * Allows a player to create groups and add and remove managers or corporation and alliances.
     *
     * @var string
     */
    const GROUP_ADMIN = 'group-admin';

    /**
     * Allows a player to add and remove members to his groups.
     *
     * @var string
     */
    const GROUP_MANAGER = 'group-manager';

    /**
     * Allows a player to make an ESI request on behalf of a character from the database.
     *
     * @var string
     */
    const ESI = 'esi';

    /**
     * Allows a player to change the system settings.
     *
     * @var string
     */
    const SETTINGS = 'settings';

    /**
     * Allows a player to view corporation member tracking data.
     *
     * @var string
     */
    const TRACKING = 'tracking';

    /**
     * Allows a player to change the tracking corporation/groups configuration.
     *
     * @var string
     */
    const TRACKING_ADMIN = 'tracking-admin';

    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="NONE")
     * @var integer
     */
    private $id;

    /**
     * Role name.
     *
     * @ORM\Column(type="string", unique=true, length=64)
     * @var string
     */
    private $name;

    /**
     * @ORM\ManyToMany(targetEntity="Player", mappedBy="roles")
     * @ORM\OrderBy({"name" = "ASC"})
     * @var Collection
     */
    private $players;

    /**
     * @ORM\ManyToMany(targetEntity="App", mappedBy="roles")
     * @ORM\OrderBy({"name" = "ASC"})
     * @var Collection
     */
    private $apps;

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
    public function __construct(int $id)
    {
        $this->id = $id;
        $this->players = new ArrayCollection();
        $this->apps = new ArrayCollection();
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
     * Add player.
     *
     * @param Player $player
     *
     * @return Role
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
     * Add app.
     *
     * @param App $app
     *
     * @return Role
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
}
