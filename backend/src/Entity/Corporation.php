<?php

declare(strict_types=1);

namespace Neucore\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Neucore\Api;
/* @phan-suppress-next-line PhanUnreferencedUseNormal */
use OpenApi\Annotations as OA;

/**
 * EVE corporation.
 *
 * @OA\Schema(
 *     required={"id", "name", "ticker"}
 * )
 */
#[ORM\Entity]
#[ORM\Table(name: "corporations", options: ["charset" => "utf8mb4", "collate" => "utf8mb4_unicode_520_ci"])]
class Corporation implements \JsonSerializable
{
    /**
     * EVE corporation ID.
     *
     * @OA\Property(format="int64")
     */
    #[ORM\Id]
    #[ORM\Column(type: "bigint")]
    #[ORM\GeneratedValue(strategy: "NONE")]
    private ?int $id = null;

    /**
     * EVE corporation name.
     *
     * @OA\Property(nullable=true)
     */
    #[ORM\Column(type: "string", length: 255, nullable: true)]
    private ?string $name = null;

    /**
     * Corporation ticker.
     *
     * @OA\Property(nullable=true)
     */
    #[ORM\Column(type: "string", length: 16, nullable: true)]
    private ?string $ticker = null;

    /**
     * Last ESI update.
     *
     */
    #[ORM\Column(name: "last_update", type: "datetime", nullable: true)]
    private ?\DateTime $lastUpdate = null;

    /**
     *
     * @OA\Property(ref="#/components/schemas/Alliance", nullable=false)
     */
    #[ORM\ManyToOne(targetEntity: "Alliance", inversedBy: "corporations")]
    private ?Alliance $alliance = null;

    /**
     * Groups for automatic assignment (API: not included by default).
     *
     * @OA\Property(type="array", @OA\Items(ref="#/components/schemas/Group"))
     */
    #[ORM\ManyToMany(targetEntity: "Group", inversedBy: "corporations")]
    #[ORM\JoinTable(name: "corporation_group")]
    #[ORM\OrderBy(["name" => "ASC"])]
    private Collection $groups;

    /**
     * Groups those members may see this corporation member tracking data.
     *
     */
    #[ORM\ManyToMany(targetEntity: "Group")]
    #[ORM\JoinTable(name: "corporation_group_tracking")]
    #[ORM\OrderBy(["name" => "ASC"])]
    private Collection $groupsTracking;

    /**
     * Last update of corporation member tracking data (API: not included by default).
     *
     * @OA\Property(nullable=true)
     */
    #[ORM\Column(name: "tracking_last_update", type: "datetime", nullable: true)]
    private ?\DateTime $trackingLastUpdate = null;

    #[ORM\OneToMany(mappedBy: "corporation", targetEntity: "Character")]
    #[ORM\OrderBy(["name" => "ASC"])]
    private Collection $characters;

    #[ORM\OneToMany(mappedBy: "corporation", targetEntity: "CorporationMember")]
    #[ORM\OrderBy(["name" => "ASC"])]
    private Collection $members;

    /**
     * True if this corporation was automatically placed on the allowlist of a watchlist (API: not included by default).
     *
     * @OA\Property(type="boolean")
     */
    #[ORM\Column(name: "auto_allowlist", type: "boolean", nullable: false, options: ["default" => 0])]
    private bool $autoAllowlist = false;

    /**
     * Contains only information that is of interest for clients.
     *
     * {@inheritDoc}
     * @see \JsonSerializable::jsonSerialize()
     */
    public function jsonSerialize(
        bool $includeTrackingDate = false,
        bool $includeAutoAllowlist = false,
        bool $includeAlliance = true
    ): array {
        $data = [
            'id' => $this->getId(),
            'name' => $this->name,
            'ticker' => $this->ticker,
        ];

        if ($includeAlliance) {
            $data['alliance'] = $this->alliance;
        }

        if ($includeTrackingDate) {
            $data['trackingLastUpdate'] = $this->trackingLastUpdate?->format(Api::DATE_FORMAT);
        }

        if ($includeAutoAllowlist) {
            $data['autoAllowlist'] = $this->autoAllowlist;
        }

        return $data;
    }

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->groups = new ArrayCollection();
        $this->groupsTracking = new ArrayCollection();
        $this->characters = new ArrayCollection();
        $this->members = new ArrayCollection();
    }

    public function setId(int $id): self
    {
        $this->id = $id;

        return $this;
    }

    /**
     * Get id.
     */
    public function getId(): int
    {
        // cast to int because Doctrine creates string for type bigint, also make sure it's no null
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

    public function getLastUpdate(): ?\DateTime
    {
        return $this->lastUpdate;
    }

    public function setAlliance(?Alliance $alliance = null): self
    {
        $this->alliance = $alliance;

        return $this;
    }

    public function getAlliance(): ?Alliance
    {
        return $this->alliance;
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

    public function hasGroup(int $groupId): bool
    {
        foreach ($this->getGroups() as $g) {
            if ($g->getId() === $groupId) {
                return true;
            }
        }
        return false;
    }

    public function addGroupTracking(Group $group): self
    {
        $this->groupsTracking[] = $group;

        return $this;
    }

    public function removeGroupTracking(Group $group): bool
    {
        return $this->groupsTracking->removeElement($group);
    }

    /**
     * @return Group[]
     */
    public function getGroupsTracking(): array
    {
        return array_values($this->groupsTracking->toArray());
    }

    /**
     * @return int[]
     */
    public function getGroupsTrackingIds(): array
    {
        return array_map(function (Group $group) {
            return $group->getId();
        }, array_values($this->groupsTracking->toArray()));
    }

    public function hasGroupTracking(int $groupId): bool
    {
        foreach ($this->getGroupsTracking() as $g) {
            if ($g->getId() === $groupId) {
                return true;
            }
        }
        return false;
    }

    public function setTrackingLastUpdate(\DateTime $trackingLastUpdate): self
    {
        $this->trackingLastUpdate = clone $trackingLastUpdate;

        return $this;
    }

    public function getTrackingLastUpdate(): ?\DateTime
    {
        return $this->trackingLastUpdate;
    }

    public function addCharacter(Character $character): self
    {
        $this->characters[] = $character;

        return $this;
    }

    public function removeCharacter(Character $character): bool
    {
        return $this->characters->removeElement($character);
    }

    /**
     * @return Character[]
     */
    public function getCharacters(): array
    {
        return array_values($this->characters->toArray());
    }

    public function addMember(CorporationMember $member): self
    {
        $this->members[] = $member;

        return $this;
    }

    public function removeMember(CorporationMember $member): bool
    {
        return $this->members->removeElement($member);
    }

    /**
     * @return CorporationMember[]
     */
    public function getMembers(): array
    {
        return array_values($this->members->toArray());
    }

    public function setAutoAllowlist(bool $autoAllowlist): self
    {
        $this->autoAllowlist = $autoAllowlist;

        return $this;
    }

    public function getAutoAllowlist(): bool
    {
        return $this->autoAllowlist;
    }
}
