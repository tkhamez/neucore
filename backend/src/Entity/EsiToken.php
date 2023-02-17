<?php

/** @noinspection PhpUnusedAliasInspection */

declare(strict_types=1);

namespace Neucore\Entity;

/* @phan-suppress-next-line PhanUnreferencedUseNormal */
use Doctrine\ORM\Mapping as ORM;
use Neucore\Api;
use Neucore\Plugin\Data\CoreEsiToken;
/* @phan-suppress-next-line PhanUnreferencedUseNormal */
use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *     required={"eveLoginId", "characterId", "playerId", "validToken", "validTokenTime", "hasRoles"},
 *     @OA\Property(property="eveLoginId", type="integer", description="ID of EveLogin"),
 *     @OA\Property(property="characterId", type="integer", description="ID of Character"),
 *     @OA\Property(property="playerId", type="integer", description="ID of Player"),
 *     @OA\Property(property="playerName", type="string", description="Name of Player"),
 * )
 * @ORM\Entity
 * @ORM\Table(
 *     name="esi_tokens",
 *     options={"charset"="utf8mb4", "collate"="utf8mb4_unicode_520_ci"},
 *     uniqueConstraints={
 *         @ORM\UniqueConstraint(name="character_eve_login_idx", columns={"character_id", "eve_login_id"})
 *     }
 * )
 */
class EsiToken implements \JsonSerializable
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue
     */
    private ?int $id = null;

    /**
     * @OA\Property(ref="#/components/schemas/Character", nullable=true)
     * @ORM\ManyToOne(targetEntity="Character", inversedBy="esiTokens")
     * @ORM\JoinColumn(nullable=false, name="character_id", onDelete="CASCADE")
     */
    private ?Character $character = null;

    /**
     * @ORM\ManyToOne(targetEntity="EveLogin", inversedBy="esiTokens")
     * @ORM\JoinColumn(nullable=false, name="eve_login_id", onDelete="CASCADE")
     */
    private ?EveLogin $eveLogin = null;

    /**
     * The OAuth refresh token.
     *
     * Empty string if the token became invalid.
     *
     * @ORM\Column(type="text", length=65535, name="refresh_token")
     */
    private ?string $refreshToken = null;

    /**
     * @ORM\Column(type="text", length=65535, name="access_token")
     */
    private ?string $accessToken = null;

    /**
     * Shows if the refresh token is valid or not.
     *
     * This is null if there is no refresh token (EVE SSOv1 only)
     * or a valid token but without scopes (SSOv2).
     *
     * @OA\Property
     * @ORM\Column(type="boolean", name="valid_token", nullable=true)
     */
    private ?bool $validToken = null;

    /**
     * Date and time when the valid token property was last changed.
     *
     * @OA\Property
     * @ORM\Column(type="datetime", name="valid_token_time", nullable=true)
     */
    private ?\DateTime $validTokenTime = null;

    /**
     * Shows if the EVE character has all required roles for the login.
     *
     * Null if the login does not require any roles or if the token is invalid.
     *
     * @OA\Property
     * @ORM\Column(type="boolean", name="has_roles", nullable=true)
     */
    private ?bool $hasRoles = null;

    /**
     * Unix timestamp when access token expires.
     *
     * @ORM\Column(type="integer")
     */
    private ?int $expires = null;

    /**
     * When the refresh token was last checked for validity.
     *
     * @OA\Property
     * @ORM\Column(type="datetime", name="last_checked", nullable=true)
     */
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
            $this->getValidToken(),
            $this->getHasRoles(),
            $this->getLastChecked(),
        );
    }
}
