<?php

/** @noinspection PhpUnusedAliasInspection */

declare(strict_types=1);

namespace Neucore\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Neucore\Plugin\Data\CoreGroup;
/* @phan-suppress-next-line PhanUnreferencedUseNormal */
use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *     required={"id", "name"}
 * )
 */
#[ORM\Entity]
#[ORM\Table(name: "groups_tbl", options: ["charset" => "utf8mb4", "collate" => "utf8mb4_unicode_520_ci"])]
class Group implements \JsonSerializable
{
    public const VISIBILITY_PRIVATE = 'private';

    public const VISIBILITY_PUBLIC = 'public';

    /**
     * Group ID.
     *
     * @OA\Property()
     */
    #[ORM\Id]
    #[ORM\Column(type: "integer")]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    /**
     * A unique group name (can be changed).
     *
     * @OA\Property(maxLength=64, pattern="^[-._a-zA-Z0-9]+$")
     */
    #[ORM\Column(type: "string", length: 64, unique: true)]
    private ?string $name = null;

    /**
     * @OA\Property(maxLength=1024)
     */
    #[ORM\Column(type: "string", length: 1024, nullable: true)]
    private ?string $description = null;

    /**
     * @OA\Property(enum={"private", "public"})
     */
    #[ORM\Column(type: "string", length: 16, options: ["default" => "private"])]
    private string $visibility = self::VISIBILITY_PRIVATE;

    /**
     * @OA\Property()
     */
    #[ORM\Column(name: "auto_accept", type: "boolean")]
    private bool $autoAccept = false;

    /**
     * @OA\Property()
     */
    #[ORM\Column(name: "is_default", type: "boolean")]
    private bool $isDefault = false;

    #[ORM\OneToMany(targetEntity: GroupApplication::class, mappedBy: "group", cascade: ["remove"])]
    #[ORM\OrderBy(["created" => "DESC"])]
    private Collection $applications;

    /**
     * Group members.
     *
     */
    #[ORM\ManyToMany(targetEntity: Player::class, mappedBy: "groups")]
    #[ORM\OrderBy(["name" => "ASC"])]
    private Collection $players;

    #[ORM\ManyToMany(targetEntity: Player::class, inversedBy: "managerGroups")]
    #[ORM\JoinTable(name: "group_manager")]
    #[ORM\OrderBy(["name" => "ASC"])]
    private Collection $managers;

    #[ORM\ManyToMany(targetEntity: App::class, mappedBy: "groups")]
    #[ORM\OrderBy(["name" => "ASC"])]
    private Collection $apps;

    /**
     * Corporations for automatic assignment.
     *
     */
    #[ORM\ManyToMany(targetEntity: Corporation::class, mappedBy: "groups")]
    #[ORM\OrderBy(["name" => "ASC"])]
    private Collection $corporations;

    /**
     * Alliances for automatic assignment.
     *
     */
    #[ORM\ManyToMany(targetEntity: Alliance::class, mappedBy: "groups")]
    #[ORM\OrderBy(["name" => "ASC"])]
    private Collection $alliances;

    /**
     * A player must be a member of one of these groups in order to be a member of this group.
     *
     */
    #[ORM\ManyToMany(targetEntity: Group::class)]
    #[ORM\JoinTable(name: "group_required_groups")]
    #[ORM\OrderBy(["name" => "ASC"])]
    private Collection $requiredGroups;

    /**
     * A player must not be a member of any of these groups in order to be a member of this group.
     *
     */
    #[ORM\ManyToMany(targetEntity: Group::class)]
    #[ORM\JoinTable(name: "group_forbidden_groups")]
    #[ORM\OrderBy(["name" => "ASC"])]
    private Collection $forbiddenGroups;

    /**
     * Contains only information that is of interest to clients.
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'visibility' => $this->visibility,
            'autoAccept' => $this->autoAccept,
            'isDefault' => $this->isDefault,
        ];
    }

    public function __construct()
    {
        $this->applications = new ArrayCollection();
        $this->players = new ArrayCollection();
        $this->managers = new ArrayCollection();
        $this->apps = new ArrayCollection();
        $this->corporations = new ArrayCollection();
        $this->alliances = new ArrayCollection();
        $this->requiredGroups = new ArrayCollection();
        $this->forbiddenGroups = new ArrayCollection();
    }

    public function getId(): int
    {
        return (int) $this->id;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getName(): string
    {
        return (string) $this->name;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getDescription(): string
    {
        return (string) $this->description;
    }

    /**
     * @param string $visibility self::VISIBILITY_PRIVATE or self::VISIBILITY_PUBLIC
     * @throws \InvalidArgumentException if parameter is invalid
     */
    public function setVisibility(string $visibility): self
    {
        $valid = [self::VISIBILITY_PRIVATE, self::VISIBILITY_PUBLIC];
        if (! in_array($visibility, $valid)) {
            throw new \InvalidArgumentException('Parameter must be one of ' . implode(', ', $valid));
        }

        $this->visibility = $visibility;

        return $this;
    }

    public function getVisibility(): string
    {
        return $this->visibility;
    }

    public function setAutoAccept(bool $autoAccept): self
    {
        $this->autoAccept = $autoAccept;

        return $this;
    }

    public function getAutoAccept(): bool
    {
        return $this->autoAccept;
    }

    public function setIsDefault(bool $isDefault): self
    {
        $this->isDefault = $isDefault;

        return $this;
    }

    public function getIsDefault(): bool
    {
        return $this->isDefault;
    }

    public function addApplication(GroupApplication $application): self
    {
        $this->applications[] = $application;

        return $this;
    }

    /**
     * @return boolean TRUE if this collection contained the specified element, FALSE otherwise.
     */
    public function removeApplication(GroupApplication $application): bool
    {
        return $this->applications->removeElement($application);
    }

    /**
     * @return GroupApplication[]
     */
    public function getApplications(): array
    {
        return array_values($this->applications->toArray());
    }

    /**
     * @return Player[]
     */
    public function getPlayers(): array
    {
        return array_values($this->players->toArray());
    }

    public function addManager(Player $manager): self
    {
        $this->managers[] = $manager;

        return $this;
    }

    /**
     * @return boolean TRUE if this collection contained the specified element, FALSE otherwise.
     */
    public function removeManager(Player $manager): bool
    {
        return $this->managers->removeElement($manager);
    }

    /**
     * @return Player[]
     */
    public function getManagers(): array
    {
        return array_values($this->managers->toArray());
    }

    public function addApp(App $app): self
    {
        $this->apps[] = $app;

        return $this;
    }

    /**
     *
     * @return boolean TRUE if this collection contained the specified element, FALSE otherwise.
     */
    public function removeApp(App $app): bool
    {
        return $this->apps->removeElement($app);
    }

    /**
     * @return App[]
     */
    public function getApps(): array
    {
        return array_values($this->apps->toArray());
    }

    public function addCorporation(Corporation $corporation): self
    {
        $this->corporations[] = $corporation;

        return $this;
    }

    /**
     * @return boolean TRUE if this collection contained the specified element, FALSE otherwise.
     */
    public function removeCorporation(Corporation $corporation): bool
    {
        return $this->corporations->removeElement($corporation);
    }

    public function getCorporations(): array
    {
        return array_values($this->corporations->toArray());
    }

    public function addAlliance(Alliance $alliance): self
    {
        $this->alliances[] = $alliance;

        return $this;
    }

    /**
     *
     * @return boolean TRUE if this collection contained the specified element, FALSE otherwise.
     */
    public function removeAlliance(Alliance $alliance): bool
    {
        return $this->alliances->removeElement($alliance);
    }

    /**
     * @return Alliance[]
     */
    public function getAlliances(): array
    {
        return array_values($this->alliances->toArray());
    }

    public function addRequiredGroup(Group $requiredGroup): self
    {
        foreach ($this->getRequiredGroups() as $entity) {
            if ($requiredGroup->getId() && $entity->getId() === $requiredGroup->getId()) {
                return $this;
            }
        }
        $this->requiredGroups[] = $requiredGroup;

        return $this;
    }

    /**
     * @return boolean TRUE if this collection contained the specified element, FALSE otherwise.
     */
    public function removeRequiredGroup(Group $requiredGroup): bool
    {
        return $this->requiredGroups->removeElement($requiredGroup);
    }

    /**
     * Get requiredGroups, ordered by name asc.
     *
     * @return Group[]
     */
    public function getRequiredGroups(): array
    {
        return array_values($this->requiredGroups->toArray());
    }

    public function addForbiddenGroup(Group $forbiddenGroups): self
    {
        foreach ($this->getForbiddenGroups() as $entity) {
            if ($forbiddenGroups->getId() && $entity->getId() === $forbiddenGroups->getId()) {
                return $this;
            }
        }
        $this->forbiddenGroups[] = $forbiddenGroups;

        return $this;
    }

    /**
     * @return boolean TRUE if this collection contained the specified element, FALSE otherwise.
     */
    public function removeForbiddenGroup(Group $forbiddenGroups): bool
    {
        return $this->forbiddenGroups->removeElement($forbiddenGroups);
    }

    /**
     * Get forbiddenGroups, ordered by name asc.
     *
     * @return Group[]
     */
    public function getForbiddenGroups(): array
    {
        return array_values($this->forbiddenGroups->toArray());
    }

    public function toCoreGroup(): CoreGroup
    {
        return new CoreGroup($this->getId(), $this->getName());
    }
}
