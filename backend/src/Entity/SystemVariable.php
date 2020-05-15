<?php

declare(strict_types=1);

namespace Neucore\Entity;

use Doctrine\ORM\Mapping as ORM;
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
 * @ORM\Table(name="system_variables")
 */
class SystemVariable implements \JsonSerializable
{
    /**
     * Public variables.
     */
    const SCOPE_PUBLIC = 'public';

    /**
     * Variables that are only visible on the settings page.
     */
    const SCOPE_SETTINGS = 'settings';

    /**
     * Variables that are not exposed to the frontend.
     */
    const SCOPE_BACKEND = 'backend';

    /**
     * System settings variable, "0" or "1".
     *
     * Allow users to delete their character.
     *
     * Scope = public
     */
    const ALLOW_CHARACTER_DELETION = 'allow_character_deletion';

    /**
     * System settings variable, "0" or "1".
     *
     * Activates the login URL for managed accounts.
     *
     * Scope = settings
     */
    const ALLOW_LOGIN_MANAGED = 'allow_login_managed';

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
    const GROUPS_REQUIRE_VALID_TOKEN = 'groups_require_valid_token';

    /**
     * How long the deactivation of the account will be delayed after a token became invalid.
     *
     * Scope = settings
     */
    const ACCOUNT_DEACTIVATION_DELAY = 'account_deactivation_delay';

    /**
     * Limit deactivation of groups to players that have a character in these alliances,
     * comma separated list of IDs
     *
     * Scope = settings
     */
    const ACCOUNT_DEACTIVATION_ALLIANCES = 'account_deactivation_alliances';

    /**
     * Limit deactivation of groups to players that have a character in these corporations,
     * comma separated list of IDs
     *
     * Scope = settings
     */
    const ACCOUNT_DEACTIVATION_CORPORATIONS = 'account_deactivation_corporations';

    /**
     * EVE character name for the character that can be used to send mails.
     *
     * Scope = settings
     */
    const MAIL_CHARACTER = 'mail_character';

    /**
     * ESI token to send mails.
     *
     * JSON with character ID, access token, expire time and refresh token.
     *
     * Scope = backend
     */
    const MAIL_TOKEN = 'mail_token';

    /**
     * Activate the "invalid ESI token" EVE mail notification
     *
     * Scope = settings
     */
    const MAIL_INVALID_TOKEN_ACTIVE = 'mail_invalid_token_active';

    /**
     * The "invalid ESI token" EVE mail is only send to accounts that have a character in one of these alliances
     * (comma separated list of EVE alliance IDs).
     *
     * Scope = settings
     */
    const MAIL_INVALID_TOKEN_ALLIANCES = 'mail_invalid_token_alliances';

    /**
     * The "invalid ESI token" EVE mail is only send to accounts that have a character in one of these corporations
     * (comma separated list of EVE corporation IDs).
     *
     * Scope = settings
     */
    const MAIL_INVALID_TOKEN_CORPORATIONS = 'mail_invalid_token_corporations';

    /**
     * Subject for "invalid ESI token" EVE mail notification
     *
     * Scope = settings
     */
    const MAIL_INVALID_TOKEN_SUBJECT = 'mail_invalid_token_subject';

    /**
     * Body for "invalid ESI token" EVE mail notification
     *
     * Scope = settings
     */
    const MAIL_INVALID_TOKEN_BODY = 'mail_invalid_token_body';

    /**
     * Scope = settings
     */
    const MAIL_MISSING_CHARACTER_ACTIVE = 'mail_missing_character_active';

    /**
     * Corporations with member tracking enabled.
     *
     * Scope = settings
     */
    const MAIL_MISSING_CHARACTER_CORPORATIONS = 'mail_missing_character_corporations';

    /**
     * Scope = settings
     */
    const MAIL_MISSING_CHARACTER_SUBJECT = 'mail_missing_character_subject';

    /**
     * Scope = settings
     */
    const MAIL_MISSING_CHARACTER_BODY = 'mail_missing_character_body';

    /**
     * Defines the minimum number of days that must pass before the mail is resent.
     * Also defines a maximum number of days since the last login, only within these
     * days the mail will be sent.
     *
     * Scope = settings
     */
    const MAIL_MISSING_CHARACTER_RESEND = 'mail_missing_character_resend';

    /**
     * Character with director role for member tracking.
     *
     * This is the base name of this variable, the actual variable must have a number suffix
     * because there can be several director characters.
     *
     * Scope = settings
     */
    const DIRECTOR_CHAR = 'director_char_';

    /**
     * Tokens for DIRECTOR_CHAR
     *
     * Scope = backend
     */
    const DIRECTOR_TOKEN = 'director_token_';

    /**
     * The default theme.
     *
     * Scope = public
     */
    const CUSTOMIZATION_DEFAULT_THEME = 'customization_default_theme';

    /**
     * Value for HTML head title tag.
     *
     * Scope = public
     */
    const CUSTOMIZATION_DOCUMENT_TITLE = 'customization_document_title';

    /**
     * URL for the links of the logos in the navigation bar and on the home page.
     *
     * Scope = public
     */
    const CUSTOMIZATION_WEBSITE = 'customization_website';

    /**
     * URL of GitHub repository for various links to the documentation.
     *
     * Scope = public
     */
    const CUSTOMIZATION_GITHUB = 'customization_github';

    /**
     * Organization name used in navigation bar.
     *
     * Scope = public
     */
    const CUSTOMIZATION_NAV_TITLE = 'customization_nav_title';

    /**
     * Organization logo used in navigation bar (Base64 encoded).
     *
     * Scope = public
     */
    const CUSTOMIZATION_NAV_LOGO = 'customization_nav_logo';

    /**
     * Headline on the home page.
     *
     * Scope = public
     */
    const CUSTOMIZATION_HOME_HEADLINE = 'customization_home_headline';

    /**
     * Text below the headline on the homepage.
     *
     * Scope = public
     */
    const CUSTOMIZATION_HOME_DESCRIPTION = 'customization_home_description';

    /**
     * Organization logo used on the home page (Base64 encoded).
     *
     * Scope = public
     */
    const CUSTOMIZATION_HOME_LOGO = 'customization_home_logo';

    /**
     * Text area on the home page.
     *
     * Scope = public
     */
    const CUSTOMIZATION_HOME_MARKDOWN = 'customization_home_markdown';

    /**
     * Text for the footer.
     *
     * Scope = public
     */
    const CUSTOMIZATION_FOOTER_TEXT = 'customization_footer_text';

    /**
     * Scope = settings
     */
    const API_RATE_LIMIT_MAX_REQUESTS = 'api_rate_limit_max_requests';

    /**
     * Scope = settings
     */
    const API_RATE_LIMIT_RESET_TIME = 'api_rate_limit_reset_time';

    /**
     * Scope = settings
     */
    const API_RATE_LIMIT_ACTIVE = 'api_rate_limit_active';

    const TOKEN_ID = 'id';

    const TOKEN_ACCESS = 'access';

    const TOKEN_REFRESH = 'refresh';

    const TOKEN_EXPIRES = 'expires';

    const TOKEN_SCOPES = 'scopes';

    /**
     * Variable name.
     *
     * @OA\Property(maxLength=255)
     * @ORM\Column(type="string", length=255)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="NONE")
     * @var string
     */
    private $name;

    /**
     * Variable value.
     *
     * @OA\Property(nullable=true)
     * @ORM\Column(type="text", length=16777215, nullable=true)
     * @var string
     */
    private $value;

    /**
     * @ORM\Column(type="string", length=16, options={"default" : "public"})
     * @var string
     */
    private $scope = self::SCOPE_PUBLIC;

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
            case self::ALLOW_LOGIN_MANAGED:
            case self::GROUPS_REQUIRE_VALID_TOKEN:
            case self::MAIL_INVALID_TOKEN_ACTIVE:
            case self::MAIL_MISSING_CHARACTER_ACTIVE:
            case self::API_RATE_LIMIT_ACTIVE:
                $this->value = ((bool) $value) ? '1' : '0';
                break;
            case self::ACCOUNT_DEACTIVATION_DELAY:
            case self::MAIL_MISSING_CHARACTER_RESEND:
            case self::API_RATE_LIMIT_RESET_TIME:
            case self::API_RATE_LIMIT_MAX_REQUESTS:
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
            case self::CUSTOMIZATION_HOME_MARKDOWN:
                $this->value = $value;
                break;
            case self::CUSTOMIZATION_HOME_LOGO:
            case self::CUSTOMIZATION_NAV_LOGO:
                if (preg_match('#^data:image/[a-z]+;base64,[a-zA-Z0-9+/]+={0,2}$#', $value)) {
                    $this->value = $value;
                }
                break;
            default:
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
