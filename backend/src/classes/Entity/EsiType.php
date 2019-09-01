<?php declare(strict_types=1);

namespace Neucore\Entity;

use Doctrine\ORM\Mapping as ORM;
use OpenApi\Annotations as OA;

/**
 * An EVE name from the category "inventory_type".
 *
 * @OA\Schema(
 *     required={"id", "name"}
 * )
 * @ORM\Entity
 * @ORM\Table(name="esi_types")
 */
class EsiType implements \JsonSerializable
{
    /**
     * @OA\Property(format="int64")
     * @ORM\Id
     * @ORM\Column(type="bigint")
     * @ORM\GeneratedValue(strategy="NONE")
     * @var integer
     */
    private $id;

    /**
     * @OA\Property()
     * @ORM\Column(type="string", length=255, nullable=true)
     * @var string
     */
    private $name;

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
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
}
