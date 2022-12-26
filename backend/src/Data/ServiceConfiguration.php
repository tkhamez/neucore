<?php

/** @noinspection PhpUnusedAliasInspection */

declare(strict_types=1);

namespace Neucore\Data;

/* @phan-suppress-next-line PhanUnreferencedUseNormal */
use OpenApi\Annotations as OA;

/**
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
    public ?string $pluginYml = '';

    /**
     * Inactive plugins are neither updated by the cron job nor displayed to the user.
     *
     * From admin UI.
     *
     * @OA\Property()
     */
    public ?bool $active = false;

    /**
     * From admin UI.
     *
     * @OA\Property()
     * @var int[]
     */
    public ?array $requiredGroups = [];

    /**
     * From plugin.yml
     *
     * @OA\Property()
     */
    public ?string $phpClass = '';

    /**
     * From plugin.yml
     *
     * @OA\Property()
     */
    public ?string $psr4Prefix = '';

    /**
     * From plugin.yml
     *
     * @OA\Property()
     */
    public ?string $psr4Path = '';

    /**
     * From plugin.yml
     *
     * @OA\Property()
     */
    public ?bool $oneAccount = false;

    /**
     * From plugin.yml
     *
     * @OA\Property(enum={"username", "password", "email", "status", "name"})
     * @var string[]
     */
    public ?array $properties = [];

    /**
     * From plugin.yml
     *
     * @OA\Property()
     */
    public ?bool $showPassword = false;

    /**
     * From plugin.yml
     *
     * @OA\Property(enum={"update-account", "reset-password"})
     * @var string[]
     */
    public ?array $actions = [];

    /**
     * Optionally from plugin.yml, overwritten in admin UI.
     *
     * @OA\Property(type="array", @OA\Items(ref="#/components/schemas/ServiceConfigurationURL"))
     * @var ServiceConfigurationURL[]
     */
    public ?array $URLs = [];

    /**
     * Optionally from plugin.yml, overwritten in admin UI.
     *
     * @OA\Property()
     */
    public ?string $textTop = '';

    /**
     * Optionally from plugin.yml, overwritten in admin UI.
     *
     * @OA\Property()
     */
    public ?string $textAccount = '';

    /**
     * Optionally from plugin.yml, overwritten in admin UI.
     *
     * @OA\Property()
     */
    public ?string $textRegister = '';

    /**
     * Optionally from plugin.yml, overwritten in admin UI.
     *
     * @OA\Property()
     */
    public ?string $textPending = '';

    /**
     * Optionally from plugin.yml, overwritten in admin UI.
     *
     * @OA\Property()
     */
    public string $configurationData = '';

    public static function fromArray(array $data): self
    {
        $obj = new self();
        foreach ($data as $name => $value) {
            if ($name === 'URLs') {
                $urlValues = [];
                foreach ($value as $url) {
                    $valueObject = new ServiceConfigurationURL();
                    $valueObject->url = $url['url'];
                    $valueObject->title = $url['title'];
                    $valueObject->target = $url['target'];
                    $urlValues[] = $valueObject;
                }
                $value = $urlValues;
            }
            $obj->{$name} = $value;
        }
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
