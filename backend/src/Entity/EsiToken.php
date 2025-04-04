<?php

/** @noinspection PhpUnusedAliasInspection */

declare(strict_types=1);

namespace Neucore\Entity;

use Doctrine\ORM\Mapping as ORM;
use Neucore\Api;
use Neucore\Plugin\Data\CoreEsiToken;
use OpenApi\Attributes as OA;

#[ORM\Entity]
#[ORM\Table(
    name: "esi_tokens",
    uniqueConstraints: [
        new ORM\UniqueConstraint(name: "character_eve_login_idx", columns: ["character_id", "eve_login_id"]),
    ],
    options: ["charset" => "utf8mb4", "collate" => "utf8mb4_unicode_520_ci"],
)]
#[OA\Schema(
    required: ['eveLoginId', 'characterId', 'playerId', 'validToken', 'validTokenTime', 'hasRoles'],
    properties: [
        new OA\Property(property: 'eveLoginId', description: 'ID of EveLogin', type: 'integer'),
        new OA\Property(property: 'characterId', description: 'ID of Character', type: 'integer'),
        new OA\Property(property: 'playerId', description: 'ID of Player', type: 'integer'),
        new OA\Property(property: 'playerName', description: 'Name of Player', type: 'string'),
    ],
)]
class EsiToken implements \JsonSerializable
{
    #[ORM\Id]
    #[ORM\Column(type: "integer")]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Character::class, inversedBy: "esiTokens")]
    #[ORM\JoinColumn(name: "character_id", nullable: false, onDelete: "CASCADE")]
    #[OA\Property(ref: '#/components/schemas/Character', nullable: false)]
    private ?Character $character = null;

    #[ORM\ManyToOne(targetEntity: EveLogin::class, inversedBy: "esiTokens")]
    #[ORM\JoinColumn(name: "eve_login_id", nullable: false, onDelete: "CASCADE")]
    private ?EveLogin $eveLogin = null;

    /**
     * The OAuth refresh token.
     *
     * Empty string if the token became invalid.
     *
     */
    #[ORM\Column(name: "refresh_token", type: "text", length: 65535)]
    private ?string $refreshToken = null;

    #[ORM\Column(name: "access_token", type: "text", length: 65535)]
    private ?string $accessToken = null;

    /**
     * Shows if the refresh token is valid or not.
     *
     * This is null if there is no refresh token (EVE SSOv1 only)
     * or a valid token but without scopes (SSOv2).
     */
    #[ORM\Column(name: "valid_token", type: "boolean", nullable: true)]
    #[OA\Property]
    private ?bool $validToken = null;

    /**
     * Date and time when the valid token property was last changed.
     */
    #[ORM\Column(name: "valid_token_time", type: "datetime", nullable: true)]
    #[OA\Property]
    private ?\DateTime $validTokenTime = null;

    /**
     * Shows if the EVE character has all required roles for the login.
     *
     * Null if the login does not require any roles or if the token is invalid.
     */
    #[ORM\Column(name: "has_roles", type: "boolean", nullable: true)]
    #[OA\Property]
    private ?bool $hasRoles = null;

    /**
     * Unix timestamp when access token expires.
     *
     */
    #[ORM\Column(type: "integer")] private ?int $expires = null;

    /**
     * When the refresh token was last checked for validity.
     */
    #[ORM\Column(name: "last_checked", type: "datetime", nullable: true)]
    #[OA\Property]
    private ?\DateTime $lastChecked = null;

    public function jsonSerialize(bool $withCharacterDetails = false): array
    {
        $data = [
            'eveLoginId' => $this->eveLogin ? $this->eveLogin->getId() : 0,
            'characterId' => $this->character ? $this->character->getId() : 0,
            'playerId' => $this->character ? $this->character->getPlayer()->getId() : 0,
            'validToken' => $this->validToken,
            'validTokenTime' => $this->validTokenTime?->format(Api::DATE_FORMAT),
            'hasRoles' => $this->hasRoles,
            'lastChecked' => $this->lastChecked?->format(Api::DATE_FORMAT),
        ];

        if ($withCharacterDetails) {
            $data['playerName'] = $this->character?->getPlayer()->getName();
            $data['character'] = $this->character?->jsonSerialize(true);
        }

        return $data;
    }

    public function setId(int $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setCharacter(Character $character): self
    {
        $this->character = $character;
        return $this;
    }

    public function getCharacter(): ?Character
    {
        return $this->character;
    }

    public function setEveLogin(EveLogin $eveLogin): self
    {
        $this->eveLogin = $eveLogin;
        return $this;
    }

    public function getEveLogin(): ?EveLogin
    {
        return $this->eveLogin;
    }

    public function setRefreshToken(string $refreshToken): self
    {
        $this->refreshToken = $refreshToken;
        return $this;
    }

    public function getRefreshToken(): string
    {
        return (string) $this->refreshToken;
    }

    public function setAccessToken(string $accessToken): self
    {
        $this->accessToken = $accessToken;
        return $this;
    }

    public function getAccessToken(): string
    {
        return (string) $this->accessToken;
    }

    public function setExpires(int $expires): self
    {
        $this->expires = $expires;
        return $this;
    }

    public function getExpires(): ?int
    {
        return $this->expires;
    }

    /**
     * Set validToken and updates validTokenTime.
     */
    public function setValidToken(?bool $validToken = null): self
    {
        if ($this->validToken !== $validToken) {
            $this->validTokenTime = new \DateTime();
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

    public function setHasRoles(?bool $hasRole = null): self
    {
        $this->hasRoles = $hasRole;
        return $this;
    }

    public function getHasRoles(): ?bool
    {
        return $this->hasRoles;
    }

    public function setLastChecked(\DateTime $lastChecked): self
    {
        $this->lastChecked = clone $lastChecked;
        return $this;
    }

    public function getLastChecked(): ?\DateTime
    {
        return $this->lastChecked;
    }

    public function toCoreEsiToken(bool $fullCharacter): ?CoreEsiToken
    {
        if (!$this->character || !$this->getEveLogin()) {
            return null;
        }

        return new CoreEsiToken(
            $this->character->toCoreCharacter($fullCharacter),
            $this->getEveLogin()->getName(),
            !empty($this->getEveLogin()->getEsiScopes()) ?
                array_map('trim', explode(' ', $this->getEveLogin()->getEsiScopes())) :
                [],
            $this->getEveLogin()->getEveRoles(),
            $this->validToken,
            $this->validTokenTime,
            $this->hasRoles,
            $this->lastChecked,
        );
    }
}
