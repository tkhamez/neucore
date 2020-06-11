<?php

declare(strict_types=1);

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
 *           "user-chars", "group-admin", "group-manager", "app-admin", "app-manager", "esi", "settings", "tracking",
 *           "tracking-admin", "watchlist", "watchlist-manager", "watchlist-admin"})
 * )
 *
 * @ORM\Entity
 * @ORM\Table(name="roles")
 *
 * @see doc/API.md for role descriptions
 */
class Role implements \JsonSerializable
{
    const APP = 'app';
    const APP_GROUPS = 'app-groups';
    const APP_CHARS = 'app-chars';
    const APP_TRACKING = 'app-tracking';
    const APP_ESI = 'app-esi';

    const ANONYMOUS = 'anonymous';

    const USER = 'user';
    const USER_ADMIN = 'user-admin';
    const USER_MANAGER = 'user-manager';
    const USER_CHARS = 'user-chars';
    const GROUP_ADMIN = 'group-admin';
    const APP_ADMIN = 'app-admin';
    const ESI = 'esi';
    const SETTINGS = 'settings';
    const TRACKING_ADMIN = 'tracking-admin';
    const WATCHLIST_ADMIN = 'watchlist-admin';

    const GROUP_MANAGER = 'group-manager';
    const APP_MANAGER = 'app-manager';
    const TRACKING = 'tracking';
    const WATCHLIST = 'watchlist';
    const WATCHLIST_MANAGER = 'watchlist-manager';

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
    public function jsonSerialize(): ?string
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
