<?php

declare(strict_types=1);

namespace Neucore\Entity;

use OpenApi\Annotations as OA;

/**
 * @OA\Schema(required={"url", "title", "target"})
 */
class ServiceConfigurationURL implements \JsonSerializable
{
    /**
     * @OA\Property(description="placeholders: {username}, {password}, {email}")
     * @var string
     */
    public $url = '';

    /**
     * @OA\Property()
     * @var string
     */
    public $title = '';

    /**
     * @OA\Property()
     * @var string
     */
    public $target = '';

    public function jsonSerialize(): array
    {
        $return = [];
        /* @phan-suppress-next-line PhanTypeSuspiciousNonTraversableForeach */
        foreach ($this as $key => $value) {
            $return[$key] = $value;
        }
        return $return;
    }
}
