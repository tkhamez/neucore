<?php

declare(strict_types=1);

namespace Neucore\Entity;

use Doctrine\ORM\Mapping as ORM;
use Neucore\Api;
use OpenApi\Attributes as OA;

/**
 * A previous character name.
 */
#[ORM\Entity]
#[ORM\Table(
    name: "character_name_changes",
    options: ["charset" => "utf8mb4", "collate" => "utf8mb4_unicode_520_ci"])
]
#[OA\Schema(required: ['oldName', 'changeDate'])]
class CharacterNameChange implements \JsonSerializable
{
    #[ORM\Id]
    #[ORM\Column(type: "integer")]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Character::class, inversedBy: "characterNameChanges")]
    #[ORM\JoinColumn(nullable: false, onDelete: "CASCADE")]
    private Character $character;

    #[ORM\Column(name: "old_name", type: "string", length: 255)]
    #[OA\Property]
    private string $oldName = '';

    #[ORM\Column(name: "change_date", type: "datetime")]
    #[OA\Property(nullable: true)]
    private ?\DateTime $changeDate = null;

    public function jsonSerialize(): array
    {
        return [
            'oldName' => $this->oldName,
            'changeDate' => $this->changeDate?->format(Api::DATE_FORMAT),
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
