<?php

declare(strict_types=1);

namespace Neucore\Data;

/* @phan-suppress-next-line PhanUnreferencedUseNormal */
use OpenApi\Annotations as OA;

/**
 * Plugin configuration from YAML file.
 *
 * API: The required properties are necessary for the service page where users register their account. The rest
 * is necessary for the admin page.
 *
 * @OA\Schema(required={"properties", "actions"})
 */
class PluginConfigurationFile extends PluginConfiguration implements \JsonSerializable
{
    public const TYPE_GENERAL = 'general';

    public const TYPE_SERVICE = 'service';

    public const PROPERTY_USERNAME = 'username';

    public const PROPERTY_PASSWORD = 'password';

    public const PROPERTY_EMAIL = 'email';

    public const PROPERTY_STATUS = 'status';

    public const PROPERTY_NAME = 'name';

    public const ACTION_UPDATE_ACCOUNT = 'update-account';

    public const ACTION_RESET_PASSWORD = 'reset-password';

    /**
     * @OA\Property()
     */
    public string $name = '';

    /**
     * Not part of the file but will be set when the plugin implementation is loaded.
     *
     * @OA\Property(enum={"general", "service"})
     * @var string[]
     */
    public array $types = [];

    public string $phpClass = '';

    public string $psr4Prefix = '';

    public string $psr4Path = '';

    /**
     * @OA\Property()
     */
    public bool $oneAccount = false;

    /**
     * @OA\Property(enum={"username", "password", "email", "status", "name"})
     * @var string[]
     */
    public array $properties = [];

    /**
     * @OA\Property()
     */
    public bool $showPassword = false;

    /**
     * @OA\Property(enum={"update-account", "reset-password"})
     * @var string[]
     */
    public array $actions = [];

    /**
     * @param array $data Array created from jsonSerialize(), except "types".
     */
    public static function fromArray(array $data): self
    {
        $obj = new self();

        $obj->name = $data['name'] ?? '';
        $obj->phpClass = $data['phpClass'] ?? '';
        $obj->psr4Prefix = $data['psr4Prefix'] ?? '';
        $obj->psr4Path = $data['psr4Path'] ?? '';
        $obj->oneAccount = $data['oneAccount'] ?? false;
        $obj->properties = $data['properties'] ?? [];
        $obj->showPassword = $data['showPassword'] ?? false;
        $obj->actions = $data['actions'] ?? [];

        self::fromArrayCommon($obj, $data);

        return $obj;
    }

    public function jsonSerialize(bool $fullConfig = true, bool $includeBackendOnly = true): array
    {
        $result = [
            'name' => $this->name,
            'types' => $this->types,
            'phpClass' => $this->phpClass,
            'psr4Prefix' => $this->psr4Prefix,
            'psr4Path' => $this->psr4Path,
            'oneAccount' => $this->oneAccount,
            'properties' => $this->properties,
            'showPassword' => $this->showPassword,
            'actions' => $this->actions,
        ];

        return $this->jsonSerializeCommon($result, $fullConfig, $includeBackendOnly);
    }
}
