<?php

declare(strict_types=1);

namespace Neucore\Plugin;

/**
 * Represents a service account.
 *
 * $characterId and either $username or $email must be set, $password and $status are optional.
 * If the account status is not provided it is considered to be active.
 * If a password is provided, it will be displayed to the user.
 */
class ServiceAccountData implements \JsonSerializable
{
    const STATUS_PENDING = 'Pending';

    const STATUS_ACTIVE = 'Active';

    const STATUS_DEACTIVATED = 'Deactivated';

    const STATUS_UNKNOWN = 'Unknown';

    /**
     * @var int
     */
    private $characterId;

    /**
     * @var string|null
     */
    private $username;

    /**
     * @var string|null
     */
    private $password;

    /**
     * @var string|null
     */
    private $email;

    /**
     * @var string|null
     */
    private $status;

    public function __construct(
        int $characterId,
        string $username = null,
        string $password = null,
        string $email = null,
        string $status = null
    ) {
        $this->characterId = $characterId;
        $this->username = $username;
        $this->password = $password;
        $this->email = $email;
        $this->status = $status;
    }

    public function jsonSerialize(): array
    {
        return [
            'characterId' => $this->characterId,
            'username' => $this->username,
            'password' => $this->password,
            'email' => $this->email,
            'status' => $this->status,
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

    public function getStatus(): ?string
    {
        return $this->status;
    }

    /**
     * @param string $status One of the self::STATUS_* constants. Invalid values are ignored.
     * @return self
     */
    public function setStatus(string $status): self
    {
        if (in_array(
            $status,
            [self::STATUS_ACTIVE, self::STATUS_DEACTIVATED, self::STATUS_PENDING, self::STATUS_UNKNOWN]
        )) {
            $this->status = $status;
        }
        return $this;
    }
}
