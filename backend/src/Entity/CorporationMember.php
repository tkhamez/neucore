<?php

declare(strict_types=1);

namespace Neucore\Entity;

use Neucore\Api;
use Neucore\Plugin\Data\CoreCharacter;
use Neucore\Plugin\Data\CoreEsiToken;
use Neucore\Plugin\Data\CoreMemberTracking;
use Doctrine\ORM\Mapping as ORM;
/* @phan-suppress-next-line PhanUnreferencedUseNormal */
use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *     required={"id", "name"},
 *     description="The player property contains only id and name, character does not contain corporation.",
 *     @OA\Property(property="player", ref="#/components/schemas/Player", nullable=false)
 * )
 */
#[ORM\Entity]
#[ORM\Table(
    name: "corporation_members",
    options: ["charset" => "utf8mb4", "collate" => "utf8mb4_unicode_520_ci"])
]
class CorporationMember implements \JsonSerializable
{
    /**
     * EVE Character ID.
     *
     * @OA\Property(format="int64")
     */
    #[ORM\Id]
    #[ORM\Column(type: "bigint")]
    #[ORM\GeneratedValue(strategy: "NONE")]
    private ?int $id = null;

    /**
     * EVE Character name.
     *
     * @OA\Property(type="string", nullable=true)
     */
    #[ORM\Column(type: "string", length: 255, nullable: true)]
    private ?string $name = null;

    /**
     * @OA\Property(ref="#/components/schemas/EsiLocation", nullable=false)
     */
    #[ORM\ManyToOne(targetEntity: EsiLocation::class)]
    #[ORM\JoinColumn(name: "esi_location_id")]
    private ?EsiLocation $location = null;

    /**
     * @OA\Property(nullable=true)
     */
    #[ORM\Column(name: "logoff_date", type: "datetime", nullable: true)]
    private ?\DateTime $logoffDate = null;

    /**
     * @OA\Property(nullable=true)
     */
    #[ORM\Column(name: "logon_date", type: "datetime", nullable: true)]
    private ?\DateTime $logonDate = null;

    /**
     * @OA\Property(ref="#/components/schemas/EsiType", nullable=false)
     */
    #[ORM\ManyToOne(targetEntity: EsiType::class)]
    #[ORM\JoinColumn(name: "esi_type_id")]
    private ?EsiType $shipType = null;

    /**
     * @OA\Property(nullable=true)
     */
    #[ORM\Column(name: "start_date", type: "datetime", nullable: true)]
    private ?\DateTime $startDate = null;

    #[ORM\ManyToOne(targetEntity: Corporation::class, inversedBy: "members")]
    #[ORM\JoinColumn(nullable: false)]
    private Corporation $corporation;

    /**
     * @OA\Property(ref="#/components/schemas/Character", nullable=false)
     */
    private ?Character $character = null;
    // *not* mapped to Character entity, this relation is via primary key, although not defined for Doctrine

    /**
     * Date and time of the last sent mail.
     *
     * @OA\Property(nullable=true)
     */
    #[ORM\Column(name: "missing_character_mail_sent_date", type: "datetime", nullable: true)]
    private ?\DateTime $missingCharacterMailSentDate = null;

    /**
     * Result of the last sent mail (OK, Blocked, CSPA charge > 0)
     *
     * @OA\Property(nullable=true)
     */
    #[ORM\Column(name: "missing_character_mail_sent_result", type: "string", length: 255, nullable: true)]
    private ?string $missingCharacterMailSentResult = null;

    /**
     * Number of mails sent, is reset when the character is added.
     *
     * @OA\Property()
     */
    #[ORM\Column(name: "missing_character_mail_sent_number", type: "integer")]
    private int $missingCharacterMailSentNumber = 0;

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
            'logoffDate' => $this->getLogoffDate()?->format(Api::DATE_FORMAT),
            'logonDate' => $this->getLogonDate()?->format(Api::DATE_FORMAT),
            'shipType' => $this->shipType,
            'startDate' => $this->getStartDate()?->format(Api::DATE_FORMAT),
            'missingCharacterMailSentDate' => $this->getMissingCharacterMailSentDate()?->format(Api::DATE_FORMAT),
            'missingCharacterMailSentResult' => $this->missingCharacterMailSentResult,
            'missingCharacterMailSentNumber' => $this->missingCharacterMailSentNumber,
        ];

        if ($forUser) {
            $char = $this->getCharacter();
            $result = array_merge($result, [
                'character' => $char?->jsonSerialize(false, false),
                'player' => $char?->getPlayer()->jsonSerialize(true),
            ]);
        }

        return $result;
    }

    public function setId(int $id): static
    {
        $this->id = $id;

        return $this;
    }

    public function getId(): int
    {
        // cast to int because Doctrine creates string for type bigint, also make sure it's no null
        return (int) $this->id;
    }

    public function setName(?string $name = null): static
    {
        $this->name = $name;

        return $this;
    }

    public function getName(): ?string
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

    public function setLogoffDate(\DateTime $logoffDate): static
    {
        $this->logoffDate = clone $logoffDate;

        return $this;
    }

    public function getLogoffDate(): ?\DateTime
    {
        return $this->logoffDate;
    }

    public function setLogonDate(\DateTime $logonDate): static
    {
        $this->logonDate = clone $logonDate;

        return $this;
    }

    public function getLogonDate(): ?\DateTime
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

    public function setStartDate(\DateTime $startDate): static
    {
        $this->startDate = clone $startDate;

        return $this;
    }

    public function getStartDate(): ?\DateTime
    {
        return $this->startDate;
    }

    public function setCorporation(Corporation $corporation): static
    {
        $this->corporation = $corporation;

        return $this;
    }

    public function getCorporation(): Corporation
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

    public function toCoreMemberTracking(): ?CoreMemberTracking
    {
        if (
            !$this->character ||
            !$this->logonDate ||
            !$this->logoffDate ||
            !$this->location ||
            !$this->shipType ||
            !$this->startDate
        ) {
            return null;
        }

        $coreToken = null;
        if ($esiToken = $this->character->getEsiToken(EveLogin::NAME_DEFAULT)) {
            $coreToken = new CoreEsiToken(
                valid: $esiToken->getValidToken(),
                validStatusChanged: $esiToken->getValidTokenTime(),
                lastChecked: $esiToken->getLastChecked(),
            );
        }

        return new CoreMemberTracking(
            new CoreCharacter(
                $this->character->getId(),
                $this->character->getPlayer()->getId(),
                $this->character->getMain(),
                $this->character->getName(),
                $this->character->getPlayer()->getName(),
            ),
            $coreToken,
            $this->logonDate,
            $this->logoffDate,
            (int)$this->location->getId(),
            $this->location->getName(),
            $this->location->getCategory(),
            (int)$this->shipType->getId(),
            (string)$this->shipType->getName(),
            $this->startDate,
        );
    }
}
