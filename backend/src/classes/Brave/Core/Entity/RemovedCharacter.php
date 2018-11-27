<?php declare(strict_types=1);

namespace Brave\Core\Entity;

/**
 * @SWG\Definition(
 *     definition="RemovedCharacter",
 *     required={"characterId", "characterName", "removedDate", "action"},
 *     @SWG\Property(property="newPlayerId", type="integer"),
 *     @SWG\Property(property="newPlayerName", type="string")
 * )
 * @Entity
 * @Table(name="removed_characters")
 */
class RemovedCharacter implements \JsonSerializable
{
    /**
     * @Id
     * @Column(type="integer")
     * @GeneratedValue
     * @var integer
     */
    private $id;

    /**
     * The old player account.
     *
     * @ManyToOne(targetEntity="Player", inversedBy="removedCharacters")
     * @var Player
     */
    private $player;

    /**
     * The new player account.
     *
     * @ManyToOne(targetEntity="Player")
     * @JoinColumn(name="new_player_id")
     * @var Player
     */
    private $newPlayer;

    /**
     * EVE character ID.
     *
     * @SWG\Property(format="int64")
     * @Column(type="bigint", name="character_id")
     * @var integer
     */
    private $characterId;

    /**
     * EVE character name.
     *
     * @SWG\Property()
     * @Column(type="string", name="character_name", length=255)
     * @var string
     */
    private $characterName;

    /**
     * Date of removal.
     *
     * @SWG\Property()
     * @Column(type="datetime", name="removed_date")
     * @var \DateTime
     */
    private $removedDate;

    /**
     * How it was removed (deleted or moved to another account).
     *
     * @SWG\Property()
     * @Column(type="string", length=255)
     * @var string
     */
    private $action;

    /**
     * Contains only information that is of interest for clients.
     *
     * {@inheritDoc}
     * @see \JsonSerializable::jsonSerialize()
     */
    public function jsonSerialize()
    {
        return [
            'characterId' => $this->characterId,
            'characterName' => $this->characterName,
            'removedDate' => $this->removedDate ? $this->removedDate->format('Y-m-d\TH:i:s\Z') : null,
            'action' => $this->action,
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

    public function setAction(string $action): self
    {
        $this->action = $action;

        return $this;
    }

    public function getAction(): string
    {
        return (string) $this->action;
    }

    public function setPlayer(Player $player = null): self
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
