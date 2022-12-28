<?php

declare(strict_types=1);

namespace Neucore\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
/* @phan-suppress-next-line PhanUnreferencedUseNormal */
use Doctrine\ORM\Mapping as ORM;
use Neucore\Api;
use Neucore\Plugin\CoreCharacter;
/* @phan-suppress-next-line PhanUnreferencedUseNormal */
use OpenApi\Annotations as OA;

/**
 * An EVE character.
 *
 * @OA\Schema(
 *     required={"id", "name"},
 *     @OA\Property(
 *         property="validToken",
 *         type="boolean",
 *         nullable=true,
 *         description="Shows if character's default refresh token is valid or not.
                        This is null if there is no refresh token (EVE SSOv1 only)
                        or a valid token but without scopes (SSOv2)."
 *     ),
 *     @OA\Property(
 *         property="validTokenTime",
 *         type="string",
 *         format="date-time",
 *         nullable=true,
 *         description="Date and time when the valid token property of the default token was last changed."
 *     ),
 *     @OA\Property(
 *         property="tokenLastChecked",
 *         type="string",
 *         format="date-time",
 *         nullable=true,
 *         description="Date and time when the default token was last checked."
 *     )
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
     */
    private ?int $id = null;

    /**
     * EVE character name.
     *
     * @OA\Property()
     * @ORM\Column(type="string", length=255)
     */
    private string $name = '';

    /**
     * @OA\Property()
     * @ORM\Column(type="boolean")
     */
    private bool $main = false;

    /**
     * @ORM\Column(type="text", length=65535, name="character_owner_hash", nullable=true)
     */
    private ?string $characterOwnerHash = null;

    /**
     * ESI tokens of the character (API: not included by default).
     *
     * @OA\Property(type="array", @OA\Items(ref="#/components/schemas/EsiToken"))
     * @ORM\OneToMany(targetEntity="EsiToken", mappedBy="character")
     * @ORM\OrderBy({"id" = "ASC"})
     */
    private Collection $esiTokens;

    /**
     * @OA\Property(nullable=true)
     * @ORM\Column(type="datetime", name="created", nullable=true)
     */
    private ?\DateTime $created = null;

    /**
     * @ORM\Column(type="datetime", name="last_login", nullable=true)
     */
    private ?\DateTime $lastLogin = null;

    /**
     * Last ESI update.
     *
     * @OA\Property(nullable=true)
     * @ORM\Column(type="datetime", name="last_update", nullable=true)
     */
    private ?\DateTime $lastUpdate = null;

    /**
     * @ORM\ManyToOne(targetEntity="Player", inversedBy="characters")
     * @ORM\JoinColumn(nullable=false)
     */
    private Player $player;

    /**
     * @OA\Property(ref="#/components/schemas/Corporation", nullable=true)
     * @ORM\ManyToOne(targetEntity="Corporation", inversedBy="characters")
     */
    private ?Corporation $corporation = null;

    /**
     * List of previous character names (API: not included by default).
     *
     * @OA\Property(type="array", @OA\Items(ref="#/components/schemas/CharacterNameChange"))
     * @ORM\OneToMany(targetEntity="CharacterNameChange", mappedBy="character")
     * @ORM\OrderBy({"changeDate" = "DESC"})
     */
    private Collection $characterNameChanges;

    /**
     * Contains only information that is of interest for clients.
     *
     * {@inheritDoc}
     * @see \JsonSerializable::jsonSerialize()
     */
    public function jsonSerialize(
        bool $minimum = false,
        bool $withCorporation = true,
        bool $withNameChanges = false,
        bool $withEsiTokens = false
    ): array {
        if ($minimum) {
            $result = [
                'id' => $this->getId(),
                'name' => $this->name,
            ];
            if ($withCorporation) {
                $result['corporation'] = $this->corporation;
            }
            return $result;
        }

        $result = [
            'id' => $this->getId(),
            'name' => $this->name,
            'main' => $this->main,
            'created' => $this->created?->format(Api::DATE_FORMAT),
            'lastUpdate' => $this->getLastUpdate()?->format(Api::DATE_FORMAT),
            'validToken' => $this->getDefaultTokenValid(),
            'validTokenTime' => $this->getDefaultTokenValidTime() !== null ?
                $this->getDefaultTokenValidTime()->format(Api::DATE_FORMAT) : null,
            'tokenLastChecked' => $this->getDefaultTokenLastChecked() !== null ?
                $this->getDefaultTokenLastChecked()->format(Api::DATE_FORMAT) : null,
        ];
        if ($withCorporation) {
            $result['corporation'] = $this->corporation;
        }
        if ($withEsiTokens) {
            $result['esiTokens'] = $this->getEsiTokens();
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
        // cast to int because Doctrine creates string for type bigint, also make sure it's not null
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
        return array_values($this->esiTokens->toArray());
    }

    /**
     * @param string $eveLoginName One of the EveLogin::NAME_* constants
     */
    public function getEsiToken(string $eveLoginName): ?EsiToken
    {
        foreach ($this->getEsiTokens() as $esiToken) {
            if ($esiToken->getEveLogin() !== null && $esiToken->getEveLogin()->getName() === $eveLoginName) {
                return $esiToken;
            }
        }
        return null;
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
        return array_values($this->characterNameChanges->toArray());
    }

    public function toCoreCharacter(): CoreCharacter
    {
        $alliance = $this->getCorporation() !== null && $this->getCorporation()->getAlliance() !== null ?
            $this->getCorporation()->getAlliance() :
            null;
        return new CoreCharacter(
            $this->getId(),
            $this->getPlayer()->getId(),
            $this->getMain(),
            $this->getName() !== '' ? $this->getName() : null,
            $this->getCharacterOwnerHash(),
            $this->getCorporation()?->getId(),
            $this->getCorporation()?->getName(),
            $this->getCorporation()?->getTicker(),
            $alliance?->getId(),
            $alliance?->getName(),
            $alliance?->getTicker()
        );
    }

    private function getDefaultTokenValid(): ?bool
    {
        $token = $this->getEsiToken(EveLogin::NAME_DEFAULT);
        return $token?->getValidToken();
    }

    private function getDefaultTokenValidTime(): ?\DateTime
    {
        $token = $this->getEsiToken(EveLogin::NAME_DEFAULT);
        return $token?->getValidTokenTime();
    }

    private function getDefaultTokenLastChecked(): ?\DateTime
    {
        $token = $this->getEsiToken(EveLogin::NAME_DEFAULT);
        return $token?->getLastChecked();
    }
}
