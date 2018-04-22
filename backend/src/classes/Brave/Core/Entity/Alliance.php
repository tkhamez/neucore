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
     * @Column(type="string", length=255)
     * @var string
     */
    private $name;

    /**
     * Alliance ticker.
     *
     * @SWG\Property()
     * @Column(type="string", length=16)
     * @var string
     */
    private $ticker;

    /**
     *
     * @OneToMany(targetEntity="Corporation", mappedBy="alliance")
     * @OrderBy({"name" = "ASC"})
     * @var \Doctrine\Common\Collections\Collection
     */
    private $corporations;

    /**
     *
     * @OneToMany(targetEntity="Character", mappedBy="alliance")
     * @OrderBy({"name" = "ASC"})
     * @var \Doctrine\Common\Collections\Collection
     */
    private $characters;

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
        ];
    }
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->corporations = new \Doctrine\Common\Collections\ArrayCollection();
        $this->characters = new \Doctrine\Common\Collections\ArrayCollection();
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
     * Add character.
     *
     * @param \Brave\Core\Entity\Character $character
     *
     * @return Alliance
     */
    public function addCharacter(\Brave\Core\Entity\Character $character)
    {
        $this->characters[] = $character;

        return $this;
    }

    /**
     * Remove character.
     *
     * @param \Brave\Core\Entity\Character $character
     *
     * @return boolean TRUE if this collection contained the specified element, FALSE otherwise.
     */
    public function removeCharacter(\Brave\Core\Entity\Character $character)
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
}
