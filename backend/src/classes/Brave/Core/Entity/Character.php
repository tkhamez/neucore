<?php
namespace Brave\Core\Entity;

/**
 * EVE character.
 *
 * This is the user that logs in via EVE SSO.
 *
 * @SWG\Definition(
 *     definition="Character",
 *     required={"id", "name", "main"}
 * )
 * @Entity(repositoryClass="Brave\Core\Entity\CharacterRepository")
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
     *
     * @Column(type="text", length=65535, name="character_owner_hash")
     * @var string
     */
    private $characterOwnerHash;

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
    private $main;

    /**
     * @Column(type="text", length=65535, name="access_token")
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
     * @ManyToOne(targetEntity="Player", inversedBy="characters")
     * @var Player
     */
    private $player;

    /**
     * Contains only information that is of interest for clients.
     *
     * {@inheritDoc}
     * @see \JsonSerializable::jsonSerialize()
     */
    public function jsonSerialize()
    {
        $arr = [
            'id' => (int) $this->id,
            'name' => $this->name,
            'main' => $this->main
        ];

        return $arr;
    }

    /**
     * Set id.
     *
     * @param int $id
     *
     * @return Character
     */
    public function setId($id)
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
        return $this->id;
    }

    /**
     * Set name.
     *
     * @param string $name
     *
     * @return Character
     */
    public function setName($name)
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
    public function setMain($main)
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
     * @param string $characterOwnerHash
     *
     * @return Character
     */
    public function setCharacterOwnerHash($characterOwnerHash)
    {
        $this->characterOwnerHash = $characterOwnerHash;

        return $this;
    }

    /**
     * Get characterOwnerHash.
     *
     * @return string
     */
    public function getCharacterOwnerHash()
    {
        return $this->characterOwnerHash;
    }

    /**
     * Set accessToken.
     *
     * @param string $accessToken
     *
     * @return Character
     */
    public function setAccessToken($accessToken)
    {
        $this->accessToken = $accessToken;

        return $this;
    }

    /**
     * Get accessToken.
     *
     * @return string
     */
    public function getAccessToken()
    {
        return $this->accessToken;
    }

    /**
     * Set expires.
     *
     * @param int $expires
     *
     * @return Character
     */
    public function setExpires($expires)
    {
        $this->expires = $expires;

        return $this;
    }

    /**
     * Get expires.
     *
     * @return int
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
    public function setRefreshToken($refreshToken)
    {
        $this->refreshToken = $refreshToken;

        return $this;
    }

    /**
     * Get refreshToken.
     *
     * @return string
     */
    public function getRefreshToken()
    {
        return $this->refreshToken;
    }

    /**
     * Set player.
     *
     * @param \Brave\Core\Entity\Player|null $player
     *
     * @return Character
     */
    public function setPlayer(\Brave\Core\Entity\Player $player = null)
    {
        $this->player = $player;

        return $this;
    }

    /**
     * Get player.
     *
     * @return \Brave\Core\Entity\Player|null
     */
    public function getPlayer()
    {
        return $this->player;
    }
}
