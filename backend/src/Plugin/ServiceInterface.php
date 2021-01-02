<?php

declare(strict_types=1);

namespace Neucore\Plugin;

use Psr\Log\LoggerInterface;

interface ServiceInterface
{
    public function __construct(LoggerInterface $logger);

    /**
     * Returns all accounts for the characters provided.
     *
     * @return ServiceAccountData[]
     */
    public function getAccounts(CoreCharacter ...$characters): array;

    /**
     * Creates new account and returns account data or null on error.
     *
     * This is not called if there is already an account for the character ID.
     */
    public function register(CoreCharacter $character, string $emailAddress): ?ServiceAccountData;
}
