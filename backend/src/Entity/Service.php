<?php

declare(strict_types=1);

namespace Neucore\Entity;

use Doctrine\ORM\Mapping as ORM;
use OpenApi\Annotations as OA;

/**
 * @OA\Schema(required={"id", "name"})
 *
 * @ORM\Entity()
 * @ORM\Table(name="services")
 */
class Service implements \JsonSerializable
{
    /**
     * @OA\Property()
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue
     * @var integer
     */
    private $id;

    /**
     * @OA\Property()
     * @ORM\Column(type="string", length=255)
     * @var string
     */
    private $name;

    /**
     * TODO split into individual properties: phpClass, psr4Prefix, psr4Path, groups, ...
     * @OA\Property()
     * @ORM\Column(type="text", length=16777215, nullable=true)
     * @var string|null
     */
    private $configuration;

    public function jsonSerialize(bool $onlyRequired = true): array
    {
        $data = [
            'id' => (int) $this->id,
            'name' => $this->name,
        ];
        if (! $onlyRequired) {
            $data['configuration'] = $this->configuration;
        }
        return $data;
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

    public function getName(): string
    {
        return (string) $this->name;
    }

    public function setConfiguration(string $configuration): self
    {
        $this->configuration = $configuration;
        return $this;
    }

    public function getConfiguration(): ?string
    {
        return $this->configuration;
    }
}
