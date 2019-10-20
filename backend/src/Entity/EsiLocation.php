<?php declare(strict_types=1);

namespace Neucore\Entity;

use Doctrine\ORM\Mapping as ORM;
use OpenApi\Annotations as OA;

/**
 * An EVE location (System, Station, Structure, ...)
 *
 * @OA\Schema(
 *     required={"id", "name", "category"}
 * )
 * @ORM\Entity
 * @ORM\Table(name="esi_locations")
 */
class EsiLocation implements \JsonSerializable
{
    const CATEGORY_SYSTEM = 'system';

    const CATEGORY_STATION = 'station';

    const CATEGORY_STRUCTURE = 'structure';

    /**
     * @OA\Property(format="int64")
     * @ORM\Id
     * @ORM\Column(type="bigint")
     * @ORM\GeneratedValue(strategy="NONE")
     * @var integer
     */
    private $id;

    /**
     * @OA\Property(enum={"system", "station", "structure"})
     * @ORM\Column(type="string", length=16)
     * @var string
     */
    private $category;

    /**
     * @OA\Property()
     * @ORM\Column(type="string", length=255, nullable=true)
     * @var string
     */
    private $name;

    /**
     * Optional owner for category = structure.
     *
     * @ORM\Column(type="integer", name="owner_id", nullable=true)
     * @var integer|null
     */
    private $ownerId;

    /**
     * Optional system for category = structure.
     *
     * @ORM\Column(type="integer", name="system_id", nullable=true)
     * @var integer|null
     */
    private $systemId;

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
}
