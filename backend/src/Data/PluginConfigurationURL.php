<?php

declare(strict_types=1);

namespace Neucore\Data;

use OpenApi\Attributes as OA;

#[OA\Schema(required: ['url', 'title', 'target'])]
class PluginConfigurationURL implements \JsonSerializable
{
    #[OA\Property(description: 'placeholders: {plugin_id}, {username}, {password}, {email}')]
    public string $url = '';

    #[OA\Property]
    public string $title = '';

    #[OA\Property]
    public string $target = '';

    public function jsonSerialize(): array
    {
        return [
            'url' => $this->url,
            'title' => $this->title,
            'target' => $this->target,
        ];
    }
}
