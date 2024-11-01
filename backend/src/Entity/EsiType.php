<?php

declare(strict_types=1);

namespace Neucore\Entity;

use Doctrine\ORM\Mapping as ORM;
use OpenApi\Attributes as OA;

/**
 * An EVE name from the category "inventory_type".
 */
#[ORM\Entity]
#[ORM\Table(name: "esi_types", options: ["charset" => "utf8mb4", "collate" => "utf8mb4_unicode_520_ci"])]
#[OA\Schema(required: ['id', 'name'])]
class EsiType implements \JsonSerializable
{
    #[ORM\Id]
    #[ORM\Column(type: "bigint")]
    #[ORM\GeneratedValue(strategy: "NONE")]
    #[OA\Property(format: 'int64')]
    private ?int $id = null;

    #[ORM\Column(type: "string", length: 255, nullable: true)]
    #[OA\Property]
    private ?string $name = null;

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->getId(),
            'name' => $this->name,
        ];
    }

    public function setId(int $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getId(): ?int
    {
        // cast to int because Doctrine creates string for type bigint
        return $this->id !== null ? (int)$this->id : null;
    }

    public function setName(string $name): self
    {
        $this->name = mb_substr($name, 0, 255);

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }
}
