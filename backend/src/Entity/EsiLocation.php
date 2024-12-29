<?php

declare(strict_types=1);

namespace Neucore\Entity;

use Doctrine\ORM\Mapping as ORM;
use OpenApi\Attributes as OA;

/**
 * An EVE location (System, Station, Structure, ...)
 */
#[ORM\Entity]
#[ORM\Table(name: "esi_locations", options: ["charset" => "utf8mb4", "collate" => "utf8mb4_unicode_520_ci"])]
#[OA\Schema(required: ['id', 'name', 'category'])]
class EsiLocation implements \JsonSerializable
{
    public const CATEGORY_SYSTEM = 'system';

    public const CATEGORY_STATION = 'station';

    public const CATEGORY_STRUCTURE = 'structure';

    #[ORM\Id]
    #[ORM\Column(type: "bigint")]
    #[ORM\GeneratedValue(strategy: "NONE")]
    #[OA\Property(format: 'int64')]
    private ?int $id = null;

    #[ORM\Column(type: "string", length: 16)]
    #[OA\Property(enum: ['system', 'station', 'structure'])]
    private ?string $category = null;

    #[ORM\Column(type: "string", length: 255, nullable: true)]
    #[OA\Property]
    private ?string $name = null;

    /**
     * Optional owner for category = structure.
     *
     */
    #[ORM\Column(name: "owner_id", type: "integer", nullable: true)]
    private ?int $ownerId = null;

    /**
     * Optional system for category = structure.
     *
     */
    #[ORM\Column(name: "system_id", type: "integer", nullable: true)]
    private ?int $systemId = null;

    /**
     * Last ESI update.
     *
     */
    #[ORM\Column(name: "last_update", type: "datetime", nullable: true)]
    private ?\DateTime $lastUpdate = null;

    #[ORM\Column(name: "error_count", type: "integer", nullable: true)]
    private ?int $errorCount = null;

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->getId(),
            'name' => $this->name,
            'category' => $this->category,
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
        return $this->id !== null ? (int) $this->id : null;
    }

    public function setName(string $name): self
    {
        $this->name = mb_substr($name, 0, 255);

        return $this;
    }

    public function getName(): string
    {
        return (string) $this->name;
    }

    /**
     * @param string $category One of the self::CATEGORY_* constants, invalid value is silently ignored.
     * @return EsiLocation
     */
    public function setCategory(string $category): self
    {
        if (in_array($category, [self::CATEGORY_SYSTEM, self::CATEGORY_STATION, self::CATEGORY_STRUCTURE])) {
            $this->category = $category;
        }

        return $this;
    }

    public function getCategory(): string
    {
        return (string) $this->category;
    }

    public function setOwnerId(int $id): self
    {
        $this->ownerId = $id;

        return $this;
    }

    public function getOwnerId(): ?int
    {
        return $this->ownerId;
    }

    public function setSystemId(int $id): self
    {
        $this->systemId = $id;

        return $this;
    }

    public function getSystemId(): ?int
    {
        return $this->systemId;
    }

    public function setLastUpdate(\DateTime $lastUpdate): self
    {
        $this->lastUpdate = clone $lastUpdate;

        return $this;
    }

    public function getLastUpdate(): ?\DateTime
    {
        return $this->lastUpdate;
    }

    public function setErrorCount(int $errorCount): self
    {
        $this->errorCount = $errorCount;

        return $this;
    }

    public function getErrorCount(): int
    {
        return $this->errorCount !== null ? $this->errorCount : 0;
    }
}
