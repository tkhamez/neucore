<?php declare(strict_types=1);

namespace Neucore\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use OpenApi\Annotations as OA;

/**
 * EVE corporation.
 *
 * @OA\Schema(
 *     required={"id", "name", "ticker"}
 * )
 * @ORM\Entity
 * @ORM\Table(name="corporations")
 */
class Corporation implements \JsonSerializable
{

    /**
     * EVE corporation ID.
     *
     * @OA\Property(format="int64")
     * @ORM\Id
     * @ORM\Column(type="bigint")
     * @ORM\GeneratedValue(strategy="NONE")
     * @var integer
     */
    private $id;

    /**
     * EVE corporation name.
     *
     * @OA\Property(nullable=true)
     * @ORM\Column(type="string", length=255, nullable=true)
     * @var string
     */
    private $name;

    /**
     * Corporation ticker.
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
     * @OA\Property(ref="#/components/schemas/Alliance", nullable=true)
     * @ORM\ManyToOne(targetEntity="Alliance", inversedBy="corporations")
     * @var Alliance|null
     */
    private $alliance;

    /**
     * Groups for automatic assignment (API: not included by default).
     *
     * @OA\Property(type="array", @OA\Items(ref="#/components/schemas/Group"))
     * @ORM\ManyToMany(targetEntity="Group", inversedBy="corporations")
     * @ORM\JoinTable(name="corporation_group")
     * @ORM\OrderBy({"name" = "ASC"})
     * @var Collection
     */
    private $groups;

    /**
     *
     * @ORM\OneToMany(targetEntity="Character", mappedBy="corporation")
     * @ORM\OrderBy({"name" = "ASC"})
     * @var Collection
     */
    private $characters;

    /**
     * @ORM\OneToMany(targetEntity="CorporationMember", mappedBy="corporation")
     * @ORM\OrderBy({"name" = "ASC"})
     * @var Collection
     */
    private $members;

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
            'ticker' => $this->ticker,
            'alliance' => $this->alliance,
            // API: groups are not included by default
        ];
    }

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->groups = new ArrayCollection();
        $this->characters = new ArrayCollection();
        $this->members = new ArrayCollection();
    }

    /**
     * Set id.
     *
     * @param int $id
     *
     * @return Corporation
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
     * @return Corporation
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
     * @return Corporation
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
     * @return Corporation
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
     * Set alliance.
     *
     * @param Alliance|null $alliance
     *
     * @return Corporation
     */
    public function setAlliance(Alliance $alliance = null)
    {
        $this->alliance = $alliance;

        return $this;
    }

    /**
     * Get alliance.
     *
     * @return Alliance|null
     */
    public function getAlliance()
    {
        return $this->alliance;
    }

    /**
     * Add group.
     *
     * @param Group $group
     *
     * @return Corporation
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

    /**
     * Add character.
     *
     * @param Character $character
     *
     * @return Corporation
     */
    public function addCharacter(Character $character)
    {
        $this->characters[] = $character;

        return $this;
    }

    /**
     * Remove character.
     *
     * @param Character $character
     *
     * @return boolean TRUE if this collection contained the specified element, FALSE otherwise.
     */
    public function removeCharacter(Character $character)
    {
        return $this->characters->removeElement($character);
    }

    /**
     * Get characters.
     *
     * @return Character[]
     */
    public function getCharacters()
    {
        return $this->characters->toArray();
    }

    /**
     * Add member.
     *
     * @param CorporationMember $member
     *
     * @return Corporation
     */
    public function addMember(CorporationMember $member)
    {
        $this->members[] = $member;

        return $this;
    }

    /**
     * Remove member.
     *
     * @param CorporationMember $member
     *
     * @return boolean TRUE if this collection contained the specified element, FALSE otherwise.
     */
    public function removeMember(CorporationMember $member)
    {
        return $this->members->removeElement($member);
    }

    /**
     * Get members.
     *
     * @return CorporationMember[]
     */
    public function getMembers()
    {
        return $this->members->toArray();
    }
}
