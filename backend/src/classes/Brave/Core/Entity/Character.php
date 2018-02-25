<?php
namespace Brave\Core\Entity;

/**
 * EVE character.
 *
 * This is the user that logs in via EVE SSO.
 *
 * @SWG\Definition(definition="Character")
 * @Entity(repositoryClass="Brave\Core\Entity\CharacterRepository")
 * @Table(name="characters")
 */
class Character implements \JsonSerializable
{

    /**
     * EVE character ID
     *
     * @SWG\Property(format="int64")
     * @Id
     * @Column(type="bigint")
     * @NONE
     * @var integer
     */
    private $id;

    /**
     * EVE character name
     *
     * @SWG\Property()
     * @Column(type="string", length=255)
     * @var string
     */
    private $name;

    /**
     *
     * @SWG\Property()
     * @Column(type="boolean", nullable=false)
     * @var bool
     */
    private $main;

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
