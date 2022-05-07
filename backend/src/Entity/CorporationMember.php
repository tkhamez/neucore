<?php

declare(strict_types=1);

namespace Neucore\Entity;

use Neucore\Api;
/* @phan-suppress-next-line PhanUnreferencedUseNormal */
use Doctrine\ORM\Mapping as ORM;
/* @phan-suppress-next-line PhanUnreferencedUseNormal */
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
     * @var Character|null
     */
    private $character;
    // *not* mapped to Character entity, this relation is via primary key, although not defined for Doctrine

    /**
     * Date and time of the last sent mail.
     *
     * @OA\Property(nullable=true)
     * @ORM\Column(type="datetime", name="missing_character_mail_sent_date", nullable=true)
     * @var \DateTime|null
     */
    private $missingCharacterMailSentDate;

    /**
     * Result of the last sent mail (OK, Blocked, CSPA charge > 0)
     *
     * @OA\Property(nullable=true)
     * @ORM\Column(type="string", length=255, name="missing_character_mail_sent_result", nullable=true)
     * @var string|null
     */
    private $missingCharacterMailSentResult;

    /**
     * Number of mails sent, is reset when the character is added.
     *
     * @OA\Property()
     * @ORM\Column(type="integer", name="missing_character_mail_sent_number")
     * @var integer
     */
    private $missingCharacterMailSentNumber = 0;

    /**
     * {@inheritDoc}
     * @see \JsonSerializable::jsonSerialize()
     */
    public function jsonSerialize(bool $forUser = true): array
    {
        $result = [
            'id' => $this->getId(),
            'name' => $this->name,
            'location' => $this->location,
            'logoffDate' => $this->getLogoffDate() !== null ? $this->getLogoffDate()->format(Api::DATE_FORMAT) : null,
            'logonDate' => $this->getLogonDate() !== null ? $this->getLogonDate()->format(Api::DATE_FORMAT) : null,
            'shipType' => $this->shipType,
            'startDate' => $this->getStartDate() !== null ? $this->getStartDate()->format(Api::DATE_FORMAT) : null,
            'missingCharacterMailSentDate' => $this->getMissingCharacterMailSentDate() !== null ?
                $this->getMissingCharacterMailSentDate()->format(Api::DATE_FORMAT) : null,
            'missingCharacterMailSentResult' => $this->missingCharacterMailSentResult,
            'missingCharacterMailSentNumber' => $this->missingCharacterMailSentNumber,
        ];

        if ($forUser) {
            $char = $this->getCharacter();
            $result = array_merge($result, [
                'character' => $char !== null ? $char->jsonSerialize(false, false) : null,
                'player' => $char !== null ? $char->getPlayer()->jsonSerialize(true) : null,
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
    public function getId(): int
    {
        // cast to int because Doctrine creates string for type bigint, also make sure it's no null
        return (int) $this->id;
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
     * This is not mapped with Doctrine
     */
    public function setCharacter(?Character $character = null): self
    {
        $this->character = $character;

        return $this;
    }

    /**
     * This is not mapped with Doctrine
     */
    public function getCharacter(): ?Character
    {
        return $this->character;
    }

    public function setMissingCharacterMailSentDate(\DateTime $date): self
    {
        $this->missingCharacterMailSentDate = clone $date;

        return $this;
    }

    public function getMissingCharacterMailSentDate(): ?\DateTime
    {
        return $this->missingCharacterMailSentDate;
    }

    public function setMissingCharacterMailSentResult(?string $result): self
    {
        $this->missingCharacterMailSentResult = $result;

        return $this;
    }

    public function getMissingCharacterMailSentResult(): ?string
    {
        return $this->missingCharacterMailSentResult;
    }

    public function setMissingCharacterMailSentNumber(int $number): self
    {
        $this->missingCharacterMailSentNumber = $number;

        return $this;
    }

    public function getMissingCharacterMailSentNumber(): int
    {
        return $this->missingCharacterMailSentNumber;
    }
}
