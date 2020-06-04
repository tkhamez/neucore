<?php

declare(strict_types=1);

namespace Neucore\Entity;

use Brave\Sso\Basics\JsonWebToken;
use Doctrine\ORM\Mapping as ORM;
use League\OAuth2\Client\Token\AccessToken;
use League\OAuth2\Client\Token\AccessTokenInterface;
use Neucore\Api;
use OpenApi\Annotations as OA;

/**
 * EVE character.
 *
 * This is the user that logs in via EVE SSO.
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
     * @ORM\Column(type="text", length=65535, name="access_token", nullable=true)
     * @var string|null
     */
    private $accessToken;

    /**
     * Unix timestamp when access token expires.
     *
     * @ORM\Column(type="integer", nullable=true)
     * @var int|null
     */
    private $expires;

    /**
     * The OAuth refresh token.
     *
     * Null if there was never a token, e. g. EVE SSOv1 without scopes or a char that was added by an admin.
     * Empty string if the token became invalid.
     *
     * @ORM\Column(type="text", length=65535, name="refresh_token", nullable=true)
     * @var string|null
     */
    private $refreshToken;

    /**
     * Shows if character's refresh token is valid or not.
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
     * @ORM\OneToOne(targetEntity="CorporationMember", mappedBy="character")
     * @var CorporationMember|null
     */
    private $corporationMember;

    /**
     * Contains only information that is of interest for clients.
     *
     * {@inheritDoc}
     * @see \JsonSerializable::jsonSerialize()
     */
    public function jsonSerialize(bool $minimum = false, bool $withRelations = true): array
    {
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

        return $result;
    }

    public function setId(int $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getId(): int
    {
        // cast to int because Doctrine creates string for type bigint, also make sure it's no null
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

    public function setAccessToken(string $accessToken = null): self
    {
        $this->accessToken = $accessToken;

        return $this;
    }

    public function getAccessToken(): ?string
    {
        return $this->accessToken;
    }

    public function setExpires(int $expires = null): self
    {
        $this->expires = $expires;

        return $this;
    }

    public function getExpires(): ?int
    {
        return $this->expires;
    }

    public function setRefreshToken(string $refreshToken = null): self
    {
        $this->refreshToken = $refreshToken;

        return $this;
    }

    public function getRefreshToken(): ?string
    {
        return $this->refreshToken;
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

    public function setCorporationMember(CorporationMember $corporationMember = null): self
    {
        $this->corporationMember = $corporationMember;

        return $this;
    }

    public function getCorporationMember(): ?CorporationMember
    {
        return $this->corporationMember;
    }

    public function createAccessToken(): ?AccessTokenInterface
    {
        $token = null;
        try {
            $token = new AccessToken([
                'access_token' => $this->accessToken,
                'refresh_token' => (string) $this->refreshToken,
                'expires' => $this->expires
            ]);
        } catch (\Exception $e) {
            // characters without an "access_token" are okay.
        }

        return $token;
    }

    public function getScopesFromToken(): array
    {
        $token = $this->createAccessToken();
        if ($token === null) {
            return [];
        }
        try {
            $jwt = new JsonWebToken($token);
        } catch (\UnexpectedValueException $e) {
            return [];
        }

        return $jwt->getEveAuthentication()->getScopes();
    }
}
