<?php

declare(strict_types=1);

namespace Neucore\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="watchlists")
 */
class Watchlist
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="NONE")
     * @var integer
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=32)
     * @var string
     */
    private $name;

    /**
     * Player accounts that are white listed.
     *
     * @ORM\ManyToMany(targetEntity="Player")
     * @ORM\OrderBy({"name" = "ASC"})
     * @var Collection
     */
    private $exemptions;

    /**
     * Corporations whose members are on this list if their player accounts also have
     * members in other corporations that are not on this list.
     *
     * @ORM\ManyToMany(targetEntity="Corporation")
     * @ORM\OrderBy({"name" = "ASC"})
     * @var Collection
     */
    private $corporations;

    /**
     * Same as $corporations but for alliances.
     *
     * @ORM\ManyToMany(targetEntity="Alliance")
     * @ORM\OrderBy({"name" = "ASC"})
     * @var Collection
     */
    private $alliances;

    /**
     * Members of theses groups have read access to this list.
     *
     * @ORM\ManyToMany(targetEntity="Group")
     * @ORM\OrderBy({"name" = "ASC"})
     * @var Collection
     */
    private $groups;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->exemptions = new ArrayCollection();
        $this->corporations = new ArrayCollection();
        $this->alliances = new ArrayCollection();
        $this->groups = new ArrayCollection();
    }

    public function setId(int $id): Watchlist
    {
        $this->id = $id;

        return $this;
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    public function setName(string $name): Watchlist
    {
        $this->name = $name;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function addExemption(Player $exemption): Watchlist
    {
        $this->exemptions[] = $exemption;

        return $this;
    }

    public function removeExemption(Player $exemption): bool
    {
        return $this->exemptions->removeElement($exemption);
    }

    /**
     * @return Player[]
     */
    public function getExemptions(): array
    {
        return $this->exemptions->toArray();
    }

    public function addCorporation(Corporation $corporation): Watchlist
    {
        $this->corporations[] = $corporation;

        return $this;
    }

    public function removeCorporation(Corporation $corporation): bool
    {
        return $this->corporations->removeElement($corporation);
    }

    /**
     * @return Corporation[]
     */
    public function getCorporations(): array
    {
        return $this->corporations->toArray();
    }

    public function addAlliance(Alliance $alliance): Watchlist
    {
        $this->alliances[] = $alliance;

        return $this;
    }

    public function removeAlliance(Alliance $alliance): bool
    {
        return $this->alliances->removeElement($alliance);
    }

    /**
     * @return Alliance[]
     */
    public function getAlliances(): array
    {
        return $this->alliances->toArray();
    }

    public function addGroup(Group $group): Watchlist
    {
        $this->groups[] = $group;

        return $this;
    }

    public function removeGroup(Group $group): bool
    {
        return $this->groups->removeElement($group);
    }

    /**
     * @return Group[]
     */
    public function getGroups(): array
    {
        return $this->groups->toArray();
    }
}
