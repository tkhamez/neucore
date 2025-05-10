<?php

declare(strict_types=1);

namespace Neucore\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use OpenApi\Attributes as OA;

/**
 * EVE Alliance.
 */
#[ORM\Table(name: "alliances", options: ["charset" => "utf8mb4", "collate" => "utf8mb4_unicode_520_ci"])]
#[ORM\Entity]
#[OA\Schema(required: ['id', 'name', 'ticker'])]
class Alliance implements \JsonSerializable
{
    /**
     * EVE alliance ID.
     */
    #[ORM\Id]
    #[ORM\Column(type: "bigint")]
    #[ORM\GeneratedValue(strategy: "NONE")]
    #[OA\Property(format: 'int64')]
    private ?int $id = null;

    /**
     * EVE alliance name.
     */
    #[ORM\Column(type: "string", length: 255, nullable: true)]
    #[OA\Property(nullable: true)]
    private ?string $name = null;

    /**
     * Alliance ticker.
     */
    #[ORM\Column(type: "string", length: 16, nullable: true)]
    #[OA\Property(nullable: true)]
    private ?string $ticker = null;

    /**
     * Last ESI update.
     *
     */
    #[ORM\Column(name: "last_update", type: "datetime", nullable: true)]
    private ?\DateTime $lastUpdate = null;

    #[ORM\OneToMany(targetEntity: Corporation::class, mappedBy: "alliance")]
    #[ORM\OrderBy(["name" => "ASC"])]
    private Collection $corporations;

    /**
     * Groups for automatic assignment (API: not included by default).
     */
    #[ORM\ManyToMany(targetEntity: Group::class, inversedBy: "alliances")]
    #[ORM\JoinTable(name: "alliance_group")]
    #[ORM\OrderBy(["name" => "ASC"])]
    #[OA\Property(type: 'array', items: new OA\Items(ref: '#/components/schemas/Group'))]
    private Collection $groups;

    /**
     * Contains only information of interest to clients.
     *
     * {@inheritDoc}
     * @see \JsonSerializable::jsonSerialize()
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->getId(),
            'name' => $this->name,
            'ticker' => $this->ticker,
            // API: groups are not included by default
        ];
    }

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->groups = new ArrayCollection();
        $this->corporations = new ArrayCollection();
    }

    public function setId(int $id): Alliance
    {
        $this->id = $id;

        return $this;
    }

    public function getId(): int
    {
        // Cast to int because Doctrine creates string for type bigint, also make sure it's not null.
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

    public function setTicker(string $ticker): self
    {
        $this->ticker = $ticker;

        return $this;
    }

    public function getTicker(): ?string
    {
        return $this->ticker;
    }

    public function setLastUpdate(\DateTime $lastUpdate): self
    {
        $this->lastUpdate = clone $lastUpdate;

        return $this;
    }

    /**
     * Get lastUpdate.
     */
    public function getLastUpdate(): ?\DateTime
    {
        return $this->lastUpdate;
    }

    public function addCorporation(Corporation $corporation): self
    {
        $this->corporations[] = $corporation;

        return $this;
    }

    /**
     * @return boolean TRUE if this collection contained the specified element, FALSE otherwise.
     */
    public function removeCorporation(Corporation $corporation): bool
    {
        return $this->corporations->removeElement($corporation);
    }

    /**
     * @return Corporation[]
     */
    public function getCorporations(): array
    {
        return array_values($this->corporations->toArray());
    }

    public function addGroup(Group $group): self
    {
        $this->groups[] = $group;

        return $this;
    }

    /**
     * @return boolean TRUE if this collection contained the specified element, FALSE otherwise.
     */
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

    public function hasGroup(int $groupId): bool
    {
        foreach ($this->getGroups() as $g) {
            if ($g->getId() === $groupId) {
                return true;
            }
        }
        return false;
    }
}
