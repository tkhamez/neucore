<?php

declare(strict_types=1);

namespace Neucore\Entity;

/* @phan-suppress-next-line PhanUnreferencedUseNormal */
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(
 *     name="app_requests",
 *     indexes={
 *         @ORM\Index(name="ar_year_idx", columns={"year"}),
 *         @ORM\Index(name="ar_month_idx", columns={"month"})
 *     },
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
     * @ORM\Column(type="integer")
     * @var int|null
     */
    private $year;

    /**
     * @ORM\Column(type="integer")
     * @var int|null
     */
    private $month;

    /**
     * @ORM\Column(name="day_of_month", type="integer")
     * @var int|null
     */
    private $dayOfMonth;

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

    public function getYear(): ?int
    {
        return $this->year;
    }

    public function setYear(int $year): AppRequests
    {
        $this->year = $year;
        return $this;
    }

    public function getMonth(): ?int
    {
        return $this->month;
    }

    public function setMonth(int $month): AppRequests
    {
        $this->month = $month;
        return $this;
    }

    public function getDayOfMonth(): ?int
    {
        return $this->dayOfMonth;
    }

    public function setDayOfMonth(int $dayOfMonth): AppRequests
    {
        $this->dayOfMonth = $dayOfMonth;
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
