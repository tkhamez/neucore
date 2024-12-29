<?php

declare(strict_types=1);

namespace Neucore\Data;

use OpenApi\Attributes as OA;

#[OA\Schema(required: ['serviceId', 'serviceName', 'characterId', 'username', 'status', 'name'])]
class ServiceAccount implements \JsonSerializable
{
    #[OA\Property(property: 'serviceId', type: 'integer')]
    private int $serviceId;

    #[OA\Property(property: 'serviceName', type: 'string')]
    private string $serviceName;

    #[OA\Property(property: 'characterId', type: 'integer', format: 'int64')]
    private int $characterId;

    #[OA\Property(property: 'username', type: 'string', nullable: true)]
    private ?string $username;

    #[OA\Property(property: 'status', type: 'string', enum: ['Pending', 'Active', 'Deactivated', 'Unknown'], nullable: true)]
    private ?string $status;

    #[OA\Property(property: 'name', type: 'string', nullable: true)]
    private ?string $name;

    public function __construct(
        int $serviceId,
        string $serviceName,
        int $characterId,
        ?string $username,
        ?string $status,
        ?string $name,
    ) {
        $this->serviceId = $serviceId;
        $this->serviceName = $serviceName;
        $this->characterId = $characterId;
        $this->username = $username;
        $this->status = $status;
        $this->name = $name;
    }

    public function jsonSerialize(): array
    {
        return [
            'serviceId' => $this->serviceId,
            'serviceName' => $this->serviceName,
            'characterId' => $this->characterId,
            'username' => $this->username,
            'status' => $this->status,
            'name' => $this->name,
        ];
    }
}
