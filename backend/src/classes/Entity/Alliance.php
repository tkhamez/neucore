<?php declare(strict_types=1);

namespace Brave\Core\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Swagger\Annotations as SWG;
use Doctrine\ORM\Mapping as ORM;

/**
 * EVE Alliance.
 *
 * @SWG\Definition(
 *     definition="Alliance",
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
     * @SWG\Property(format="int64")
     * @ORM\ID
     * @ORM\Column(type="bigint")
     * @ORM\GeneratedValue(strategy="NONE")
     * @var integer
     */
    private $id;

    /**
     * EVE alliance name.
     *
     * @SWG\Property()
     * @ORM\Column(type="string", length=255, nullable=true)
     * @var string
     */
    private $name;

    /**
     * Alliance ticker.
     *
     * @SWG\Property()
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
     * @SWG\Property(type="array", @SWG\Items(ref="#/definitions/Group"))
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
    public function jsonSerialize()
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
    public function getId(): ?int
    {
        // cast to int because Doctrine creates string for type bigint
        return $this->id !== null ? (int) $this->id : null;
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
