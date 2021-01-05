<?php

declare(strict_types=1);

namespace Neucore\Plugin;

use Psr\Log\LoggerInterface;

/**
 * Methods to implement for a Neucore service.
 *
 * A player can only register a new account for their main character and only if that account does not
 * already exists or if it's ServiceAccountData::$status is "Deactivated" or "Unknown".
 */
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

    /**
     * The account for the character was not found.
     */
    public const ERROR_ACCOUNT_NOT_FOUND = 'account_not_found';

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
     * Creates new account and returns account data.
     *
     * This is not called if there is already an account for the character.
     *
     * @param CoreGroup[] $groups All groups from the player of the characters.
     * @param int[] $allCharacterIds All character IDs from the same player account.
     * @return ServiceAccountData
     * @throws Exception On error, the message should be one of the self::ERROR_* constants
     *                   (it will be shown to the user with a 409 response code) or empty (500 response code).
     */
    public function register(
        CoreCharacter $character,
        array $groups,
        string $emailAddress,
        array $allCharacterIds
    ): ServiceAccountData;

    /**
     * Updates account information.
     *
     * This is not called if there is no account for the character.
     *
     * @throws Exception On error
     */
    public function updateAccount(CoreCharacter $character, array $groups): void;

    /**
     * Resets and returns the password for the account of the provided character.
     *
     * This is not called if there is no account for the character.
     *
     * @throws Exception
     */
    public function resetPassword(int $characterId): string;
}
