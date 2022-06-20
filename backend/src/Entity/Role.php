<?php

declare(strict_types=1);

namespace Neucore\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
/* @phan-suppress-next-line PhanUnreferencedUseNormal */
use Doctrine\ORM\Mapping as ORM;
/* @phan-suppress-next-line PhanUnreferencedUseNormal */
use OpenApi\Annotations as OA;

/**
 * Roles are used to determined player permissions.
 *
 * @OA\Schema(
 *     type="string",
 *     enum={"app", "app-groups", "app-chars", "app-tracking", "app-esi", "user", "user-admin", "user-manager",
 *           "user-chars", "group-admin", "group-manager", "app-admin", "app-manager", "service-admin", "statistics",
 *           "esi", "settings", "tracking", "tracking-admin", "watchlist", "watchlist-manager", "watchlist-admin"})
 * )
 *
 * @ORM\Entity
 * @ORM\Table(name="roles")
 *
 * @see doc/API.md for role descriptions
 */
class Role implements \JsonSerializable
{
    public const APP = 'app';
    public const APP_GROUPS = 'app-groups';
    public const APP_CHARS = 'app-chars';
    public const APP_TRACKING = 'app-tracking';
    public const APP_ESI = 'app-esi';

    public const ANONYMOUS = 'anonymous';

    public const USER = 'user';
    public const USER_ADMIN = 'user-admin';
    public const USER_MANAGER = 'user-manager';
    public const USER_CHARS = 'user-chars';
    public const GROUP_ADMIN = 'group-admin';
    public const SERVICE_ADMIN = 'service-admin';
    public const STATISTICS = 'statistics';
    public const APP_ADMIN = 'app-admin';
    public const ESI = 'esi';
    public const SETTINGS = 'settings';
    public const TRACKING_ADMIN = 'tracking-admin';
    public const WATCHLIST_ADMIN = 'watchlist-admin';

    public const GROUP_MANAGER = 'group-manager';
    public const APP_MANAGER = 'app-manager';
    public const TRACKING = 'tracking';
    public const WATCHLIST = 'watchlist';
    public const WATCHLIST_MANAGER = 'watchlist-manager';

    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="NONE")
     */
    private int $id;

    /**
     * Role name.
     *
     * @ORM\Column(type="string", unique=true, length=64)
     */
    private string $name = '';

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
    public function jsonSerialize(): string
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

    public function getId(): int
    {
        return $this->id;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function addPlayer(Player $player): self
    {
        $this->players[] = $player;

        return $this;
    }

    public function removePlayer(Player $player): bool
    {
        return $this->players->removeElement($player);
    }

    /**
     * @return Player[]
     */
    public function getPlayers(): array
    {
        return array_values($this->players->toArray());
    }

    public function addApp(App $app): self
    {
        $this->apps[] = $app;

        return $this;
    }

    public function removeApp(App $app): bool
    {
        return $this->apps->removeElement($app);
    }

    /**
     * @return App[]
     */
    public function getApps(): array
    {
        return array_values($this->apps->toArray());
    }
}
