<?php declare(strict_types=1);

namespace Brave\Core\Entity;

/**
 * EVE character.
 *
 * This is the user that logs in via EVE SSO.
 *
 * @SWG\Definition(
 *     definition="Character",
 *     required={"id", "name"}
 * )
 * @Entity
 * @Table(name="characters")
 */
class Character implements \JsonSerializable
{

    /**
     * EVE character ID.
     *
     * @SWG\Property(format="int64")
     * @Id
     * @Column(type="bigint")
     * @NONE
     * @var integer
     */
    private $id;

    /**
     * EVE character name.
     *
     * @SWG\Property()
     * @Column(type="string", length=255)
     * @var string
     */
    private $name;

    /**
     *
     * @SWG\Property()
     * @Column(type="boolean")
     * @var bool
     */
    private $main = false;

    /**
     *
     * @Column(type="text", length=65535, name="character_owner_hash", nullable=true)
     * @var string
     */
    private $characterOwnerHash;

    /**
     * @Column(type="text", length=65535, name="access_token", nullable=true)
     * @var string
     */
    private $accessToken;

    /**
     * Unix timestamp when access token expires.
     *
     * @Column(type="integer", nullable=true)
     * @var int
     */
    private $expires;

    /**
     * @Column(type="text", length=65535, name="refresh_token", nullable=true)
     * @var string
     */
    private $refreshToken;

    /**
     * Shows if character's refresh token is valid or not.
     *
     * If there is no refresh token this is null.
     *
     * @SWG\Property()
     * @Column(type="boolean", name="valid_token", nullable=true)
     * @var bool
     */
    private $validToken;

    /**
     * Date and time when that valid token property was changed.
     *
     * @Column(type="datetime", name="valid_token_time", nullable=true)
     * @var \DateTime
     */
    private $validTokenTime;

    /**
     * OAuth scopes.
     *
     * @Column(type="text", length=65535, nullable=true)
     * @var string
     */
    private $scopes;

    /**
     * @Column(type="datetime", name="last_login", nullable=true)
     * @var \DateTime
     */
    private $lastLogin;

    /**
     * Last ESI update.
     *
     * @SWG\Property()
     * @Column(type="datetime", name="last_update", nullable=true)
     * @var \DateTime
     */
    private $lastUpdate;

    /**
     * @ManyToOne(targetEntity="Player", inversedBy="characters")
     * @JoinColumn(nullable=false)
     * @var Player
     */
    private $player;

    /**
     *
     * @SWG\Property(ref="#/definitions/Corporation")
     * @ManyToOne(targetEntity="Corporation", inversedBy="characters")
     * @var Corporation
     */
    private $corporation;

    /**
     * @OneToOne(targetEntity="CorporationMember", mappedBy="character")
     * @var CorporationMember
     */
    private $corporationMember;

    /**
     * Contains only information that is of interest for clients.
     *
     * {@inheritDoc}
     * @see \JsonSerializable::jsonSerialize()
     */
    public function jsonSerialize($withRelations = true)
    {
        $result = [
            'id' => $this->getId(),
            'name' => $this->name,
            'main' => $this->main,
            'lastUpdate' => $this->lastUpdate ? $this->lastUpdate->format('Y-m-d\TH:i:s\Z') : null,
            'validToken' => $this->validToken,
        ];
        if ($withRelations) {
            $result['corporation'] = $this->corporation;
        }

        return $result;
    }

    /**
     * Set id.
     *
     * @param int $id
     *
     * @return Character
     */
    public function setId(int $id)
    {
        $this->id = $id;

        return $this;
    }

    /**
     * Get id.
     *
     * @return int
     */
    public function getId()
    {
        // cast to int because Doctrine creates string for type bigint
        return $this->id !== null ? (int) $this->id : null;
    }

    /**
     * Set name.
     *
     * @param string $name
     *
     * @return Character
     */
    public function setName(string $name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get name.
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set main.
     *
     * @param bool $main
     *
     * @return Character
     */
    public function setMain(bool $main)
    {
        $this->main = $main;

        return $this;
    }

    /**
     * Get main.
     *
     * @return bool
     */
    public function getMain()
    {
        return $this->main;
    }

    /**
     * Set characterOwnerHash.
     *
     * @param string|null $characterOwnerHash
     *
     * @return Character
     */
    public function setCharacterOwnerHash(string $characterOwnerHash = null)
    {
        $this->characterOwnerHash = $characterOwnerHash;

        return $this;
    }

    /**
     * Get characterOwnerHash.
     *
     * @return string|null
     */
    public function getCharacterOwnerHash()
    {
        return $this->characterOwnerHash;
    }

    /**
     * Set accessToken.
     *
     * @param string|null $accessToken
     *
     * @return Character
     */
    public function setAccessToken(string $accessToken = null)
    {
        $this->accessToken = $accessToken;

        return $this;
    }

    /**
     * Get accessToken.
     *
     * @return string|null
     */
    public function getAccessToken()
    {
        return $this->accessToken;
    }

    /**
     * Set expires.
     *
     * @param int|null $expires
     *
     * @return Character
     */
    public function setExpires(int $expires = null)
    {
        $this->expires = $expires;

        return $this;
    }

    /**
     * Get expires.
     *
     * @return int|null
     */
    public function getExpires()
    {
        return $this->expires;
    }

    /**
     * Set refreshToken.
     *
     * @param string $refreshToken
     *
     * @return Character
     */
    public function setRefreshToken(string $refreshToken = null)
    {
        $this->refreshToken = $refreshToken;

        return $this;
    }

    /**
     * Get refreshToken.
     *
     * @return string|null
     */
    public function getRefreshToken()
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
            $this->validTokenTime = date_create();
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

    /**
     * Set scopes.
     *
     * @param string|null $scopes
     *
     * @return Character
     */
    public function setScopes($scopes = null)
    {
        $this->scopes = $scopes;

        return $this;
    }

    /**
     * Get scopes.
     *
     * @return string|null
     */
    public function getScopes()
    {
        return $this->scopes;
    }

    /**
     * Set lastLogin.
     *
     * @param \DateTime $lastLogin
     *
     * @return Character
     */
    public function setLastLogin($lastLogin)
    {
        $this->lastLogin = clone $lastLogin;

        return $this;
    }

    /**
     * Get lastLogin.
     *
     * @return \DateTime|null
     */
    public function getLastLogin()
    {
        return $this->lastLogin;
    }

    /**
     * Set lastUpdate.
     *
     * @param \DateTime $lastUpdate
     *
     * @return Character
     */
    public function setLastUpdate($lastUpdate)
    {
        $this->lastUpdate = clone $lastUpdate;

        return $this;
    }

    /**
     * Get lastUpdate.
     *
     * @return \DateTime|null
     */
    public function getLastUpdate()
    {
        return $this->lastUpdate;
    }

    /**
     * Set player.
     *
     * @param Player $player
     *
     * @return Character
     */
    public function setPlayer(Player $player)
    {
        $this->player = $player;

        return $this;
    }

    /**
     * Get player.
     *
     * A character always belongs to a player.
     *
     * @return Player
     */
    public function getPlayer()
    {
        return $this->player;
    }

    /**
     * Set corporation.
     *
     * @param Corporation|null $corporation
     *
     * @return Character
     */
    public function setCorporation(Corporation $corporation = null)
    {
        $this->corporation = $corporation;

        return $this;
    }

    /**
     * Get corporation.
     *
     * @return Corporation|null
     */
    public function getCorporation()
    {
        return $this->corporation;
    }

    /**
     * Set corporationMember.
     *
     * @param CorporationMember|null $corporationMember
     *
     * @return Character
     */
    public function setCorporationMember(CorporationMember $corporationMember = null)
    {
        $this->corporationMember = $corporationMember;

        return $this;
    }

    /**
     * Get corporationMember.
     *
     * @return CorporationMember|null
     */
    public function getCorporationMember()
    {
        return $this->corporationMember;
    }
}
