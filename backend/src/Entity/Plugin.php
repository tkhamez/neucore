<?php

declare(strict_types=1);

namespace Neucore\Entity;

use Doctrine\ORM\Mapping as ORM;
use Neucore\Data\PluginConfigurationFile;
use Neucore\Data\PluginConfigurationDatabase;
use Neucore\Plugin\GeneralInterface;
use Neucore\Plugin\ServiceInterface;
use OpenApi\Attributes as OA;

#[ORM\Entity]
#[ORM\Table(name: "plugins", options: ["charset" => "utf8mb4", "collate" => "utf8mb4_unicode_520_ci"])]
#[OA\Schema(required: ['id', 'name'])]
class Plugin implements \JsonSerializable
{
    #[ORM\Id]
    #[ORM\Column(type: "integer")]
    #[ORM\GeneratedValue]
    #[OA\Property]
    // @phpstan-ignore property.unusedType
    private ?int $id = null;

    #[ORM\Column(type: "string", length: 255)]
    #[OA\Property]
    private string $name = '';

    /**
     * JSON serialized PluginConfigurationDatabase class.
     *
     * @see PluginConfigurationDatabase
     */
    #[ORM\Column(type: "text", length: 16777215, nullable: true)]
    #[OA\Property(property: 'configurationDatabase', ref: '#/components/schemas/PluginConfigurationDatabase')]
    private ?string $configuration = null;

    /**
     * @var ?PluginConfigurationFile
     */
    #[OA\Property(ref: '#/components/schemas/PluginConfigurationFile')]
    private ?PluginConfigurationFile $configurationFile = null;

    private ?ServiceInterface $serviceImplementation = null;

    private ?GeneralInterface $generalImplementation = null;

    public function jsonSerialize(
        bool $onlyRequired = true,
        bool $fullConfig = false,
        bool $includeBackendOnly = true,
    ): array {
        $data = [
            'id' => (int) $this->id,
            'name' => $this->name,
        ];
        if (!$onlyRequired && !empty($this->configuration)) {
            $config = \json_decode((string) $this->configuration, true);
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
        $this->configuration = (string) \json_encode($configuration);
        return $this;
    }

    public function getConfigurationDatabase(): ?PluginConfigurationDatabase
    {
        $data = \json_decode((string) $this->configuration, true);

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
        return $this->serviceImplementation;
    }

    public function setServiceImplementation(?ServiceInterface $implementation): self
    {
        $this->serviceImplementation = $implementation;
        return $this;
    }

    public function getGeneralImplementation(): ?GeneralInterface
    {
        return $this->generalImplementation;
    }

    public function setGeneralImplementation(?GeneralInterface $implementation): self
    {
        $this->generalImplementation = $implementation;
        return $this;
    }
}
