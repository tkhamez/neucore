<?php declare(strict_types=1);

namespace Brave\Core\Entity;

/**
 * EVE Alliance.
 *
 * @SWG\Definition(
 *     definition="Alliance",
 *     required={"id", "name", "ticker"}
 * )
 * @Entity(repositoryClass="Brave\Core\Entity\AllianceRepository")
 * @Table(name="alliances")
 */
class Alliance implements \JsonSerializable
{

    /**
     * EVE alliance ID.
     *
     * @SWG\Property(format="int64")
     * @Id
     * @Column(type="bigint")
     * @NONE
     * @var integer
     */
    private $id;

    /**
     * EVE alliance name.
     *
     * @SWG\Property()
     * @Column(type="string", length=255, nullable=true)
     * @var string
     */
    private $name;

    /**
     * Alliance ticker.
     *
     * @SWG\Property()
     * @Column(type="string", length=16, nullable=true)
     * @var string
     */
    private $ticker;

    /**
     * Last ESI update.
     *
     * @SWG\Property()
     * @Column(type="datetime", name="last_update", nullable=true)
     * @var \DateTime
     */
    private $lastUpdate;

    /**
     *
     * @OneToMany(targetEntity="Corporation", mappedBy="alliance")
     * @OrderBy({"name" = "ASC"})
     * @var \Doctrine\Common\Collections\Collection
     */
    private $corporations;

    /**
     * Groups for automatic assignment (API: not included by default).
     *
     * @SWG\Property(type="array", @SWG\Items(ref="#/definitions/Group"))
     * @ManyToMany(targetEntity="Group", inversedBy="alliances")
     * @JoinTable(name="alliance_group")
     * @OrderBy({"name" = "ASC"})
     * @var \Doctrine\Common\Collections\Collection
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
        $this->groups = new \Doctrine\Common\Collections\ArrayCollection();
        $this->corporations = new \Doctrine\Common\Collections\ArrayCollection();
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
     *
     * @return int
     */
    public function getId()
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
     * @param \DateTime $update
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
     * @param \Brave\Core\Entity\Corporation $corporation
     *
     * @return Alliance
     */
    public function addCorporation(\Brave\Core\Entity\Corporation $corporation)
    {
        $this->corporations[] = $corporation;

        return $this;
    }

    /**
     * Remove corporation.
     *
     * @param \Brave\Core\Entity\Corporation $corporation
     *
     * @return boolean TRUE if this collection contained the specified element, FALSE otherwise.
     */
    public function removeCorporation(\Brave\Core\Entity\Corporation $corporation)
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
     * @param \Brave\Core\Entity\Group $group
     *
     * @return Alliance
     */
    public function addGroup(\Brave\Core\Entity\Group $group)
    {
        $this->groups[] = $group;

        return $this;
    }

    /**
     * Remove group.
     *
     * @param \Brave\Core\Entity\Group $group
     *
     * @return boolean TRUE if this collection contained the specified element, FALSE otherwise.
     */
    public function removeGroup(\Brave\Core\Entity\Group $group)
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
