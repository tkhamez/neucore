<?php

declare(strict_types=1);

namespace Neucore\Entity;

/* @phan-suppress-next-line PhanUnreferencedUseNormal */
use Doctrine\ORM\Mapping as ORM;
/* @phan-suppress-next-line PhanUnreferencedUseNormal */
use OpenApi\Annotations as OA;

/**
 * A system settings variable.
 *
 * This is also used as a storage for Storage\Variables with the prefix "__storage__" if APCu is not available.
 *
 * @see \Neucore\Storage\Variables
 *
 * @OA\Schema(
 *     required={"name", "value"}
 * )
 * @ORM\Entity
 * @ORM\Table(name="system_variables", options={"charset"="utf8mb4", "collate"="utf8mb4_unicode_520_ci"})
 */
class SystemVariable implements \JsonSerializable
{
    /**
     * Public variables, visible to all users, even before login.
     */
    public const SCOPE_PUBLIC = 'public';

    /**
     * Variables that are only visible to users with the "settings" role.
     */
    public const SCOPE_SETTINGS = 'settings';

    /**
     * Variables that are not exposed to the frontend.
     */
    public const SCOPE_BACKEND = 'backend';

    /**
     * System settings variable, "0" or "1".
     *
     * Allow users to delete their character.
     *
     * Scope = public
     */
    public const ALLOW_CHARACTER_DELETION = 'allow_character_deletion';

    /**
     * System settings variable, "0" or "1".
     *
     * Activates the login URL without ESI scopes.
     *
     * Scope = settings
     */
    public const ALLOW_LOGIN_NO_SCOPES = 'allow_login_no_scopes';

    /**
     * System settings variable, "0" or "1".
     *
     * Disables login with characters that are not the main character of an account.
     *
     * Scope = settings
     */
    public const DISABLE_ALT_LOGIN = 'disable_alt_login';

    /**
     * System settings variable, "0" or "1".
     *
     * 1: The API for application does not return groups for a player account
     *    if one or more of their characters has an invalid token.
     *
     * 0: ignore invalid tokens.
     *
     * Scope = settings
     */
    public const GROUPS_REQUIRE_VALID_TOKEN = 'groups_require_valid_token';

    /**
     * How long the deactivation of the account will be delayed after a token became invalid.
     *
     * Scope = settings
     */
    public const ACCOUNT_DEACTIVATION_DELAY = 'account_deactivation_delay';

    /**
     * Limit deactivation of groups to players that have a character in these alliances,
     * comma separated list of IDs
     *
     * Scope = settings
     */
    public const ACCOUNT_DEACTIVATION_ALLIANCES = 'account_deactivation_alliances';

    /**
     * Limit deactivation of groups to players that have a character in these corporations,
     * comma separated list of IDs
     *
     * Scope = settings
     */
    public const ACCOUNT_DEACTIVATION_CORPORATIONS = 'account_deactivation_corporations';

    /**
     * Number of days for the "check-tokens" command with the "characters = active" option.
     *
     * Scope = settings
     */
    public const ACCOUNT_DEACTIVATION_ACTIVE_DAYS = 'account_deactivation_active_days';

    /**
     * This is to reduce 403 errors from ESI for structure name update (see frontend for more details).
     *
     * Scope = settings
     */
    public const FETCH_STRUCTURE_NAME_ERROR_DAYS = 'fetch_structure_name_error_days';

    /**
     * EVE character name for the character that can be used to send mails.
     *
     * Scope = settings
     */
    public const MAIL_CHARACTER = 'mail_character';

    /**
     * ESI token to send mails.
     *
     * JSON with character ID, access token, expire time and refresh token.
     *
     * Scope = backend
     */
    public const MAIL_TOKEN = 'mail_token';

    /**
     * Activate the "invalid ESI token" EVE mail notification
     *
     * Scope = settings
     */
    public const MAIL_INVALID_TOKEN_ACTIVE = 'mail_invalid_token_active';

    /**
     * The "invalid ESI token" EVE mail is only send to accounts that have a character in one of these alliances
     * (comma separated list of EVE alliance IDs).
     *
     * Scope = settings
     */
    public const MAIL_INVALID_TOKEN_ALLIANCES = 'mail_invalid_token_alliances';

    /**
     * The "invalid ESI token" EVE mail is only send to accounts that have a character in one of these corporations
     * (comma separated list of EVE corporation IDs).
     *
     * Scope = settings
     */
    public const MAIL_INVALID_TOKEN_CORPORATIONS = 'mail_invalid_token_corporations';

    /**
     * Subject for "invalid ESI token" EVE mail notification
     *
     * Scope = settings
     */
    public const MAIL_INVALID_TOKEN_SUBJECT = 'mail_invalid_token_subject';

    /**
     * Body for "invalid ESI token" EVE mail notification
     *
     * Scope = settings
     */
    public const MAIL_INVALID_TOKEN_BODY = 'mail_invalid_token_body';

    /**
     * Scope = settings
     */
    public const MAIL_MISSING_CHARACTER_ACTIVE = 'mail_missing_character_active';

    /**
     * Corporations with member tracking enabled.
     *
     * Scope = settings
     */
    public const MAIL_MISSING_CHARACTER_CORPORATIONS = 'mail_missing_character_corporations';

    /**
     * Scope = settings
     */
    public const MAIL_MISSING_CHARACTER_SUBJECT = 'mail_missing_character_subject';

    /**
     * Scope = settings
     */
    public const MAIL_MISSING_CHARACTER_BODY = 'mail_missing_character_body';

    /**
     * Defines the minimum number of days that must pass before the mail is resent.
     * Also defines a maximum number of days since the last login, only within these
     * days the mail will be sent.
     *
     * Scope = settings
     */
    public const MAIL_MISSING_CHARACTER_RESEND = 'mail_missing_character_resend';

    /**
     * Value for HTML head title tag.
     *
     * Scope = public
     */
    public const CUSTOMIZATION_DOCUMENT_TITLE = 'customization_document_title';

    /**
     * URL for the links of the logos in the navigation bar and on the home page.
     *
     * Scope = public
     */
    public const CUSTOMIZATION_WEBSITE = 'customization_website';

    /**
     * Organization name used in navigation bar.
     *
     * Scope = public
     */
    public const CUSTOMIZATION_NAV_TITLE = 'customization_nav_title';

    /**
     * Organization logo used in navigation bar (Base64 encoded).
     *
     * Scope = public
     */
    public const CUSTOMIZATION_NAV_LOGO = 'customization_nav_logo';

    /**
     * Headline on the home page.
     *
     * Scope = public
     */
    public const CUSTOMIZATION_HOME_HEADLINE = 'customization_home_headline';

    /**
     * Text below the headline on the homepage.
     *
     * Scope = public
     */
    public const CUSTOMIZATION_HOME_DESCRIPTION = 'customization_home_description';

    /**
     * Organization logo used on the home page (Base64 encoded).
     *
     * Scope = public
     */
    public const CUSTOMIZATION_HOME_LOGO = 'customization_home_logo';

    /**
     * Text below the login button.
     *
     * Scope = public
     */
    public const CUSTOMIZATION_LOGIN_TEXT = 'customization_login_text';

    /**
     * Text area on the home page.
     *
     * Scope = public
     */
    public const CUSTOMIZATION_HOME_MARKDOWN = 'customization_home_markdown';

    /**
     * Text for the footer.
     *
     * Scope = public
     */
    public const CUSTOMIZATION_FOOTER_TEXT = 'customization_footer_text';

    /**
     * Scope = settings
     */
    public const RATE_LIMIT_APP_MAX_REQUESTS = 'rate_limit_app_max_requests';

    /**
     * Scope = settings
     */
    public const RATE_LIMIT_APP_RESET_TIME = 'rate_limit_app_reset_time';

    /**
     * Scope = settings
     */
    public const RATE_LIMIT_APP_ACTIVE = 'rate_limit_app_active';

    public const TOKEN_ID = 'id';
    public const TOKEN_ACCESS = 'access';
    public const TOKEN_REFRESH = 'refresh';
    public const TOKEN_EXPIRES = 'expires';

    /**
     * Variable name.
     *
     * @OA\Property(maxLength=255)
     * @ORM\Column(type="string", length=255)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="NONE")
     */
    private string $name;

    /**
     * Variable value.
     *
     * @OA\Property(nullable=true)
     * @ORM\Column(name="variable_value", type="text", length=16777215, nullable=true)
     */
    private ?string $value = null;

    /**
     * @ORM\Column(type="string", length=16, options={"default" : "public"})
     */
    private string $scope = self::SCOPE_PUBLIC;

    public function jsonSerialize(): array
    {
        return [
            'name' => $this->name,
            'value' => $this->value,
        ];
    }

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getValue(): string
    {
        return (string) $this->value;
    }

    public function setValue(string $value): SystemVariable
    {
        switch ($this->name) {
            case self::ALLOW_CHARACTER_DELETION:
            case self::ALLOW_LOGIN_NO_SCOPES:
            case self::DISABLE_ALT_LOGIN:
            case self::GROUPS_REQUIRE_VALID_TOKEN:
            case self::MAIL_INVALID_TOKEN_ACTIVE:
            case self::MAIL_MISSING_CHARACTER_ACTIVE:
            case self::RATE_LIMIT_APP_ACTIVE:
                $this->value = $value ? '1' : '0';
                break;
            case self::ACCOUNT_DEACTIVATION_DELAY:
            case self::MAIL_MISSING_CHARACTER_RESEND:
            case self::RATE_LIMIT_APP_RESET_TIME:
            case self::RATE_LIMIT_APP_MAX_REQUESTS:
                $this->value = (string) abs((int) $value);
                break;
            case self::MAIL_INVALID_TOKEN_ALLIANCES:
            case self::MAIL_INVALID_TOKEN_CORPORATIONS:
            case self::MAIL_MISSING_CHARACTER_CORPORATIONS:
                $allianceIds = [];
                foreach (explode(',', $value) as $allianceId) {
                    if ((int) $allianceId > 0) {
                        $allianceIds[] = (int) $allianceId;
                    }
                }
                $this->value = implode(',', $allianceIds);
                break;
            case self::MAIL_INVALID_TOKEN_BODY:
            case self::MAIL_MISSING_CHARACTER_BODY:
            case self::CUSTOMIZATION_LOGIN_TEXT:
            case self::CUSTOMIZATION_HOME_MARKDOWN:
                $this->value = $value;
                break;
            case self::CUSTOMIZATION_HOME_LOGO:
            case self::CUSTOMIZATION_NAV_LOGO:
                if (preg_match('#^data:image/[a-z+]+;base64,[a-zA-Z\d+/]+={0,2}$#', $value)) {
                    $this->value = $value;
                }
                break;
            default: // e.g. MAIL_INVALID_TOKEN_SUBJECT
                $this->value = trim(str_replace(["\r\n", "\n"], ' ', $value));
        }

        return $this;
    }

    public function getScope(): string
    {
        return $this->scope;
    }

    public function setScope(string $scope): SystemVariable
    {
        $this->scope = $scope;

        return $this;
    }
}
