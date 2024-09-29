<?php

declare(strict_types=1);

namespace Neucore\Data;

use OpenApi\Attributes as OA;

/**
 * Plugin configuration stored in database.
 *
 * API: The required properties are necessary for the service page where users register their account. The rest
 * is necessary for the admin page.
 */
#[OA\Schema(required: ['URLs', 'textAccount', 'textTop', 'textRegister', 'textPending', 'configurationData'])]
class PluginConfigurationDatabase extends PluginConfiguration implements \JsonSerializable
{
    /**
     * Inactive plugins are neither updated by the cron job nor displayed to the user.
     *
     * From admin UI.
     */
    #[OA\Property]
    public bool $active = false;

    /**
     * From admin UI.
     *
     * @var int[]
     */
    #[OA\Property]
    public array $requiredGroups = [];

    /**
     * @param array $data Array created from jsonSerialize().
     */
    public static function fromArray(array $data): self
    {
        $obj = new self();

        $obj->active = $data['active'] ?? false;
        $obj->requiredGroups = $data['requiredGroups'] ?? [];

        self::fromArrayCommon($obj, $data);

        return $obj;
    }

    /**
     * @param bool $fullConfig Only required API properties if true
     * @param bool $includeBackendOnly Include PHP autoloader properties if true
     * @return array
     */
    public function jsonSerialize(bool $fullConfig = true, bool $includeBackendOnly = true): array
    {
        $result = [
            'active' => $this->active,
            'requiredGroups' => $this->requiredGroups,
        ];

        if (!$fullConfig) {
            // keep only required API properties
            unset($result['active']);
            unset($result['requiredGroups']);
        }

        return $this->jsonSerializeCommon($result, $fullConfig, $includeBackendOnly);
    }
}
