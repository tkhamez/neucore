<?php

declare(strict_types=1);

namespace Neucore\Entity;

use Doctrine\ORM\Mapping as ORM;
use Neucore\Api;
use OpenApi\Annotations as OA;

/**
 * A previous character name.
 *
 * @OA\Schema(
 *     required={"oldName", "changeDate"}
 * )
 * @ORM\Entity
 * @ORM\Table(name="character_name_changes")
 */
class CharacterNameChange implements \JsonSerializable
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue
     * @var integer
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="Character", inversedBy="characterNameChanges")
     * @ORM\JoinColumn(nullable=false)
     * @var Character
     */
    private $character;

    /**
     * @OA\Property()
     * @ORM\Column(type="string", length=255, name="old_name")
     * @var string
     */
    private $oldName = '';

    /**
     * @OA\Property(nullable=true)
     * @ORM\Column(type="datetime", name="change_date")
     * @var \DateTime|null
     */
    private $changeDate;

    public function jsonSerialize(): array
    {
        return [
            'oldName' => $this->oldName,
            'changeDate' => $this->changeDate !== null ? $this->changeDate->format(Api::DATE_FORMAT) : null,
        ];
    }

    public function getId(): int
    {
        return (int) $this->id;
    }

    public function setCharacter(Character $character): self
    {
        $this->character = $character;
        return $this;
    }

    public function getCharacter(): Character
    {
        return $this->character;
    }

    public function setOldName(string $oldName): self
    {
        $this->oldName = $oldName;
        return $this;
    }

    public function getOldName(): string
    {
        return $this->oldName;
    }

    public function setChangeDate(\DateTime $changeDate): self
    {
        $this->changeDate = clone $changeDate;
        return $this;
    }

    public function getChangeDate(): ?\DateTime
    {
        return $this->changeDate;
    }
}
