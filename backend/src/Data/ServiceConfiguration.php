<?php

/** @noinspection PhpUnusedAliasInspection */

declare(strict_types=1);

namespace Neucore\Data;

/* @phan-suppress-next-line PhanUnreferencedUseNormal */
use OpenApi\Annotations as OA;

/**
 * Service configuration.
 *
 * Note: The property names of this class and the names of the YAML keys from the plugin.yml file of
 * plugins need to be the same.
 *
 * @OA\Schema(required={
 *     "properties", "actions", "URLs", "textAccount", "textTop", "textRegister", "textPending", "configurationData"
 * })
 */
class ServiceConfiguration implements \JsonSerializable
{
    public const ACTION_UPDATE_ACCOUNT = 'update-account';

    public const ACTION_RESET_PASSWORD = 'reset-password';

    /**
     * From admin UI.
     *
     * @OA\Property()
     */
    public string $pluginYml = '';

    /**
     * Inactive plugins are neither updated by the cron job nor displayed to the user.
     *
     * From admin UI.
     *
     * @OA\Property()
     */
    public bool $active = false;

    /**
     * From admin UI.
     *
     * @OA\Property()
     * @var int[]
     */
    public array $requiredGroups = [];

    /**
     * From plugin.yml
     *
     * @OA\Property()
     */
    public string $phpClass = '';

    /**
     * From plugin.yml
     *
     * @OA\Property()
     */
    public string $psr4Prefix = '';

    /**
     * From plugin.yml
     *
     * @OA\Property()
     */
    public string $psr4Path = '';

    /**
     * From plugin.yml
     *
     * @OA\Property()
     */
    public bool $oneAccount = false;

    /**
     * From plugin.yml
     *
     * @OA\Property(enum={"username", "password", "email", "status", "name"})
     * @var string[]
     */
    public array $properties = [];

    /**
     * From plugin.yml
     *
     * @OA\Property()
     */
    public bool $showPassword = false;

    /**
     * From plugin.yml
     *
     * @OA\Property(enum={"update-account", "reset-password"})
     * @var string[]
     */
    public array $actions = [];

    /**
     * Optionally from plugin.yml, overwritten in admin UI.
     *
     * @OA\Property(type="array", @OA\Items(ref="#/components/schemas/ServiceConfigurationURL"))
     * @var ServiceConfigurationURL[]
     */
    public array $URLs = [];

    /**
     * Optionally from plugin.yml, overwritten in admin UI.
     *
     * @OA\Property()
     */
    public string $textTop = '';

    /**
     * Optionally from plugin.yml, overwritten in admin UI.
     *
     * @OA\Property()
     */
    public string $textAccount = '';

    /**
     * Optionally from plugin.yml, overwritten in admin UI.
     *
     * @OA\Property()
     */
    public string $textRegister = '';

    /**
     * Optionally from plugin.yml, overwritten in admin UI.
     *
     * @OA\Property()
     */
    public string $textPending = '';

    /**
     * Optionally from plugin.yml, overwritten in admin UI.
     *
     * @OA\Property()
     */
    public string $configurationData = '';

    /**
     * @param array $data Array created from jsonSerialize().
     */
    public static function fromArray(array $data): self
    {
        $obj = new self();

        $obj->pluginYml = $data['pluginYml'] ?? '';
        $obj->active = $data['active'] ?? false;
        $obj->requiredGroups = $data['requiredGroups'] ?? [];

        $obj->phpClass = $data['phpClass'] ?? '';
        $obj->psr4Prefix = $data['psr4Prefix'] ?? '';
        $obj->psr4Path = $data['psr4Path'] ?? '';
        $obj->oneAccount = $data['oneAccount'] ?? false;
        $obj->properties = $data['properties'] ?? [];
        $obj->showPassword = $data['showPassword'] ?? false;
        $obj->actions = $data['actions'] ?? [];
        $obj->URLs = [];
        foreach ($data['URLs'] ?? [] as $urlData) {
            $urlObj = new ServiceConfigurationURL();
            $urlObj->url = $urlData['url'] ?? '';
            $urlObj->title = $urlData['title'] ?? '';
            $urlObj->target = $urlData['target'] ?? '';
            $obj->URLs[] = $urlObj;
        }
        $obj->textTop = $data['textTop'] ?? '';
        $obj->textAccount = $data['textAccount'] ?? '';
        $obj->textRegister = $data['textRegister'] ?? '';
        $obj->textPending = $data['textPending'] ?? '';
        $obj->configurationData = $data['configurationData'] ?? '';

        return $obj;
    }

    public function jsonSerialize(): array
    {
        $result = [];
        /* @phan-suppress-next-line PhanTypeSuspiciousNonTraversableForeach */
        foreach ($this as $key => $value) {
            if ($key === 'URLs') {
                $values = [];
                foreach ($value as $item) {
                    /* @var ServiceConfigurationURL $item */
                    $values[] = $item->jsonSerialize();
                }
                $value = $values;
            }
            $result[$key] = $value;
        }
        return $result;
    }
}
