<?php

declare(strict_types=1);

namespace Neucore\Plugin;

use Neucore\Entity\Player;
use Neucore\Entity\Service;
use OpenApi\Annotations as OA;

/**
 * @OA\Schema(required={"id", "service", "player"})
 */
class ServiceAccount implements \JsonSerializable
{
    /**
     * @OA\Property(ref="#/components/schemas/Service")
     * @var Service|null
     */
    private $service;

    /**
     * @OA\Property(ref="#/components/schemas/Player")
     * @var Player|null
     */
    private $player;

    /**
     * @OA\Property(type="array", @OA\Items(ref="#/components/schemas/AccountData"))
     * @var array
     */
    private $data = [];

    public function jsonSerialize(): array
    {
        return [
            'service' => $this->service,
            'player' => $this->player ? $this->player->jsonSerialize(true) : null,
            'data' => $this->data,
        ];
    }

    public function getService(): ?Service
    {
        return $this->service;
    }

    public function setService(Service $service): self
    {
        $this->service = $service;
        return $this;
    }

    public function getPlayer(): ?Player
    {
        return $this->player;
    }

    public function setPlayer(Player $player): self
    {
        $this->player = $player;
        return $this;
    }

    /**
     * @return AccountData[]
     */
    public function getData(): array
    {
        return $this->data;
    }

    public function setData(AccountData ...$data): self
    {
        $this->data = $data;
        return $this;
    }
}
