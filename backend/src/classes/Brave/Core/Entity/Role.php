<?php declare(strict_types=1);

namespace Brave\Core\Entity;

/**
 * Roles are used to determined player permissions.
 *
 * @SWG\Definition(
 *     definition="Role",
 *     type="string",
 *     enum={"app-admin", "app-manager", "group-admin", "group-manager", "user", "user-admin", "esi", "settings", "tracking"})
 * )
 *
 * @Entity
 * @Table(name="roles")
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
     * Allows an app to get corporation member tracking data.
     */
    const APP_TRACKING = 'app-tracking';

    /**
     * Allows an app to make an ESI request on behalf of a character from the database.
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
     * @Id
     * @Column(type="integer")
     * @GeneratedValue
     * @var integer
     */
    private $id;

    /**
     * Role name.
     *
     * @Column(type="string", unique=true, length=64)
     * @var string
     */
    private $name;

    /**
     * @ManyToMany(targetEntity="Player", mappedBy="roles")
     * @OrderBy({"name" = "ASC"})
     * @var \Doctrine\Common\Collections\Collection
     */
    private $players;

    /**
     * @ManyToMany(targetEntity="App", mappedBy="roles")
     * @OrderBy({"name" = "ASC"})
     * @var \Doctrine\Common\Collections\Collection
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
    public function __construct()
    {
        $this->players = new \Doctrine\Common\Collections\ArrayCollection();
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
     * @param \Brave\Core\Entity\Player $player
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
     * @param \Brave\Core\Entity\Player $player
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
     * @param \Brave\Core\Entity\App $app
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
     * @param \Brave\Core\Entity\App $app
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
