<?php

declare(strict_types=1);

namespace Neucore\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(
    name: "player_logins",
    indexes: [
        new ORM\Index(name: "pl_year_idx", columns: ["request_year"]),
        new ORM\Index(name: "pl_month_idx", columns: ["request_month"]),
    ],
    options: ["charset" => "utf8mb4", "collate" => "utf8mb4_unicode_520_ci"],
)]
class PlayerLogins
{
    #[ORM\Id]
    #[ORM\Column(type: "integer")]
    #[ORM\GeneratedValue]
    // @phpstan-ignore property.unusedType
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Player::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Player $player = null;

    #[ORM\Column(name: "request_year", type: "integer")]
    private ?int $year = null;

    #[ORM\Column(name: "request_month", type: "integer")]
    private ?int $month = null;

    #[ORM\Column(name: "request_count", type: "integer")]
    private int $count = 0;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPlayer(): ?Player
    {
        return $this->player;
    }

    public function setPlayer(Player $player): self
    {
        $this->player = $player;
        return $this;
    }

    public function getYear(): ?int
    {
        return $this->year;
    }

    public function setYear(int $year): self
    {
        $this->year = $year;
        return $this;
    }

    public function getMonth(): ?int
    {
        return $this->month;
    }

    public function setMonth(int $month): self
    {
        $this->month = $month;
        return $this;
    }

    public function getCount(): ?int
    {
        return $this->count;
    }

    public function setCount(int $count): self
    {
        $this->count = $count;
        return $this;
    }
}
