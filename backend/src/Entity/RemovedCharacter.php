<?php

/** @noinspection PhpPropertyOnlyWrittenInspection */

declare(strict_types=1);

namespace Neucore\Entity;

use Neucore\Api;
use Neucore\Plugin\Data\CoreCharacter;
use Neucore\Plugin\Data\CoreMovedCharacter;
use Doctrine\ORM\Mapping as ORM;
/* @phan-suppress-next-line PhanUnreferencedUseNormal */
use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *     required={"characterId", "characterName", "removedDate", "reason"},
 *     @OA\Property(property="newPlayerId", type="integer"),
 *     @OA\Property(property="newPlayerName", type="string")
 * )
 */
#[ORM\Entity]
#[ORM\Table(
    name: "removed_characters",
    options: ["charset" => "utf8mb4", "collate" => "utf8mb4_unicode_520_ci"]
)]
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
     * EVE character was deleted/biomassed.
     */
    public const REASON_DELETED_BIOMASSED = 'deleted-biomassed';

    /**
     * Character was moved to another EVE account (owner hash changed).
     */
    public const REASON_DELETED_OWNER_CHANGED = 'deleted-owner-changed';

    /**
     * Deleted by admin because the player lost access to the EVE account.
     */
    public const REASON_DELETED_LOST_ACCESS = 'deleted-lost-access';

    /**
     * User has deleted the character from their player account, or an admin
     * deleted it for a different reason than owner change or lost access.
     */
    public const REASON_DELETED_MANUALLY = 'deleted-manually';

    /**
     * Character was deleted by an admin, this does not create a RemovedCharacter database entry.
     */
    public const REASON_DELETED_BY_ADMIN = 'deleted-by-admin';

    #[ORM\Id]
    #[ORM\Column(type: "integer")]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    /**
     * The old player account.
     *
     * @OA\Property(ref="#/components/schemas/Player", description="The old player account.", nullable=false)
     */
    #[ORM\ManyToOne(targetEntity: Player::class, inversedBy: "removedCharacters")]
    #[ORM\JoinColumn(nullable: false)]
    private ?Player $player = null;

    /**
     * The new player account.
     *
     */
    #[ORM\ManyToOne(targetEntity: Player::class, inversedBy: "incomingCharacters")]
    #[ORM\JoinColumn(name: "new_player_id")]
    private ?Player $newPlayer = null;

    /**
     * EVE character ID.
     *
     * @OA\Property(format="int64")
     */
    #[ORM\Column(name: "character_id", type: "bigint")]
    private ?int $characterId = null;

    /**
     * EVE character name.
     *
     * @OA\Property()
     */
    #[ORM\Column(name: "character_name", type: "string", length: 255)]
    private ?string $characterName = null;

    /**
     * Date of removal.
     *
     * @OA\Property()
     */
    #[ORM\Column(name: "removed_date", type: "datetime")]
    private ?\DateTime $removedDate = null;

    /**
     * How it was removed (deleted or moved to another account).
     *
     * @OA\Property(enum={"moved", "moved-owner-changed", "deleted-biomassed",
                          "deleted-owner-changed", "deleted-lost-access", "deleted-manually"})
     */
    #[ORM\Column(type: "string", length: 32)]
    private ?string $reason = null;

    /**
     * The player who deleted the character (only set if it was deleted via the API).
     *
     * @OA\Property(ref="#/components/schemas/Player", nullable=false)
     */
    #[ORM\ManyToOne(targetEntity: Player::class)]
    #[ORM\JoinColumn(name: "deleted_by")]
    private ?Player $deletedBy = null;

    /**
     * Contains only information that is of interest for clients.
     *
     * {@inheritDoc}
     * @see \JsonSerializable::jsonSerialize()
     */
    public function jsonSerialize(): array
    {
        return [
            'player' => $this->player?->jsonSerialize(true),
            'characterId' => $this->getCharacterId(),
            'characterName' => $this->characterName,
            'removedDate' => $this->getRemovedDate()?->format(Api::DATE_FORMAT),
            'reason' => $this->reason,
            'deletedBy' => $this->deletedBy?->jsonSerialize(true),

            // The JS client used to have problems if the newPLayer (type Player) property was added here
            // (keep it for backwards compatibility)
            'newPlayerId' => $this->newPlayer?->getId(),
            'newPlayerName' => $this->newPlayer?->getName(),
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
        return $this->characterId !== null ? (int)$this->characterId : null;
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

    public function toCoreMovedCharacter(): ?CoreMovedCharacter
    {
        if (
            !$this->player ||
            !$this->characterId ||
            !$this->removedDate ||
            !$this->reason ||
            !($oldPlayer = $this->player->toCoreAccount(false))
        ) {
            return null;
        }

        return new CoreMovedCharacter(
            $oldPlayer,
            $this->newPlayer?->toCoreAccount(false),
            new CoreCharacter(
                id: $this->characterId,
                playerId: 0,
                name: $this->characterName,
            ),
            $this->removedDate,
            $this->reason,
            $this->deletedBy?->toCoreAccount(false),
        );
    }
}
