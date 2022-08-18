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
 *         @ORM\Index(name="ar_year_idx", columns={"request_year"}),
 *         @ORM\Index(name="ar_month_idx", columns={"request_month"}),
 *         @ORM\Index(name="ar_day_of_month_idx", columns={"request_day_of_month"}),
 *         @ORM\Index(name="ar_hour_idx", columns={"request_hour"})
 *     },
 * )
 */
class AppRequests
{
    /**
     * @ORM\Id
     * @ORM\Column(type="bigint")
     * @ORM\GeneratedValue
     */
    private ?int $id = null;

    /**
     * @ORM\ManyToOne(targetEntity="App")
     * @ORM\JoinColumn(nullable=false)
     */
    private ?App $app = null;

    /**
     * @ORM\Column(name="request_year", type="integer")
     */
    private ?int $year = null;

    /**
     * @ORM\Column(name="request_month", type="integer")
     */
    private ?int $month = null;

    /**
     * @ORM\Column(name="request_day_of_month", type="integer")
     */
    private ?int $dayOfMonth = null;

    /**
     * @ORM\Column(name="request_hour", type="integer")
     */
    private ?int $hour = null;

    /**
     * @ORM\Column(name="request_count", type="integer")
     */
    private int $count = 0;

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

    public function getDayOfMonth(): ?int
    {
        return $this->dayOfMonth;
    }

    public function setDayOfMonth(int $dayOfMonth): self
    {
        $this->dayOfMonth = $dayOfMonth;
        return $this;
    }

    public function getHour(): ?int
    {
        return $this->hour;
    }

    public function setHour(int $hour): self
    {
        $this->hour = $hour;
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
