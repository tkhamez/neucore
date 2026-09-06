<?php

declare(strict_types=1);

namespace Neucore\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use OpenApi\Attributes as OA;

#[ORM\Entity]
#[ORM\Table(name: "mcp_tokens", options: ["charset" => "utf8mb4", "collate" => "utf8mb4_unicode_520_ci"])]
#[OA\Schema(required: ['id', 'name'])]
class McpToken implements \JsonSerializable
{
    #[ORM\Id]
    #[ORM\Column(type: "integer")]
    #[ORM\GeneratedValue]
    #[OA\Property]
    /** @phpstan-ignore property.onlyRead */
    private int $id;

    #[ORM\Column(type: "string", length: 255)]
    #[OA\Property(maxLength: 255)]
    private ?string $name = null;

    #[ORM\Column(type: "string", length: 255)]
    private ?string $secret = null;

    #[ORM\ManyToMany(targetEntity: Role::class)]
    #[ORM\JoinTable(
        name: "mcp_token_role",
        joinColumns: [new ORM\JoinColumn(name: "mcp_token_id", referencedColumnName: "id")],
        inverseJoinColumns: [new ORM\JoinColumn(name: "role_id", referencedColumnName: "id")],
    )]
    #[ORM\OrderBy(["name" => "ASC"])]
    #[OA\Property(type: 'array', items: new OA\Items(ref: '#/components/schemas/Role'))]
    private Collection $roles;

    public function __construct()
    {
        $this->roles = new ArrayCollection();
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

    public function getName(): ?string
    {
        return $this->name;
    }

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

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'roles' => $this->getRoles(),
        ];
    }
}
