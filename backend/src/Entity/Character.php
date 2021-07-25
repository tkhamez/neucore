<?php

declare(strict_types=1);

namespace Neucore\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Neucore\Api;
use Neucore\Plugin\CoreCharacter;
use OpenApi\Annotations as OA;

/**
 * An EVE character.
 *
 * @OA\Schema(
 *     required={"id", "name"}
 * )
 * @ORM\Entity
 * @ORM\Table(name="characters")
 */
class Character implements \JsonSerializable
{
    /**
     * EVE character ID.
     *
     * @OA\Property(format="int64")
     * @ORM\Id
     * @ORM\Column(type="bigint")
     * @ORM\GeneratedValue(strategy="NONE")
     * @var integer
     */
    private $id;

    /**
     * EVE character name.
     *
     * @OA\Property()
     * @ORM\Column(type="string", length=255)
     * @var string
     */
    private $name = '';

    /**
     * @OA\Property()
     * @ORM\Column(type="boolean")
     * @var bool
     */
    private $main = false;

    /**
     * @ORM\Column(type="text", length=65535, name="character_owner_hash", nullable=true)
     * @var string|null
     */
    private $characterOwnerHash;

    /**
     * @ORM\OneToMany(targetEntity="EsiToken", mappedBy="character")
     * @ORM\OrderBy({"id" = "ASC"})
     * @var Collection
     */
    private $esiTokens;

    /**
     * Shows if character's default refresh token is valid or not.
     *
     * This is null if there is no refresh token (EVE SSOv1 only)
     * or a valid token but without scopes (SSOv2).
     *
     * @OA\Property(type="boolean", nullable=true)
     * @ORM\Column(type="boolean", name="valid_token", nullable=true)
     * @var bool|null
     */
    private $validToken;

    /**
     * Date and time when that valid token property was last changed.
     *
     * @OA\Property(nullable=true)
     * @ORM\Column(type="datetime", name="valid_token_time", nullable=true)
     * @var \DateTime|null
     */
    private $validTokenTime;

    /**
     * @OA\Property(nullable=true)
     * @ORM\Column(type="datetime", name="created", nullable=true)
     * @var \DateTime|null
     */
    private $created;

    /**
     * @ORM\Column(type="datetime", name="last_login", nullable=true)
     * @var \DateTime|null
     */
    private $lastLogin;

    /**
     * Last ESI update.
     *
     * @OA\Property(nullable=true)
     * @ORM\Column(type="datetime", name="last_update", nullable=true)
     * @var \DateTime
     */
    private $lastUpdate;

    /**
     * @ORM\ManyToOne(targetEntity="Player", inversedBy="characters")
     * @ORM\JoinColumn(nullable=false)
     * @var Player
     */
    private $player;

    /**
     * @OA\Property(ref="#/components/schemas/Corporation", nullable=true)
     * @ORM\ManyToOne(targetEntity="Corporation", inversedBy="characters")
     * @var Corporation|null
     */
    private $corporation;

    /**
     * List of previous character names (API: not included by default).
     *
     * @OA\Property(type="array", @OA\Items(ref="#/components/schemas/CharacterNameChange"))
     * @ORM\OneToMany(targetEntity="CharacterNameChange", mappedBy="character")
     * @ORM\OrderBy({"changeDate" = "DESC"})
     * @var Collection
     */
    private $characterNameChanges;

    /**
     * Contains only information that is of interest for clients.
     *
     * {@inheritDoc}
     * @see \JsonSerializable::jsonSerialize()
     */
    public function jsonSerialize(
        bool $minimum = false,
        bool $withRelations = true,
        bool $withNameChanges = false
    ): array {
        if ($minimum) {
            return [
                'id' => $this->getId(),
                'name' => $this->name,
            ];
        }

        $result = [
            'id' => $this->getId(),
            'name' => $this->name,
            'main' => $this->main,
            'created' => $this->created ? $this->created->format(Api::DATE_FORMAT) : null,
            'lastUpdate' => $this->getLastUpdate() !== null ? $this->getLastUpdate()->format(Api::DATE_FORMAT) : null,
            'validToken' => $this->validToken,
            'validTokenTime' => $this->getValidTokenTime() !== null ?
                $this->getValidTokenTime()->format(Api::DATE_FORMAT) : null,
        ];
        if ($withRelations) {
            $result['corporation'] = $this->corporation;
        }
        if ($withNameChanges) {
            $result['characterNameChanges'] = $this->getCharacterNameChanges();
        }

        return $result;
    }

    public function __construct()
    {
        $this->characterNameChanges = new ArrayCollection();
        $this->esiTokens = new ArrayCollection();
    }

    public function setId(int $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getId(): int
    {
        // cast to int because Doctrine creates string for type bigint, also make sure it's no null
        /** @noinspection PhpCastIsUnnecessaryInspection */
        return (int) $this->id;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setMain(bool $main): self
    {
        $this->main = $main;

        return $this;
    }

    public function getMain(): bool
    {
        return $this->main;
    }

    public function setCharacterOwnerHash(string $characterOwnerHash = null): self
    {
        $this->characterOwnerHash = $characterOwnerHash;

        return $this;
    }

    public function getCharacterOwnerHash(): ?string
    {
        return $this->characterOwnerHash;
    }

    public function addEsiToken(EsiToken $token): self
    {
        $this->esiTokens[] = $token;
        return $this;
    }

    public function removeEsiToken(EsiToken $token): bool
    {
        return $this->esiTokens->removeElement($token);
    }

    /**
     * @return EsiToken[]
     */
    public function getEsiTokens(): array
    {
        return $this->esiTokens->toArray();
    }

    public function getEsiToken(string $eveLoginName): ?EsiToken
    {
        foreach ($this->getEsiTokens() as $esiToken) {
            if ($esiToken->getEveLogin() !== null && $esiToken->getEveLogin()->getName() === $eveLoginName) {
                return $esiToken;
            }
        }
        return null;
    }

    /**
     * Set validToken and updates validTokenTime.
     *
     * @param bool|null $validToken
     * @return Character
     */
    public function setValidToken(bool $validToken = null): self
    {
        if ($this->validToken !== $validToken) {
            try {
                $this->validTokenTime = new \DateTime();
            } catch (\Exception $e) {
                // ignore
            }
        }

        $this->validToken = $validToken;

        return $this;
    }

    public function getValidToken(): ?bool
    {
        return $this->validToken;
    }

    public function setValidTokenTime(\DateTime $validTokenTime): self
    {
        $this->validTokenTime = clone $validTokenTime;

        return $this;
    }

    public function getValidTokenTime(): ?\DateTime
    {
        return $this->validTokenTime;
    }

    public function setCreated(\DateTime $created): self
    {
        $this->created = clone $created;

        return $this;
    }

    public function getCreated(): ?\DateTime
    {
        return $this->created;
    }

    public function setLastLogin(\DateTime $lastLogin): self
    {
        $this->lastLogin = clone $lastLogin;

        return $this;
    }

    public function getLastLogin(): ?\DateTime
    {
        return $this->lastLogin;
    }

    public function setLastUpdate(\DateTime $lastUpdate): self
    {
        $this->lastUpdate = clone $lastUpdate;

        return $this;
    }

    public function getLastUpdate(): ?\DateTime
    {
        return $this->lastUpdate;
    }

    public function setPlayer(Player $player): self
    {
        $this->player = $player;

        return $this;
    }

    /**
     * Get player.
     *
     * A character always belongs to a player.
     */
    public function getPlayer(): Player
    {
        return $this->player;
    }

    public function setCorporation(Corporation $corporation = null): Character
    {
        $this->corporation = $corporation;

        return $this;
    }

    public function getCorporation(): ?Corporation
    {
        return $this->corporation;
    }

    public function addCharacterNameChange(CharacterNameChange $characterNameChange): self
    {
        $this->characterNameChanges[] = $characterNameChange;
        return $this;
    }

    public function removeCharacterNameChange(CharacterNameChange $characterNameChange): bool
    {
        return $this->characterNameChanges->removeElement($characterNameChange);
    }

    /**
     * @return CharacterNameChange[]
     */
    public function getCharacterNameChanges(): array
    {
        return $this->characterNameChanges->toArray();
    }

    public function toCoreCharacter(): CoreCharacter
    {
        $alliance = $this->getCorporation() !== null && $this->getCorporation()->getAlliance() !== null ?
            $this->getCorporation()->getAlliance() :
            null;
        return new CoreCharacter(
            $this->getId(),
            $this->getMain(),
            $this->getName() !== '' ? $this->getName() : null,
            $this->getCharacterOwnerHash(),
            $this->getCorporation() !== null ? $this->getCorporation()->getId() : null,
            $this->getCorporation() !== null ? $this->getCorporation()->getName() : null,
            $this->getCorporation() !== null ? $this->getCorporation()->getTicker() : null,
            $alliance !== null ? $alliance->getId() : null,
            $alliance !== null ? $alliance->getName() : null,
            $alliance !== null ? $alliance->getTicker() : null
        );
    }
}
