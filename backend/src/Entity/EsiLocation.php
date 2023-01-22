<?php

declare(strict_types=1);

namespace Neucore\Entity;

/* @phan-suppress-next-line PhanUnreferencedUseNormal */
use Doctrine\ORM\Mapping as ORM;
/* @phan-suppress-next-line PhanUnreferencedUseNormal */
use OpenApi\Annotations as OA;

/**
 * An EVE location (System, Station, Structure, ...)
 *
 * @OA\Schema(
 *     required={"id", "name", "category"}
 * )
 * @ORM\Entity
 * @ORM\Table(name="esi_locations", options={"charset"="utf8mb4", "collate"="utf8mb4_unicode_520_ci"})
 */
class EsiLocation implements \JsonSerializable
{
    public const CATEGORY_SYSTEM = 'system';

    public const CATEGORY_STATION = 'station';

    public const CATEGORY_STRUCTURE = 'structure';

    /**
     * @OA\Property(format="int64")
     * @ORM\Id
     * @ORM\Column(type="bigint")
     * @ORM\GeneratedValue(strategy="NONE")
     */
    private ?int $id = null;

    /**
     * @OA\Property(enum={"system", "station", "structure"})
     * @ORM\Column(type="string", length=16)
     */
    private ?string $category = null;

    /**
     * @OA\Property()
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private ?string $name = null;

    /**
     * Optional owner for category = structure.
     *
     * @ORM\Column(type="integer", name="owner_id", nullable=true)
     */
    private ?int $ownerId = null;

    /**
     * Optional system for category = structure.
     *
     * @ORM\Column(type="integer", name="system_id", nullable=true)
     */
    private ?int $systemId = null;

    /**
     * Last ESI update.
     *
     * @ORM\Column(type="datetime", name="last_update", nullable=true)
     */
    private ?\DateTime $lastUpdate = null;

    /**
     * @ORM\Column(type="integer", name="error_count", nullable=true)
     */
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
