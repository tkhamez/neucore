<?php

declare(strict_types=1);

namespace Neucore\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(
 *     name="app_requests",
 *     indexes={ @ORM\Index(name="day_idx", columns={"day"}) }
 * )
 */
class AppRequests
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue
     * @var integer
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="App")
     * @ORM\JoinColumn(nullable=false)
     * @var App
     */
    private $app;

    /**
     * @ORM\Column(type="string", length=10)
     * @var string
     */
    private $day;

    /**
     * @ORM\Column(type="integer")
     * @var int
     */
    private $count;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getApp(): ?App
    {
        return $this->app;
    }

    public function setApp(App $app): self
    {
        $this->app = $app;
        return $this;
    }

    public function getDay(): ?string
    {
        return $this->day;
    }

    public function setDay(string $day): self
    {
        $this->day = $day;
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
