<?php declare(strict_types=1);

namespace Neucore\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use OpenApi\Annotations as OA;

/**
 * EVE Alliance.
 *
 * @OA\Schema(
 *     required={"id", "name", "ticker"}
 * )
 * @ORM\Entity
 * @ORM\Table(name="alliances")
 */
class Alliance implements \JsonSerializable
{
    /**
     * EVE alliance ID.
     *
     * @OA\Property(format="int64")
     * @ORM\ID
     * @ORM\Column(type="bigint")
     * @ORM\GeneratedValue(strategy="NONE")
     * @var integer
     */
    private $id;

    /**
     * EVE alliance name.
     *
     * @OA\Property(nullable=true)
     * @ORM\Column(type="string", length=255, nullable=true)
     * @var string
     */
    private $name;

    /**
     * Alliance ticker.
     *
     * @OA\Property(nullable=true)
     * @ORM\Column(type="string", length=16, nullable=true)
     * @var string
     */
    private $ticker;

    /**
     * Last ESI update.
     *
     * @ORM\Column(type="datetime", name="last_update", nullable=true)
     * @var \DateTime
     */
    private $lastUpdate;

    /**
     *
     * @ORM\OneToMany(targetEntity="Corporation", mappedBy="alliance")
     * @ORM\OrderBy({"name" = "ASC"})
     * @var Collection
     */
    private $corporations;

    /**
     * Groups for automatic assignment (API: not included by default).
     *
     * @OA\Property(type="array", @OA\Items(ref="#/components/schemas/Group"))
     * @ORM\ManyToMany(targetEntity="Group", inversedBy="alliances")
     * @ORM\JoinTable(name="alliance_group")
     * @ORM\OrderBy({"name" = "ASC"})
     * @var Collection
     */
    private $groups;

    /**
     * Contains only information that is of interest for clients.
     *
     * {@inheritDoc}
     * @see \JsonSerializable::jsonSerialize()
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->getId(),
            'name' => $this->name,
            'ticker' => $this->ticker
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

    /**
     * Set id.
     *
     * @param int $id
     *
     * @return Alliance
     */
    public function setId(int $id)
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

    /**
     * Set name.
     *
     * @param string $name
     *
     * @return Alliance
     */
    public function setName(string $name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get name.
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set ticker.
     *
     * @param string $ticker
     *
     * @return Alliance
     */
    public function setTicker(string $ticker)
    {
        $this->ticker = $ticker;

        return $this;
    }

    /**
     * Get ticker.
     *
     * @return string
     */
    public function getTicker()
    {
        return $this->ticker;
    }

    /**
     * Set lastUpdate.
     *
     * @param \DateTime $lastUpdate
     *
     * @return Alliance
     */
    public function setLastUpdate($lastUpdate)
    {
        $this->lastUpdate = clone $lastUpdate;

        return $this;
    }

    /**
     * Get lastUpdate.
     *
     * @return \DateTime|null
     */
    public function getLastUpdate()
    {
        return $this->lastUpdate;
    }

    /**
     * Add corporation.
     *
     * @param Corporation $corporation
     *
     * @return Alliance
     */
    public function addCorporation(Corporation $corporation)
    {
        $this->corporations[] = $corporation;

        return $this;
    }

    /**
     * Remove corporation.
     *
     * @param Corporation $corporation
     *
     * @return boolean TRUE if this collection contained the specified element, FALSE otherwise.
     */
    public function removeCorporation(Corporation $corporation)
    {
        return $this->corporations->removeElement($corporation);
    }

    /**
     * Get corporations.
     *
     * @return Corporation[]
     */
    public function getCorporations()
    {
        return $this->corporations->toArray();
    }

    /**
     * Add group.
     *
     * @param Group $group
     *
     * @return Alliance
     */
    public function addGroup(Group $group)
    {
        $this->groups[] = $group;

        return $this;
    }

    /**
     * Remove group.
     *
     * @param Group $group
     *
     * @return boolean TRUE if this collection contained the specified element, FALSE otherwise.
     */
    public function removeGroup(Group $group)
    {
        return $this->groups->removeElement($group);
    }

    /**
     * Get groups.
     *
     * @return Group[]
     */
    public function getGroups()
    {
        return $this->groups->toArray();
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
