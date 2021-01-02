<?php

declare(strict_types=1);

namespace Neucore\Plugin;

use Psr\Log\LoggerInterface;

interface ServiceInterface
{
    /**
     * A required e-mail address was not provided.
     */
    public const ERROR_MISSING_EMAIL = 'missing_email';

    /**
     * The e-mail address provided belongs to another account.
     */
    public const ERROR_EMAIL_MISMATCH = 'email_mismatch';

    /**
     * Already requested an invited recently and must wait.
     */
    public const ERROR_INVITE_WAIT = 'invite_wait';

    public function __construct(LoggerInterface $logger);

    /**
     * Returns all accounts for the characters provided.
     *
     * @param CoreCharacter[] $characters All characters belong to the same player, but there may be more.
     * @param CoreGroup[] $groups All groups from the player of the characters.
     * @return ServiceAccountData[]
     * @throws Exception In the event of an error when retrieving accounts.
     */
    public function getAccounts(array $characters, array $groups): array;

    /**
     * Creates new account and returns account data or null on error.
     *
     * This is not called if there is already an account for the character.
     *
     * @param CoreGroup[] $groups All groups from the player of the characters.
     * @param int[] $otherCharacterIds All other character IDs from the same player account.
     * @return ServiceAccountData
     * @throws Exception On error, the message should be one of the self::ERROR_* constants
     *                   (it will be shown to the user with a 409 response code) or empty (500 response code).
     */
    public function register(
        CoreCharacter $character,
        array $groups,
        string $emailAddress,
        array $otherCharacterIds
    ): ServiceAccountData;
}
