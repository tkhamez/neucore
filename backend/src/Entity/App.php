<?php

declare(strict_types=1);

namespace Neucore\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use OpenApi\Attributes as OA;

#[ORM\Entity]
#[ORM\Table(name: "apps", options: ["charset" => "utf8mb4", "collate" => "utf8mb4_unicode_520_ci"])]
#[OA\Schema(required: ['id', 'name'])]
class App implements \JsonSerializable
{
    /**
     * App ID
     */
    #[ORM\Id]
    #[ORM\Column(type: "integer")]
    #[ORM\GeneratedValue]
    #[OA\Property]
    private ?int $id = null;

    /**
     * App name
     */
    #[ORM\Column(type: "string", length: 255)]
    #[OA\Property(maxLength: 255)]
    private ?string $name = null;

    #[ORM\Column(type: "string", length: 255)]
    private ?string $secret = null;

    /**
     * Roles for authorization.
     */
    #[ORM\ManyToMany(targetEntity: Role::class, inversedBy: "apps")]
    #[ORM\OrderBy(["name" => "ASC"])]
    #[OA\Property(type: 'array', items: new OA\Items(ref: '#/components/schemas/Role'))]
    private Collection $roles;

    /**
     * Groups the app can see.
     */
    #[ORM\ManyToMany(targetEntity: Group::class, inversedBy: "apps")]
    #[ORM\OrderBy(["name" => "ASC"])]
    #[OA\Property(type: 'array', items: new OA\Items(ref: '#/components/schemas/Group'))]
    private Collection $groups;

    #[ORM\ManyToMany(targetEntity: Player::class, inversedBy: "managerApps")]
    #[ORM\JoinTable(name: "app_manager")]
    #[ORM\OrderBy(["name" => "ASC"])]
    private Collection $managers;

    #[ORM\ManyToMany(targetEntity: EveLogin::class)]
    #[ORM\JoinTable(name: "app_eve_login")]
    #[ORM\OrderBy(["name" => "ASC"])]
    #[OA\Property(type: 'array', items: new OA\Items(ref: '#/components/schemas/EveLogin'))]
    private Collection $eveLogins;

    /**
     * Contains only information of interest to clients.
     *
     * {@inheritDoc}
     * @see \JsonSerializable::jsonSerialize()
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'groups' => $this->getGroups(),
            'roles' => $this->getRoles(),
            'eveLogins' => $this->getEveLogins(),
        ];
    }

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->roles = new ArrayCollection();
        $this->groups = new ArrayCollection();
        $this->managers = new ArrayCollection();
        $this->eveLogins = new ArrayCollection();
    }

    public function getId(): int
    {
        return (int) $this->id;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * @param string $secret The hashed string, *not* the plain text password.
     */
    public function setSecret(string $secret): self
    {
        $this->secret = $secret;

        return $this;
    }

    public function getSecret(): string
    {
        return (string) $this->secret;
    }

    public function addRole(Role $role): self
    {
        $this->roles[] = $role;

        return $this;
    }

    public function removeRole(Role $role): bool
    {
        return $this->roles->removeElement($role);
    }

    /**
     * @return Role[]
     */
    public function getRoles(): array
    {
        return array_values($this->roles->toArray());
    }

    /**
     * @return string[]
     */
    public function getRoleNames(): array
    {
        $names = [];
        foreach ($this->getRoles() as $role) {
            $names[] = $role->getName();
        }

        return $names;
    }

    public function hasRole(string $name): bool
    {
        return in_array($name, $this->getRoleNames());
    }

    public function addGroup(Group $group): self
    {
        $this->groups[] = $group;

        return $this;
    }

    public function removeGroup(Group $group): bool
    {
        return $this->groups->removeElement($group);
    }

    /**
     * @return Group[]
     */
    public function getGroups(): array
    {
        return array_values($this->groups->toArray());
    }

    public function addManager(Player $manager): self
    {
        $this->managers[] = $manager;

        return $this;
    }

    public function removeManager(Player $manager): bool
    {
        return $this->managers->removeElement($manager);
    }

    /**
     * @return Player[]
     */
    public function getManagers(): array
    {
        return array_values($this->managers->toArray());
    }

    public function isManager(Player $player): bool
    {
        $isManager = false;

        foreach ($this->getManagers() as $m) {
            if ($m->getId() === $player->getId()) {
                $isManager = true;
                break;
            }
        }

        return $isManager;
    }

    public function addEveLogin(EveLogin $eveLogins): self
    {
        $this->eveLogins[] = $eveLogins;

        return $this;
    }

    public function removeEveLogin(EveLogin $eveLogins): bool
    {
        return $this->eveLogins->removeElement($eveLogins);
    }

    /**
     * @return EveLogin[]
     */
    public function getEveLogins(): array
    {
        return array_values($this->eveLogins->toArray());
    }
}
