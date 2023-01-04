<?php

declare(strict_types=1);

namespace Neucore\Entity;

/* @phan-suppress-next-line PhanUnreferencedUseNormal */
use Doctrine\ORM\Mapping as ORM;
use Neucore\Data\PluginConfigurationFile;
use Neucore\Data\PluginConfigurationDatabase;
use Neucore\Plugin\ServiceInterface;
/* @phan-suppress-next-line PhanUnreferencedUseNormal */
use OpenApi\Annotations as OA;

/**
 * @OA\Schema(required={"id", "name"})
 *
 * @ORM\Entity()
 * @ORM\Table(name="plugins")
 */
class Plugin implements \JsonSerializable
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
     * JSON serialized PluginConfigurationDatabase class.
     *
     * @OA\Property(property="configurationDatabase", ref="#/components/schemas/PluginConfigurationDatabase")
     * @ORM\Column(type="text", length=16777215, nullable=true)
     * @see PluginConfigurationDatabase
     */
    private ?string $configuration = null;

    /**
     * @OA\Property(ref="#/components/schemas/PluginConfigurationFile")
     * @var ?PluginConfigurationFile
     */
    private ?PluginConfigurationFile $configurationFile = null;

    private ?ServiceInterface $implementation = null;

    public function jsonSerialize(
        bool $onlyRequired = true,
        bool $fullConfig = false,
        bool $includeBackendOnly = true
    ): array {
        $data = [
            'id' => (int) $this->id,
            'name' => $this->name,
        ];
        if (!$onlyRequired && !empty($this->configuration)) {
            $config = \json_decode((string)$this->configuration, true);
            $configurationDatabase = PluginConfigurationDatabase::fromArray($config);
            $data['configurationDatabase'] = $configurationDatabase->jsonSerialize($fullConfig, $includeBackendOnly);

            $data['configurationFile'] = $this->configurationFile?->jsonSerialize($fullConfig, $includeBackendOnly);
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

    public function setConfigurationDatabase(PluginConfigurationDatabase $configuration): self
    {
        $this->configuration = (string)\json_encode($configuration);
        return $this;
    }

    public function getConfigurationDatabase(): ?PluginConfigurationDatabase
    {
        $data = \json_decode((string)$this->configuration, true);

        if (!is_array($data)) {
            return null;
        }

        return PluginConfigurationDatabase::fromArray($data);
    }

    public function getConfigurationFile(): ?PluginConfigurationFile
    {
        return $this->configurationFile;
    }

    public function setConfigurationFile(?PluginConfigurationFile $configurationFile): self
    {
        $this->configurationFile = $configurationFile;
        return $this;
    }

    public function getServiceImplementation(): ?ServiceInterface
    {
        return $this->implementation;
    }

    public function setServiceImplementation(?ServiceInterface $implementation): self
    {
        $this->implementation = $implementation;
        return $this;
    }
}
