<?php
/** @noinspection PhpUnused */

declare(strict_types=1);

namespace Neucore\Entity;

use OpenApi\Annotations as OA;

/**
 * @OA\Schema(required={"properties", "actions", "URLs", "textAccount", "textTop", "textRegister", "textPending"})
 */
class ServiceConfiguration implements \JsonSerializable
{
    /**
     * @OA\Property()
     * @var string
     */
    public $phpClass = '';

    /**
     * @OA\Property()
     * @var string
     */
    public $psr4Prefix = '';

    /**
     * @OA\Property()
     * @var string
     */
    public $psr4Path = '';

    /**
     * @OA\Property()
     * @var int[]
     */
    public $requiredGroups = [];

    /**
     * @OA\Property(enum={"username", "password", "email", "status"})
     * @var string[]
     */
    public $properties = [];

    /**
     * @OA\Property()
     * @var bool
     */
    public $showPassword = false;

    /**
     * @OA\Property(enum={"update-account", "reset-password"})
     * @var string[]
     */
    public $actions = [];

    /**
     * @OA\Property(type="array", @OA\Items(ref="#/components/schemas/ServiceConfigurationURL"))
     * @var ServiceConfigurationURL[]
     */
    public $URLs = [];

    /**
     * @OA\Property()
     * @var string
     */
    public $textAccount = '';

    /**
     * @OA\Property()
     * @var string
     */
    public $textTop = '';

    /**
     * @OA\Property()
     * @var string
     */
    public $textRegister = '';

    /**
     * @OA\Property()
     * @var string
     */
    public $textPending = '';

    public static function fromArray(array $data): self
    {
        $obj = new self;
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
