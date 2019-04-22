<?php declare(strict_types=1);

namespace Brave\Core\Entity;

use Brave\Core\Api;
use Swagger\Annotations as SWG;
use Doctrine\ORM\Mapping as ORM;

/**
 * @SWG\Definition(
 *     definition="GroupApplication",
 *     required={"id", "player", "group", "created"},
 *     description="The player property contains only id and name."
 * )
 *
 * @ORM\Entity
 * @ORM\Table(name="group_applications")
 */
class GroupApplication implements \JsonSerializable
{
    /**
     * @var string
     */
    const STATUS_PENDING = 'pending';

    /**
     * @var string
     */
    const STATUS_ACCEPTED = 'accepted';

    /**
     * @var string
     */
    const STATUS_DENIED = 'denied';

    /**
     * @SWG\Property()
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue
     * @var integer
     */
    private $id;

    /**
     * @SWG\Property(ref="#/definitions/Player")
     * @ORM\ManyToOne(targetEntity="Player", inversedBy="groupApplications")
     * @ORM\JoinColumn(nullable=false)
     * @var Player
     */
    private $player;

    /**
     * @SWG\Property(ref="#/definitions/Group")
     * @ORM\ManyToOne(targetEntity="Group", inversedBy="applications")
     * @ORM\JoinColumn(nullable=false)
     * @var Group
     */
    private $group;

    /**
     * @SWG\Property()
     * @ORM\Column(type="datetime", nullable=true)
     * @var \DateTime
     */
    private $created;

    /**
     * Group application status.
     *
     * @SWG\Property(
     *     enum={"pending", "accepted", "denied"})
     * )
     * @ORM\Column(type="string", length=16)
     * @var string
     */
    private $status = self::STATUS_PENDING;

    /**
     * {@inheritDoc}
     * @see \JsonSerializable::jsonSerialize()
     */
    public function jsonSerialize()
    {
        return [
            'id' => $this->id,
            'player' => $this->player->jsonSerialize(true),
            'group' => $this->group,
            'status' => $this->status,
            'created' => $this->created ? $this->created->format(Api::DATE_FORMAT) : null,
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

    /**
     * Set status.
     *
     * Ignores any invalid value.
     */
    public function setStatus(string $status): self
    {
        if (in_array($status, [self::STATUS_PENDING, self::STATUS_ACCEPTED, self::STATUS_DENIED])) {
            $this->status = $status;
        }

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }
}
