<?php declare(strict_types=1);

namespace Brave\Core\Entity;

/**
 * @SWG\Definition(
 *     definition="GroupApplication",
 *     required={"id", "player", "group", "created"}
 * )
 *
 * @Entity
 * @Table(name="group_applications")
 */
class GroupApplication implements \JsonSerializable
{
    /**
     * @SWG\Property()
     * @Id
     * @Column(type="integer")
     * @GeneratedValue
     * @var integer
     */
    private $id;

    /**
     * @SWG\Property(ref="#/definitions/Player")
     * @ManyToOne(targetEntity="Player", inversedBy="groupApplications")
     * @JoinColumn(nullable=false)
     * @var Player
     */
    private $player;

    /**
     * @SWG\Property(ref="#/definitions/Group")
     * @ManyToOne(targetEntity="Group", inversedBy="applications")
     * @JoinColumn(nullable=false)
     * @var Group
     */
    private $group;

    /**
     * @SWG\Property()
     * @Column(type="datetime", nullable=true)
     * @var \DateTime
     */
    private $created;

    /**
     * Contains only information that is of interest for clients.
     *
     * {@inheritDoc}
     * @see \JsonSerializable::jsonSerialize()
     */
    public function jsonSerialize()
    {
        return [
            'id' => $this->id,
            'player' => $this->player->jsonSerialize(true),
            'group' => $this->group,
            'created' => $this->created ? $this->created->format('Y-m-d\TH:i:s\Z') : null,
        ];
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
     * Set created.
     *
     * @param \DateTime|null $created
     *
     * @return GroupApplication
     */
    public function setCreated($created = null)
    {
        $this->created = clone $created;

        return $this;
    }

    /**
     * Get created.
     *
     * @return \DateTime|null
     */
    public function getCreated()
    {
        return $this->created;
    }

    /**
     * Set player.
     *
     * @param Player|null $player
     *
     * @return GroupApplication
     */
    public function setPlayer(Player $player = null)
    {
        $this->player = $player;

        return $this;
    }

    /**
     * Get player.
     *
     * @return Player|null
     */
    public function getPlayer()
    {
        return $this->player;
    }

    /**
     * Set group.
     *
     * @param Group|null $group
     *
     * @return GroupApplication
     */
    public function setGroup(Group $group = null)
    {
        $this->group = $group;

        return $this;
    }

    /**
     * Get group.
     *
     * @return Group|null
     */
    public function getGroup()
    {
        return $this->group;
    }
}
