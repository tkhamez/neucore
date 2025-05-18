<?php

declare(strict_types=1);

namespace Neucore\Entity;

use Doctrine\ORM\Mapping as ORM;
use Neucore\Api;
use OpenApi\Attributes as OA;

#[ORM\Entity]
#[ORM\Table(
    name: "group_applications",
    uniqueConstraints: [new ORM\UniqueConstraint(name: "player_group_idx", columns: ["player_id", "group_id"])],
    options: ["charset" => "utf8mb4", "collate" => "utf8mb4_unicode_520_ci"],
)]
#[OA\Schema(
    description: 'The player property contains only id and name.',
    required: ['id', 'player', 'group', 'created'],
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

    #[ORM\Id]
    #[ORM\Column(type: "integer")]
    #[ORM\GeneratedValue]
    #[OA\Property]
    // @phpstan-ignore property.unusedType
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Player::class, inversedBy: "groupApplications")]
    #[ORM\JoinColumn(nullable: false)]
    #[OA\Property(ref: '#/components/schemas/Player')]
    private Player $player;

    #[ORM\ManyToOne(targetEntity: Group::class, inversedBy: "applications")]
    #[ORM\JoinColumn(nullable: false)]
    #[OA\Property(ref: '#/components/schemas/Group')]
    private Group $group;

    #[ORM\Column(type: "datetime", nullable: true)]
    #[OA\Property(nullable: true)]
    private ?\DateTime $created = null;

    /**
     * Group application status.
     */
    #[ORM\Column(type: "string", length: 16)]
    #[OA\Property(enum: ['pending', 'accepted', 'denied'])]
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
