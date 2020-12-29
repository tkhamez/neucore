<?php

declare(strict_types=1);

namespace Neucore\Plugin;

use OpenApi\Annotations as OA;

/**
 * @OA\Schema(required={"characterId", "username", "password", "email"})
 */
class AccountData implements \JsonSerializable
{
    /**
     * @OA\Property()
     * @var int
     */
    private $characterId;

    /**
     * @OA\Property(nullable=true)
     * @var string|null
     */
    private $username;

    /**
     * @OA\Property(nullable=true)
     * @var string|null
     */
    private $password;

    /**
     * @OA\Property(nullable=true)
     * @var string|null
     */
    private $email;

    public function __construct(
        int $characterId,
        string $username = null,
        string $password = null,
        string $email = null
    ) {
        $this->characterId = $characterId;
        $this->username = $username;
        $this->password = $password;
        $this->email = $email;
    }

    public function jsonSerialize(): array
    {
        return [
            'characterId' => $this->characterId,
            'username' => $this->username,
            'password' => $this->password,
            'email' => $this->email,
        ];
    }

    public function getCharacterId(): int
    {
        return $this->characterId;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(string $username): self
    {
        $this->username = $username;
        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;
        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;
        return $this;
    }
}
