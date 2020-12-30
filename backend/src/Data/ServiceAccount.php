<?php

declare(strict_types=1);

namespace Neucore\Data;

use Neucore\Entity\Player;
use Neucore\Entity\Service;
use Neucore\Plugin\AccountData;
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
    private $accountData = [];

    public function jsonSerialize(): array
    {
        return [
            'service' => $this->service,
            'player' => $this->player ? $this->player->jsonSerialize(true) : null,
            'accountData' => $this->accountData,
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
    public function getAccountData(): array
    {
        return $this->accountData;
    }

    public function setAccountData(AccountData ...$accountData): self
    {
        $this->accountData = $accountData;
        return $this;
    }
}
