<?php

declare(strict_types=1);

namespace Neucore\Entity;

/* @phan-suppress-next-line PhanUnreferencedUseNormal */
use Doctrine\ORM\Mapping as ORM;
/* @phan-suppress-next-line PhanUnreferencedUseNormal */
use OpenApi\Annotations as OA;

/**
 * @OA\Schema(required={"id", "name"})
 *
 * @ORM\Entity()
 * @ORM\Table(name="services")
 */
class Service implements \JsonSerializable
{
    /**
     * @OA\Property()
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue
     * @var integer
     */
    private $id;

    /**
     * @OA\Property()
     * @ORM\Column(type="string", length=255)
     * @var string
     */
    private $name;

    /**
     * JSON serialized ServiceConfiguration class.
     *
     * @OA\Property(ref="#/components/schemas/ServiceConfiguration")
     * @ORM\Column(type="text", length=16777215, nullable=true)
     * @var string|null
     * @see ServiceConfiguration
     */
    private $configuration;

    public function jsonSerialize(bool $onlyRequired = true, bool $onlyRequiredConfiguration = true): array
    {
        $data = [
            'id' => (int) $this->id,
            'name' => $this->name,
        ];
        if (!$onlyRequired && !empty($this->configuration)) {
            $configuration = \json_decode((string)$this->configuration, true);
            if ($onlyRequiredConfiguration) {
                unset($configuration['phpClass']);
                unset($configuration['psr4Prefix']);
                unset($configuration['psr4Path']);
                unset($configuration['requiredGroups']);
            }
            $data['configuration'] = $configuration;
        }
        return $data;
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

    public function setConfiguration(ServiceConfiguration $configuration): self
    {
        $this->configuration = (string)\json_encode($configuration);
        return $this;
    }

    public function getConfiguration(): ServiceConfiguration
    {
        $data = \json_decode((string)$this->configuration, true);

        if (is_array($data)) {
            $class = ServiceConfiguration::fromArray($data);
        } else {
            $class = new ServiceConfiguration();
        }

        return $class;
    }
}
