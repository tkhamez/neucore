<?php

declare(strict_types=1);

namespace Neucore\Entity;

use Doctrine\ORM\Mapping as ORM;
use Neucore\Api;
/* @phan-suppress-next-line PhanUnreferencedUseNormal */
use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *     required={"id", "player", "group", "created"},
 *     description="The player property contains only id and name."
 * )
 *
 */
#[ORM\Entity]
#[ORM\Table(
    name: "group_applications",
    uniqueConstraints: [new ORM\UniqueConstraint(name: "player_group_idx", columns: ["player_id", "group_id"])],
    options: ["charset" => "utf8mb4", "collate" => "utf8mb4_unicode_520_ci"]
)]
class GroupApplication implements \JsonSerializable
{
    /**
     * @var string
     */
    public const STATUS_PENDING = 'pending';

    /**
     * @var string
     */
    public const STATUS_ACCEPTED = 'accepted';

    /**
     * @var string
     */
    public const STATUS_DENIED = 'denied';

    /**
     * @OA\Property()
     */
    #[ORM\Id]
    #[ORM\Column(type: "integer")]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    /**
     * @OA\Property(ref="#/components/schemas/Player")
     */
    #[ORM\ManyToOne(targetEntity: "Player", inversedBy: "groupApplications")]
    #[ORM\JoinColumn(nullable: false)]
    private Player $player;

    /**
     * @OA\Property(ref="#/components/schemas/Group")
     */
    #[ORM\ManyToOne(targetEntity: "Group", inversedBy: "applications")]
    #[ORM\JoinColumn(nullable: false)]
    private Group $group;

    /**
     * @OA\Property(nullable=true)
     */
    #[ORM\Column(type: "datetime", nullable: true)]
    private ?\DateTime $created = null;

    /**
     * Group application status.
     *
     * @OA\Property(
     *     enum={"pending", "accepted", "denied"})
     * )
     */
    #[ORM\Column(type: "string", length: 16)]
    private string $status = self::STATUS_PENDING;

    /**
     * {@inheritDoc}
     * @see \JsonSerializable::jsonSerialize()
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'player' => $this->player->jsonSerialize(true),
            'group' => $this->group,
            'status' => $this->status,
            'created' => $this->getCreated()?->format(Api::DATE_FORMAT),
        ];
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setCreated(\DateTime $created): static
    {
        $this->created = clone $created;

        return $this;
    }

    public function getCreated(): ?\DateTime
    {
        return $this->created;
    }

    public function setPlayer(Player $player): static
    {
        $this->player = $player;

        return $this;
    }

    public function getPlayer(): Player
    {
        return $this->player;
    }

    public function setGroup(Group $group): static
    {
        $this->group = $group;

        return $this;
    }

    public function getGroup(): Group
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
