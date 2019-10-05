<?php declare(strict_types=1);

namespace Neucore\Entity;

use Neucore\Api;
use Doctrine\ORM\Mapping as ORM;
use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *     required={"id", "name"},
 *     description="The player property contains only id and name, character does not contain corporation.",
 *     @OA\Property(property="player", ref="#/components/schemas/Player", nullable=true)
 * )
 * @ORM\Entity
 * @ORM\Table(name="corporation_members")
 */
class CorporationMember implements \JsonSerializable
{
    /**
     * EVE Character ID.
     *
     * @OA\Property(format="int64")
     * @ORM\Id
     * @ORM\Column(type="bigint")
     * @ORM\GeneratedValue(strategy="NONE")
     * @var integer
     */
    private $id;

    /**
     * EVE Character name.
     *
     * @OA\Property(type="string", nullable=true)
     * @ORM\Column(type="string", length=255, nullable=true)
     * @var string|null
     */
    private $name;

    /**
     * @OA\Property(ref="#/components/schemas/EsiLocation", nullable=true)
     * @ORM\ManyToOne(targetEntity="EsiLocation")
     * @ORM\JoinColumn(name="esi_location_id")
     * @var EsiLocation|null
     */
    private $location;

    /**
     * @OA\Property(nullable=true)
     * @ORM\Column(type="datetime", name="logoff_date", nullable=true)
     * @var \DateTime
     */
    private $logoffDate;

    /**
     * @OA\Property(nullable=true)
     * @ORM\Column(type="datetime", name="logon_date", nullable=true)
     * @var \DateTime
     */
    private $logonDate;

    /**
     * @OA\Property(ref="#/components/schemas/EsiType", nullable=true)
     * @ORM\ManyToOne(targetEntity="EsiType")
     * @ORM\JoinColumn(name="esi_type_id")
     * @var EsiType|null
     */
    private $shipType;

    /**
     * @OA\Property(nullable=true)
     * @ORM\Column(type="datetime", name="start_date", nullable=true)
     * @var \DateTime
     */
    private $startDate;

    /**
     * @ORM\ManyToOne(targetEntity="Corporation", inversedBy="members")
     * @ORM\JoinColumn(nullable=false)
     * @var Corporation
     */
    private $corporation;

    /**
     * @OA\Property(ref="#/components/schemas/Character", nullable=true)
     * @ORM\OneToOne(targetEntity="Character", inversedBy="corporationMember")
     * @var Character|null
     */
    private $character;

    /**
     * {@inheritDoc}
     * @see \JsonSerializable::jsonSerialize()
     */
    public function jsonSerialize($forUser = true)
    {
        $result = [
            'id' => $this->getId(),
            'name' => $this->name,
            'location' => $this->location,
            'logoffDate' => $this->getLogoffDate() !== null ? $this->getLogoffDate()->format(Api::DATE_FORMAT) : null,
            'logonDate' => $this->getLogonDate() !== null ? $this->getLogonDate()->format(Api::DATE_FORMAT) : null,
            'shipType' => $this->shipType,
            'startDate' => $this->getStartDate() !== null ? $this->getStartDate()->format(Api::DATE_FORMAT) : null,
        ];

        if ($forUser) {
            $result = array_merge($result, [
                'character' => $this->getCharacter() !== null ? $this->getCharacter()->jsonSerialize(false) : null,
                'player' => $this->getCharacter() !== null ?
                    $this->getCharacter()->getPlayer()->jsonSerialize(true) : null,
            ]);
        }

        return $result;
    }

    /**
     * Set id.
     *
     * @param int $id
     *
     * @return CorporationMember
     */
    public function setId($id)
    {
        $this->id = (int) $id;

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
     * @param string|null $name
     *
     * @return CorporationMember
     */
    public function setName($name = null)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get name.
     *
     * @return string|null
     */
    public function getName()
    {
        return $this->name;
    }

    public function setLocation(?EsiLocation $location): self
    {
        $this->location = $location;

        return $this;
    }

    public function getLocation(): ?EsiLocation
    {
        return $this->location;
    }

    /**
     * Set logoffDate.
     *
     * @param \DateTime $logoffDate
     *
     * @return CorporationMember
     */
    public function setLogoffDate($logoffDate)
    {
        $this->logoffDate = clone $logoffDate;

        return $this;
    }

    /**
     * Get logoffDate.
     *
     * @return \DateTime|null
     */
    public function getLogoffDate()
    {
        return $this->logoffDate;
    }

    /**
     * Set logonDate.
     *
     * @param \DateTime $logonDate
     *
     * @return CorporationMember
     */
    public function setLogonDate($logonDate)
    {
        $this->logonDate = clone $logonDate;

        return $this;
    }

    /**
     * Get logonDate.
     *
     * @return \DateTime|null
     */
    public function getLogonDate()
    {
        return $this->logonDate;
    }

    public function setShipType(EsiType $shipType = null): CorporationMember
    {
        $this->shipType = $shipType;

        return $this;
    }

    public function getShipType(): ?EsiType
    {
        return $this->shipType;
    }

    /**
     * Set startDate.
     *
     * @param \DateTime $startDate
     *
     * @return CorporationMember
     */
    public function setStartDate($startDate)
    {
        $this->startDate = clone $startDate;

        return $this;
    }

    /**
     * Get startDate.
     *
     * @return \DateTime|null
     */
    public function getStartDate()
    {
        return $this->startDate;
    }

    /**
     * Set corporation.
     *
     * @param Corporation $corporation
     *
     * @return CorporationMember
     */
    public function setCorporation(Corporation $corporation)
    {
        $this->corporation = $corporation;

        return $this;
    }

    /**
     * Get corporation.
     *
     * @return Corporation
     */
    public function getCorporation()
    {
        return $this->corporation;
    }

    /**
     * Set character.
     *
     * @param Character|null $character
     *
     * @return CorporationMember
     */
    public function setCharacter(Character $character = null)
    {
        $this->character = $character;

        return $this;
    }

    /**
     * Get character.
     *
     * @return Character|null
     */
    public function getCharacter()
    {
        return $this->character;
    }
}
