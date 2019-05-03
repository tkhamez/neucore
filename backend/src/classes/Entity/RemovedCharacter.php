<?php declare(strict_types=1);

namespace Neucore\Entity;

use Neucore\Api;
use Swagger\Annotations as SWG;
use Doctrine\ORM\Mapping as ORM;

/**
 * @SWG\Definition(
 *     definition="RemovedCharacter",
 *     required={"characterId", "characterName", "removedDate", "reason"},
 *     @SWG\Property(property="newPlayerId", type="integer"),
 *     @SWG\Property(property="newPlayerName", type="string")
 * )
 * @ORM\Entity
 * @ORM\Table(name="removed_characters")
 */
class RemovedCharacter implements \JsonSerializable
{
    /**
     * Character was moved to another player account.
     */
    const REASON_MOVED = 'moved';

    /**
     * User has deleted the character from their player account..
     */
    const REASON_DELETED_MANUALLY = 'deleted-manually';

    /**
     * EVE character was deleted/biomassed.
     */
    const REASON_DELETED_BIOMASSED = 'deleted-biomassed';

    /**
     * Character was moved to another EVE account (owner hash changed).
     */
    const REASON_DELETED_OWNER_CHANGED = 'deleted-owner-changed';

    /**
     * Character was deleted by an admin, this does not create a RemovedCharacter database entry.
     */
    const REASON_DELETED_BY_ADMIN = 'deleted-by-admin';

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
     * @ORM\ManyToOne(targetEntity="Player", inversedBy="removedCharacters")
     * @ORM\JoinColumn(nullable=false)
     * @var Player
     */
    private $player;

    /**
     * The new player account.
     *
     * @ORM\ManyToOne(targetEntity="Player")
     * @ORM\JoinColumn(name="new_player_id")
     * @var Player|null
     */
    private $newPlayer;

    /**
     * EVE character ID.
     *
     * @SWG\Property(format="int64")
     * @ORM\Column(type="bigint", name="character_id")
     * @var integer
     */
    private $characterId;

    /**
     * EVE character name.
     *
     * @SWG\Property()
     * @ORM\Column(type="string", name="character_name", length=255)
     * @var string
     */
    private $characterName;

    /**
     * Date of removal.
     *
     * @SWG\Property()
     * @ORM\Column(type="datetime", name="removed_date")
     * @var \DateTime
     */
    private $removedDate;

    /**
     * How it was removed (deleted or moved to another account).
     *
     * @SWG\Property(enum={"moved", "deleted-manually", "deleted-biomassed", "deleted-owner-changed"})
     * @ORM\Column(type="string", length=32)
     * @var string|null
     */
    private $reason;

    /**
     * Contains only information that is of interest for clients.
     *
     * {@inheritDoc}
     * @see \JsonSerializable::jsonSerialize()
     */
    public function jsonSerialize()
    {
        return [
            'characterId' => $this->getCharacterId(),
            'characterName' => $this->characterName,
            'removedDate' => $this->getRemovedDate() !== null ?
                $this->getRemovedDate()->format(Api::DATE_FORMAT) : null,
            'reason' => $this->reason,

            // The JS client has problems if the newPLayer (type Player) property is added here
            'newPlayerId' => $this->newPlayer ? $this->newPlayer->getId() : null,
            'newPlayerName' => $this->newPlayer ? $this->newPlayer->getName() : null,
        ];
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setCharacterId(int $characterId): self
    {
        $this->characterId = $characterId;

        return $this;
    }

    public function getCharacterId(): ?int
    {
        // cast to int because Doctrine creates string for type bigint
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

    public function setReason(?string $reason): self
    {
        $this->reason = $reason;

        return $this;
    }

    public function getReason(): string
    {
        return (string) $this->reason;
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
}
