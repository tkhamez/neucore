<?php

/** @noinspection PhpPropertyOnlyWrittenInspection */

declare(strict_types=1);

namespace Neucore\Entity;

use Neucore\Api;
/* @phan-suppress-next-line PhanUnreferencedUseNormal */
use Doctrine\ORM\Mapping as ORM;
/* @phan-suppress-next-line PhanUnreferencedUseNormal */
use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *     required={"characterId", "characterName", "removedDate", "reason"},
 *     @OA\Property(property="newPlayerId", type="integer"),
 *     @OA\Property(property="newPlayerName", type="string")
 * )
 * @ORM\Entity
 * @ORM\Table(name="removed_characters")
 */
class RemovedCharacter implements \JsonSerializable
{
    /**
     * Character was moved to another player account.
     */
    public const REASON_MOVED = 'moved';

    /**
     * Character was moved to another player account because the character owner hash changed.
     */
    public const REASON_MOVED_OWNER_CHANGED = 'moved-owner-changed';

    /**
     * User has deleted the character from their player account..
     */
    public const REASON_DELETED_MANUALLY = 'deleted-manually';

    /**
     * EVE character was deleted/biomassed.
     */
    public const REASON_DELETED_BIOMASSED = 'deleted-biomassed';

    /**
     * Character was moved to another EVE account (owner hash changed).
     */
    public const REASON_DELETED_OWNER_CHANGED = 'deleted-owner-changed';

    /**
     * Character was deleted by an admin, this does not create a RemovedCharacter database entry.
     */
    public const REASON_DELETED_BY_ADMIN = 'deleted-by-admin';

    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue
     * @var integer
     */
    private $id;

    /**
     * The old player account.
     *
     * @OA\Property(ref="#/components/schemas/Player", description="The old player account.")
     * @ORM\ManyToOne(targetEntity="Player", inversedBy="removedCharacters")
     * @ORM\JoinColumn(nullable=false)
     * @var Player
     */
    private $player;

    /**
     * The new player account.
     *
     * @ORM\ManyToOne(targetEntity="Player", inversedBy="incomingCharacters")
     * @ORM\JoinColumn(name="new_player_id")
     * @var Player|null
     */
    private $newPlayer;

    /**
     * EVE character ID.
     *
     * @OA\Property(format="int64")
     * @ORM\Column(type="bigint", name="character_id")
     * @var integer
     */
    private $characterId;

    /**
     * EVE character name.
     *
     * @OA\Property()
     * @ORM\Column(type="string", name="character_name", length=255)
     * @var string
     */
    private $characterName;

    /**
     * Date of removal.
     *
     * @OA\Property()
     * @ORM\Column(type="datetime", name="removed_date")
     * @var \DateTime
     */
    private $removedDate;

    /**
     * How it was removed (deleted or moved to another account).
     *
     * @OA\Property(enum={"moved", "moved-owner-changed", "deleted-manually", "deleted-biomassed",
                          "deleted-owner-changed"})
     * @ORM\Column(type="string", length=32)
     * @var string
     */
    private $reason;

    /**
     * The player who deleted the character (only set if it was deleted via the API).
     *
     * @OA\Property(ref="#/components/schemas/Player", nullable=true)
     * @ORM\ManyToOne(targetEntity="Player")
     * @ORM\JoinColumn(name="deleted_by")
     * @var Player|null
     */
    private $deletedBy;

    /**
     * Contains only information that is of interest for clients.
     *
     * {@inheritDoc}
     * @see \JsonSerializable::jsonSerialize()
     */
    public function jsonSerialize(): array
    {
        return [
            'player' => $this->player->jsonSerialize(true),
            'characterId' => $this->getCharacterId(),
            'characterName' => $this->characterName,
            'removedDate' => $this->getRemovedDate() !== null ?
                $this->getRemovedDate()->format(Api::DATE_FORMAT) : null,
            'reason' => $this->reason,
            'deletedBy' => $this->deletedBy ? $this->deletedBy->jsonSerialize(true) : null,

            // The JS client used to have problems if the newPLayer (type Player) property was added here
            // (keep it for backwards compatibility)
            'newPlayerId' => $this->newPlayer ? $this->newPlayer->getId() : null,
            'newPlayerName' => $this->newPlayer ? $this->newPlayer->getName() : null,
        ];
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setPlayer(Player $player): self
    {
        $this->player = $player;

        return $this;
    }

    public function getPlayer(): ?Player
    {
        return $this->player;
    }

    public function setNewPlayer(Player $newPlayer = null): self
    {
        $this->newPlayer = $newPlayer;

        return $this;
    }

    public function getNewPlayer(): ?Player
    {
        return $this->newPlayer;
    }

    public function setCharacterId(int $characterId): self
    {
        $this->characterId = $characterId;

        return $this;
    }

    public function getCharacterId(): ?int
    {
        // cast to int because Doctrine creates string for type bigint
        /** @noinspection PhpCastIsUnnecessaryInspection */
        return $this->characterId !== null ? (int) $this->characterId : null;
    }

    public function setCharacterName(string $characterName): self
    {
        $this->characterName = $characterName;

        return $this;
    }

    public function getCharacterName(): ?string
    {
        return $this->characterName;
    }

    public function setRemovedDate(\DateTime $removedDate): self
    {
        $this->removedDate = clone $removedDate;

        return $this;
    }

    public function getRemovedDate(): ?\DateTime
    {
        return $this->removedDate;
    }

    public function setReason(string $reason): self
    {
        $this->reason = $reason;

        return $this;
    }

    public function getReason(): string
    {
        return (string) $this->reason;
    }

    public function setDeletedBy(?Player $deletedBy): self
    {
        $this->deletedBy = $deletedBy;

        return $this;
    }

    public function getDeletedBy(): ?Player
    {
        return $this->deletedBy;
    }
}
