<?php

declare(strict_types=1);

namespace Neucore\Entity;

/* @phan-suppress-next-line PhanUnreferencedUseNormal */
use Doctrine\ORM\Mapping as ORM;
use Neucore\Data\ServiceConfiguration;
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
     */
    private ?int $id = null;

    /**
     * @OA\Property()
     * @ORM\Column(type="string", length=255)
     */
    private string $name = '';

    /**
     * JSON serialized ServiceConfiguration class.
     *
     * @OA\Property(ref="#/components/schemas/ServiceConfiguration")
     * @ORM\Column(type="text", length=16777215, nullable=true)
     * @see ServiceConfiguration
     */
    private ?string $configuration = null;

    public function jsonSerialize(bool $onlyRequired = true, bool $fullConfig = false): array
    {
        $data = [
            'id' => (int) $this->id,
            'name' => $this->name,
        ];
        if (!$onlyRequired && !empty($this->configuration)) {
            $config = \json_decode((string)$this->configuration, true);
            $configuration = ServiceConfiguration::fromArray($config)->jsonSerialize();
            if (!$fullConfig) {
                unset($configuration['name']);
                unset($configuration['type']);
                unset($configuration['directoryName']);
                unset($configuration['active']);
                unset($configuration['requiredGroups']);
                unset($configuration['phpClass']);
                unset($configuration['psr4Prefix']);
                unset($configuration['psr4Path']);
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
        return $this->name;
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
            $config = ServiceConfiguration::fromArray($data);
        } else {
            $config = new ServiceConfiguration();
        }

        return $config;
    }
}
