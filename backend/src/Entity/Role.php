<?php

declare(strict_types=1);

namespace Neucore\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
/* @phan-suppress-next-line PhanUnreferencedUseNormal */
use OpenApi\Annotations as OA;

/**
 * Roles are used to determined player permissions.
 *
 * @OA\Schema(
 *     type="string",
 *     enum={"app", "app-groups", "app-chars", "app-tracking", "app-esi-login", "app-esi-proxy", "app-esi-token",
 *           "user", "user-admin", "user-manager", "user-chars", "group-admin", "group-manager", "app-admin",
 *           "app-manager", "plugin-admin", "statistics", "esi", "settings", "tracking", "tracking-admin",
 *           "watchlist", "watchlist-manager", "watchlist-admin"}
 * )
 *
 * @see doc/API.md for role descriptions
 */
#[ORM\Entity]
#[ORM\Table(name: "roles", options: ["charset" => "utf8mb4", "collate" => "utf8mb4_unicode_520_ci"])]
class Role implements \JsonSerializable
{
    public const APP = 'app';
    public const APP_GROUPS = 'app-groups';
    public const APP_CHARS = 'app-chars';
    public const APP_TRACKING = 'app-tracking';
    public const APP_ESI_LOGIN = 'app-esi-login';
    public const APP_ESI_PROXY = 'app-esi-proxy';
    public const APP_ESI_TOKEN = 'app-esi-token';

    public const ANONYMOUS = 'anonymous';

    public const USER = 'user';
    public const USER_ADMIN = 'user-admin';
    public const USER_MANAGER = 'user-manager';
    public const USER_CHARS = 'user-chars';
    public const GROUP_ADMIN = 'group-admin';
    public const PLUGIN_ADMIN = 'plugin-admin';
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

    public const ROLES_WITH_GROUP_REQUIREMENT = [
        Role::USER_ADMIN,
        Role::USER_MANAGER,
        Role::USER_CHARS,
        Role::GROUP_ADMIN,
        Role::PLUGIN_ADMIN,
        Role::STATISTICS,
        Role::APP_ADMIN,
        Role::ESI,
        Role::SETTINGS,
        Role::TRACKING_ADMIN,
        Role::WATCHLIST_ADMIN,
        Role::GROUP_MANAGER,
        Role::APP_MANAGER,
        // Not tracking, watchlist and watchlist-manager because these are only assigned based on groups.
    ];

    #[ORM\Id] #[ORM\Column(type: "integer")]
    #[ORM\GeneratedValue(strategy: "NONE")]
    private int $id;

    /**
     * Role name.
     *
     */
    #[ORM\Column(type: "string", length: 64, unique: true)]
    private string $name = '';

    #[ORM\ManyToMany(targetEntity: Player::class, mappedBy: "roles")]
    #[ORM\OrderBy(["name" => "ASC"])]
    private Collection $players;

    #[ORM\ManyToMany(targetEntity: App::class, mappedBy: "roles")]
    #[ORM\OrderBy(["name" => "ASC"])]
    private Collection $apps;

    #[ORM\ManyToMany(targetEntity: Group::class)]
    #[ORM\JoinTable(name: "role_required_group")]
    #[ORM\OrderBy(["name" => "ASC"])]
    private Collection $requiredGroups;

    /**
     * Contains only information that is of interest to clients.
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
        $this->requiredGroups = new ArrayCollection();
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

    /**
     * @return Group[]
     */
    public function getRequiredGroups(): array
    {
        return array_values($this->requiredGroups->toArray());
    }

    public function addRequiredGroup(Group $requiredGroup): self
    {
        foreach ($this->getRequiredGroups() as $entity) {
            if ($requiredGroup->getId() && $entity->getId() === $requiredGroup->getId()) {
                return $this;
            }
        }

        $this->requiredGroups[] = $requiredGroup;

        return $this;
    }

    public function removeRequiredGroup(Group $requiredGroup): bool
    {
        return $this->requiredGroups->removeElement($requiredGroup);
    }
}
