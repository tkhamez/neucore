<?php
/** @noinspection PhpUnusedAliasInspection */

declare(strict_types=1);

namespace Neucore\Entity;

use OpenApi\Annotations as OA;

/**
 * @OA\Schema(required={
 *     "properties", "actions", "URLs", "textAccount", "textTop", "textRegister", "textPending", "configurationData"
 * })
 */
class ServiceConfiguration implements \JsonSerializable
{
    const ACTION_UPDATE_ACCOUNT = 'update-account';

    const ACTION_RESET_PASSWORD = 'reset-password';

    /**
     * @OA\Property()
     */
    public ?string $phpClass = '';

    /**
     * @OA\Property()
     */
    public ?string $psr4Prefix = '';

    /**
     * @OA\Property()
     */
    public ?string $psr4Path = '';

    /**
     * @OA\Property()
     */
    public ?bool $oneAccount = false;

    /**
     * @OA\Property()
     * @var int[]
     */
    public ?array $requiredGroups = [];

    /**
     * @OA\Property(enum={"username", "password", "email", "status", "name"})
     * @var string[]
     */
    public ?array $properties = [];

    /**
     * @OA\Property()
     */
    public ?bool $showPassword = false;

    /**
     * @OA\Property(enum={"update-account", "reset-password"})
     * @var string[]
     */
    public ?array $actions = [];

    /**
     * @OA\Property(type="array", @OA\Items(ref="#/components/schemas/ServiceConfigurationURL"))
     * @var ServiceConfigurationURL[]
     */
    public ?array $URLs = [];

    /**
     * @OA\Property()
     */
    public ?string $textAccount = '';

    /**
     * @OA\Property()
     */
    public ?string $textTop = '';

    /**
     * @OA\Property()
     */
    public ?string $textRegister = '';

    /**
     * @OA\Property()
     */
    public ?string $textPending = '';

    /**
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
